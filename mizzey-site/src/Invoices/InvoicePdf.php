<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Invoices;

defined('ABSPATH') || exit;

/**
 * The invoice as a PDF file (ADM-92, ACCT-12), rendered by mPDF, the library bundled in `lib/` (D-24: a library inside
 * our own code, not a plugin), with DejaVu Sans, whose OpenType layout shapes Arabic. The library is loaded only here,
 * when an invoice is made. mPDF reads no remote resources: the document carries no images or links.
 *
 * See specs/023-invoice.
 */
final class InvoicePdf
{
    private const STYLE = 'body { font-family: dejavusans; font-size: 10pt; color: #1d1d1b; }
        h1 { font-size: 18pt; margin: 0 0 12pt; } h2 { font-size: 11pt; margin: 14pt 0 4pt; }
        table { border-collapse: collapse; } td, th { padding: 4pt 6pt; vertical-align: top; }
        .mizzey-invoice-lines { margin-top: 12pt; } .mizzey-invoice-lines th { background: #eef1f3; text-align: {start}; }
        .mizzey-invoice-lines td { border-bottom: 0.5pt solid #d9dde0; }
        .mizzey-invoice-totals { margin-top: 10pt; } .mizzey-invoice-amount { text-align: {end}; }
        .mizzey-invoice-note { margin-top: 16pt; font-size: 9pt; }';

    /** The PDF's bytes, or '' when the order has no invoice. */
    public static function bytes(\WC_Order $order): string
    {
        $html = InvoiceDocument::html($order);
        if ($html === '') {
            return '';
        }
        require_once dirname(__DIR__, 2) . '/lib/autoload.php';
        $temp = trailingslashit(get_temp_dir()) . 'mizzey-mpdf';
        wp_mkdir_p($temp);
        $mpdf = new \Mpdf\Mpdf([
            'mode'             => 'utf-8',
            'format'           => 'A4',
            'tempDir'          => $temp,
            'fontdata'         => ['dejavusans' => ['R' => 'DejaVuSans.ttf', 'B' => 'DejaVuSans-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]],
            'default_font'     => 'dejavusans',
            'directionality'   => InvoiceDocument::rtl($order) ? 'rtl' : 'ltr',
            'autoScriptToLang' => true,
            'autoLangToFont'   => false,
        ]);
        $mpdf->SetTitle(InvoiceNumber::of((int) $order->get_id()));
        $rtl = InvoiceDocument::rtl($order);
        $mpdf->WriteHTML(str_replace(['{start}', '{end}'], $rtl ? ['right', 'left'] : ['left', 'right'], self::STYLE), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

        return (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }
}
