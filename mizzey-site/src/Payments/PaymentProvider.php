<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

defined('ABSPATH') || exit;

/**
 * The seam between the store and a payment provider (PAY-01). The checkout never names a provider: it offers
 * WooCommerce's payment gateways. What Mizzey adds around a provider's gateway is the gate on its callback address,
 * and the gate asks the provider only these questions. A second provider is another implementation, registered
 * through the `mizzey_payment_providers` filter; the checkout does not change.
 *
 * Register ids: PAY-01, PAY-13. See specs/028-payments.
 */
interface PaymentProvider
{
    /** A short stable name, stored with each event. */
    public function id(): string;

    /** The WooCommerce API action its callback arrives on: `/wc-api/<action>/`. */
    public function callbackAction(): string;

    /** Whether an order was placed with one of this provider's gateways. */
    public function ownsOrder(\WC_Order $order): bool;

    /**
     * Read one request to the callback address and decide whether it is the provider's word.
     *
     * @param string               $method GET for the customer's return, POST for the provider's notice.
     * @param array<string,mixed>  $query  The query string, as PHP parsed it.
     * @param string               $body   The raw request body.
     */
    public function read(string $method, array $query, string $body): CallbackResult;

    /** The provider's reference for a refund, when an order note the provider's gateway wrote names one. */
    public function refundReferenceInNote(string $note): ?string;

    /**
     * Refund one of the provider's transactions in full or in part, outside WooCommerce's refund records: used for a
     * payment the order never counted (a second payment of a paid order). Returns the provider's reference for the
     * refund, or null when the provider did not make it.
     */
    public function refundTransaction(string $reference, int $amountCents): ?string;
}
