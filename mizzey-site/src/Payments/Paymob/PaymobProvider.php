<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments\Paymob;

use MizzeySite\Payments\CallbackResult;
use MizzeySite\Payments\PaymentEvent;
use MizzeySite\Payments\PaymentProvider;

defined('ABSPATH') || exit;

/**
 * Paymob, through its official WooCommerce plugin (PAY-13). This class verifies Paymob's word; the plugin keeps
 * taking the payment and making the refund.
 *
 * Paymob signs a transaction with HMAC-SHA512 over twenty of its fields in a fixed order, keyed with the merchant's
 * HMAC secret. The same fields arrive two ways: as JSON in Paymob's server notice (POST, the signature in the `hmac`
 * query parameter) and as query parameters on the customer's return (GET). Only those two shapes are taken. Paymob's
 * signature does not cover the store's own order reference (`merchant_order_id`), so the gate also makes each
 * transaction usable once across the store.
 *
 * Register ids: PAY-04, PAY-13, PAY-16, INT-01. See specs/028-payments.
 */
final class PaymobProvider implements PaymentProvider
{
    public const ID = 'paymob';

    /** The plugin's settings, where it keeps the HMAC secret it was given. */
    public const SETTINGS = 'woocommerce_paymob_settings';

    /** The signed fields, in Paymob's order. */
    private const SIGNED = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order', 'owner',
        'pending', 'source_data_pan', 'source_data_sub_type', 'source_data_type', 'success',
    ];

    /** The plugin's reference for an order: the order id, an underscore and ten digits of time. */
    private const MERCHANT_REFERENCE = '/^([1-9]\d{0,18})_\d{10}$/';

    public function id(): string
    {
        return self::ID;
    }

    public function callbackAction(): string
    {
        return 'paymob_callback';
    }

    public function ownsOrder(\WC_Order $order): bool
    {
        return str_starts_with((string) $order->get_payment_method(), 'paymob');
    }

    public function read(string $method, array $query, string $body): CallbackResult
    {
        $channel = strtoupper($method) === 'POST' ? 'notice' : 'return';
        $secret  = $this->secret();
        if ($secret === '') {
            return CallbackResult::refused('no_hmac_secret', $channel);
        }

        if ($channel === 'notice') {
            $json = json_decode($body, true);
            if (! is_array($json) || ($json['type'] ?? '') !== 'TRANSACTION' || ! is_array($json['obj'] ?? null)) {
                return CallbackResult::refused('not_transaction_notice', $channel);
            }
            $obj    = $json['obj'];
            $fields = $this->fromNotice($obj);
            $ref    = (string) (is_array($obj['order'] ?? null) ? ($obj['order']['merchant_order_id'] ?? '') : '');
            $kind   = ! empty($obj['is_refund']) ? PaymentEvent::REFUND : (! empty($obj['is_void']) ? PaymentEvent::VOID : PaymentEvent::PAYMENT);
        } else {
            $fields = [];
            foreach (self::SIGNED as $name) {
                $fields[$name] = is_scalar($query[$name] ?? null) ? (string) $query[$name] : '';
            }
            $ref  = is_scalar($query['merchant_order_id'] ?? null) ? (string) $query['merchant_order_id'] : '';
            $kind = PaymentEvent::PAYMENT;
        }

        $orderId = preg_match(self::MERCHANT_REFERENCE, $ref, $m) === 1 ? (int) $m[1] : 0;
        $given   = is_scalar($query['hmac'] ?? null) ? strtolower((string) $query['hmac']) : '';
        if ($given === '') {
            return CallbackResult::refused('unsigned', $channel, $orderId);
        }
        if (! hash_equals(self::sign($fields, $secret), $given)) {
            return CallbackResult::refused('bad_signature', $channel, $orderId);
        }
        if ($orderId === 0) {
            return CallbackResult::refused('no_order_reference', $channel);
        }
        if ($fields['id'] === '' || ! ctype_digit($fields['amount_cents'])) {
            return CallbackResult::refused('incomplete_transaction', $channel, $orderId);
        }

        // A payment Paymob later reports as refunded or voided is the payment's own record changing; the refund or
        // void itself arrives as a transaction of its own.
        if ($kind === PaymentEvent::PAYMENT && ($fields['is_refunded'] === 'true' || $fields['is_voided'] === 'true')) {
            $kind = 'payment-update';
        }

        return CallbackResult::verified(new PaymentEvent(
            self::ID,
            $orderId,
            $fields['id'],
            $kind,
            $this->outcome($fields),
            (int) $fields['amount_cents'],
            strtoupper($fields['currency']),
            $fields['created_at'],
            $channel,
        ));
    }

    public function refundReferenceInNote(string $note): ?string
    {
        return preg_match('/Transaction\sRefund\sID\s*:\s*(\d+)/', $note, $m) === 1 ? $m[1] : null;
    }

    /**
     * A refund through Paymob's API, with the secret key the plugin was given. Egypt's API only: a key of another
     * country is not used.
     */
    public function refundTransaction(string $reference, int $amountCents): ?string
    {
        $settings = get_option('woocommerce_paymob-main_settings');
        $key      = is_array($settings) && is_scalar($settings['sec_key'] ?? null) ? trim((string) $settings['sec_key']) : '';
        if (! str_starts_with($key, 'egy_') || ! ctype_digit($reference) || $amountCents <= 0) {
            return null;
        }
        $response = wp_remote_post('https://accept.paymob.com/api/acceptance/void_refund/refund', [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Token ' . $key],
            'body'    => (string) wp_json_encode(['transaction_id' => $reference, 'amount_cents' => $amountCents]),
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $reply = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($reply) && ($reply['success'] ?? null) === true && is_scalar($reply['id'] ?? null) ? (string) $reply['id'] : null;
    }

    /**
     * Paymob's signature over the twenty fields, as lowercase hex.
     *
     * @param array<string,string> $fields
     */
    public static function sign(array $fields, string $secret): string
    {
        $text = '';
        foreach (self::SIGNED as $name) {
            $text .= $fields[$name] ?? '';
        }

        return hash_hmac('sha512', $text, $secret);
    }

    /**
     * The signed fields of a notice, flattened as Paymob flattens them before signing.
     *
     * @param array<string,mixed> $obj
     * @return array<string,string>
     */
    public static function fromNotice(array $obj): array
    {
        $source = is_array($obj['source_data'] ?? null) ? $obj['source_data'] : [];
        $flat   = $obj;
        $flat['order']                = is_array($obj['order'] ?? null) ? ($obj['order']['id'] ?? '') : ($obj['order'] ?? '');
        $flat['source_data_pan']      = $source['pan'] ?? '';
        $flat['source_data_sub_type'] = $source['sub_type'] ?? '';
        $flat['source_data_type']     = $source['type'] ?? '';

        $fields = [];
        foreach (self::SIGNED as $name) {
            $value = $flat[$name] ?? '';
            $fields[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : '');
        }

        return $fields;
    }

    /** @param array<string,string> $fields */
    private function outcome(array $fields): string
    {
        if ($fields['pending'] === 'true') {
            return PaymentEvent::PENDING;
        }

        return $fields['success'] === 'true' && $fields['error_occured'] !== 'true' ? PaymentEvent::SUCCESS : PaymentEvent::FAILED;
    }

    private function secret(): string
    {
        $settings = get_option(self::SETTINGS);

        return is_array($settings) && is_scalar($settings['hmac_hidden'] ?? null) ? trim((string) $settings['hmac_hidden']) : '';
    }
}
