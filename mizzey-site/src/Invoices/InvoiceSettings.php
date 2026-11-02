<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Invoices;

defined('ABSPATH') || exit;

/**
 * The seller's details an invoice carries (ADM-92; the legal entity details are CR-12 and OD-18, the tax treatment
 * OD-09, all the client's to supply), and the invoice number's prefix: a section of WooCommerce's General settings.
 * Each detail left empty is left off the invoice.
 *
 * See specs/023-invoice.
 */
final class InvoiceSettings
{
    public const NAME     = 'mizzey_invoice_legal_name';
    public const ADDRESS  = 'mizzey_invoice_legal_address';
    public const TAX_ID   = 'mizzey_invoice_tax_number';
    public const REGISTER = 'mizzey_invoice_commercial_register';
    public const NOTE     = 'mizzey_invoice_note';
    public const PREFIX   = 'mizzey_invoice_prefix';

    public static function register(): void
    {
        add_filter('woocommerce_general_settings', [self::class, 'settings'], 20, 1);
    }

    public static function prefix(): string
    {
        $prefix = trim((string) get_option(self::PREFIX, 'INV-'));

        return $prefix !== '' ? $prefix : 'INV-';
    }

    /** @return array{name:string,address:string,tax:string,register:string,note:string} */
    public static function seller(): array
    {
        return [
            'name'     => trim((string) get_option(self::NAME, '')),
            'address'  => trim((string) get_option(self::ADDRESS, '')),
            'tax'      => trim((string) get_option(self::TAX_ID, '')),
            'register' => trim((string) get_option(self::REGISTER, '')),
            'note'     => trim((string) get_option(self::NOTE, '')),
        ];
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function settings($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }

        return [...$settings,
            ['title' => __('Invoices', 'mizzey-site'), 'type' => 'title', 'id' => 'mizzey_invoice_options',
                'desc' => __('The seller details printed on every invoice. A detail left empty is left off.', 'mizzey-site')],
            ['title' => __('Legal name', 'mizzey-site'), 'id' => self::NAME, 'type' => 'text', 'default' => ''],
            ['title' => __('Registered address', 'mizzey-site'), 'id' => self::ADDRESS, 'type' => 'textarea', 'default' => ''],
            ['title' => __('Tax registration number', 'mizzey-site'), 'id' => self::TAX_ID, 'type' => 'text', 'default' => ''],
            ['title' => __('Commercial register number', 'mizzey-site'), 'id' => self::REGISTER, 'type' => 'text', 'default' => ''],
            ['title' => __('Note at the foot of the invoice', 'mizzey-site'), 'id' => self::NOTE, 'type' => 'textarea', 'default' => ''],
            ['title' => __('Invoice number prefix', 'mizzey-site'), 'id' => self::PREFIX, 'type' => 'text', 'default' => 'INV-'],
            ['type' => 'sectionend', 'id' => 'mizzey_invoice_options'],
        ];
    }
}
