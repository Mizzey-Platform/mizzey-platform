<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The stock source switch in the administration (D-28).
 *
 * - WooCommerce > Settings > Products > Inventory: the store default (ERP or the store's own stock) and the master
 *   switch, "ERP off". WooCommerce's settings screen checks `manage_woocommerce` and its nonce; each change is logged.
 * - The product and variation editors: the item's choice (follow the default, ERP, own stock), and, for an item on its
 *   own stock, its figure, set with a reason, and its last changes. Saved after WooCommerce's own save, which has checked
 *   the editor's nonce; the user must be able to edit the product. The choice and the figure belong to the stock
 *   identity record, so editing either language changes the one item.
 *
 * WooCommerce's own stock fields stay locked on every item (specs/026): the figure of an item on its own stock is
 * changed here, through the store's stock source, which records it (ADM-73).
 *
 * See specs/049-stock-source-switch, FR-003 and FR-004.
 */
final class StockSourceAdmin
{
    public const FIELD_SOURCE = 'mizzey_stock_source';
    public const FIELD_FIGURE = 'mizzey_own_stock';
    public const FIELD_REASON = 'mizzey_own_stock_reason';

    public static function register(): void
    {
        add_filter('woocommerce_get_settings_products', [self::class, 'settings'], 20, 2);
        add_action('update_option_' . StockMode::DEFAULT_OPTION, [self::class, 'settingChanged'], 10, 3);
        add_action('update_option_' . StockMode::ERP_OFF_OPTION, [self::class, 'settingChanged'], 10, 3);
        add_action('add_option_' . StockMode::DEFAULT_OPTION, [self::class, 'settingAdded'], 10, 2);
        add_action('add_option_' . StockMode::ERP_OFF_OPTION, [self::class, 'settingAdded'], 10, 2);
        add_action('woocommerce_product_options_stock_status', [self::class, 'productPanel'], 20);
        // After the variation's fields, outside WooCommerce's stock fields, which the stock lock hides (specs/026).
        add_action('woocommerce_product_after_variable_attributes', [self::class, 'variationPanel'], 20, 3);
        add_action('woocommerce_process_product_meta', [self::class, 'saveProduct'], 30, 1);
        add_action('woocommerce_save_product_variation', [self::class, 'saveVariation'], 30, 2);
    }

    /**
     * @param mixed $settings
     * @param mixed $section
     * @return mixed
     */
    public static function settings($settings, $section)
    {
        if (! is_array($settings) || $section !== 'inventory') {
            return $settings;
        }
        $settings[] = ['type' => 'title', 'id' => 'mizzey_stock_source', 'title' => __('Stock source', 'mizzey-site'), 'desc' => __('Where each product reads its stock from. A product can also choose for itself, on its Inventory tab.', 'mizzey-site')];
        $settings[] = [
            'type'    => 'select',
            'id'      => StockMode::DEFAULT_OPTION,
            'title'   => __('Store default', 'mizzey-site'),
            'default' => StockMode::ERP,
            'options' => [StockMode::ERP => __('The ERP', 'mizzey-site'), StockMode::STORE => __('The store\'s own stock', 'mizzey-site')],
        ];
        $settings[] = [
            'type'    => 'checkbox',
            'id'      => StockMode::ERP_OFF_OPTION,
            'title'   => __('ERP off', 'mizzey-site'),
            'desc'    => __('Every product reads the store\'s own stock, whatever it is set to. Turn off to return each product to its own setting.', 'mizzey-site'),
            'default' => 'no',
        ];
        $settings[] = ['type' => 'sectionend', 'id' => 'mizzey_stock_source'];

        return $settings;
    }

    /**
     * @param mixed $old
     * @param mixed $value
     * @param mixed $option
     */
    public static function settingChanged($old, $value, $option): void
    {
        if ((string) $old !== (string) $value) {
            self::log(sprintf('setting %s changed from "%s" to "%s"', (string) $option, (string) $old, (string) $value));
        }
    }

    /**
     * @param mixed $option
     * @param mixed $value
     */
    public static function settingAdded($option, $value): void
    {
        self::log(sprintf('setting %s set to "%s"', (string) $option, (string) $value));
    }

    public static function productPanel(): void
    {
        global $product_object;
        if ($product_object instanceof \WC_Product && $product_object->get_id() > 0) {
            self::panel((int) $product_object->get_id(), self::FIELD_SOURCE, self::FIELD_FIGURE, self::FIELD_REASON, 'form-field');
        }
    }

    /**
     * @param mixed $loop
     * @param mixed $data
     * @param mixed $variation
     */
    public static function variationPanel($loop, $data, $variation): void
    {
        if ($variation instanceof \WP_Post) {
            $i = (int) $loop;
            self::panel((int) $variation->ID, self::FIELD_SOURCE . "[{$i}]", self::FIELD_FIGURE . "[{$i}]", self::FIELD_REASON . "[{$i}]", 'form-row form-row-full');
        }
    }

    private static function panel(int $productId, string $sourceName, string $figureName, string $reasonName, string $class): void
    {
        $item = StockIdentity::resolve($productId);
        if ($item === null) {
            return;
        }
        if (StockMode::holder($item)->id !== $item->id) {
            printf('<div class="mizzey-stock-switch"><p class="%s"><em>%s</em></p></div>', esc_attr($class), esc_html__('This variation\'s stock is kept on its product: its stock source and figure are set there.', 'mizzey-site'));

            return;
        }
        $choice  = StockMode::choice($item->id);
        $options = [
            ''               => sprintf(
                /* translators: %s: the store default, "the ERP" or "the store's own stock". */
                __('Store default (%s)', 'mizzey-site'),
                StockMode::storeDefault() === StockMode::STORE ? __('the store\'s own stock', 'mizzey-site') : __('the ERP', 'mizzey-site')
            ),
            StockMode::ERP   => __('The ERP', 'mizzey-site'),
            StockMode::STORE => __('The store\'s own stock', 'mizzey-site'),
        ];
        echo '<div class="mizzey-stock-switch">';
        printf('<p class="%s"><label>%s</label><select name="%s">', esc_attr($class), esc_html__('Stock source', 'mizzey-site'), esc_attr($sourceName));
        foreach ($options as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($choice, $value, false), esc_html($label));
        }
        echo '</select></p>';
        if (StockMode::erpOff()) {
            printf('<p class="%s"><em>%s</em></p>', esc_attr($class), esc_html__('The ERP is switched off: this product reads the store\'s own stock.', 'mizzey-site'));
        }
        if (StockMode::sourceFor($item) === StockMode::STORE) {
            $level = (new StoreStockSource())->level($item);
            printf(
                '<p class="%1$s"><label>%2$s</label><input type="number" min="0" step="1" name="%3$s" value="" placeholder="%4$s"></p>',
                esc_attr($class),
                esc_html__('Set own stock to', 'mizzey-site'),
                esc_attr($figureName),
                esc_attr((string) $level)
            );
            printf(
                '<p class="%1$s"><label>%2$s</label><input type="text" maxlength="%3$d" name="%4$s" value=""></p>',
                esc_attr($class),
                esc_html__('Reason for the change', 'mizzey-site'),
                StoreStockSource::REASON_MAX,
                esc_attr($reasonName)
            );
            self::history($item->id, $class);
        }
        echo '</div>';
    }

    private static function history(int $itemId, string $class): void
    {
        $rows = StoreStockSource::history($itemId, 5);
        if ($rows === []) {
            return;
        }
        printf('<div class="%s"><strong>%s</strong><ul>', esc_attr($class), esc_html__('Last stock changes', 'mizzey-site'));
        foreach ($rows as $row) {
            $user = (int) $row->user_id > 0 ? get_userdata((int) $row->user_id) : false;
            printf(
                '<li>%s</li>',
                esc_html(sprintf(
                    /* translators: 1: date and time, 2: the change, 3: the figure after it, 4: where it came from, 5: who, 6: the reason. */
                    __('%1$s: %2$+d to %3$d (%4$s, %5$s) %6$s', 'mizzey-site'),
                    get_date_from_gmt((string) $row->created_at, get_option('date_format') . ' ' . get_option('time_format')),
                    (int) $row->delta,
                    (int) $row->level_after,
                    (string) $row->origin,
                    $user ? $user->user_login : __('the store', 'mizzey-site'),
                    (string) $row->reason
                ))
            );
        }
        echo '</ul></div>';
    }

    /**
     * After WooCommerce has saved the product and checked the editor's nonce.
     *
     * @param mixed $postId
     */
    public static function saveProduct($postId): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified woocommerce_meta_nonce.
        $source = isset($_POST[self::FIELD_SOURCE]) && ! is_array($_POST[self::FIELD_SOURCE]) ? sanitize_key(wp_unslash($_POST[self::FIELD_SOURCE])) : null;
        $figure = isset($_POST[self::FIELD_FIGURE]) && ! is_array($_POST[self::FIELD_FIGURE]) ? trim((string) wp_unslash($_POST[self::FIELD_FIGURE])) : '';
        $reason = isset($_POST[self::FIELD_REASON]) && ! is_array($_POST[self::FIELD_REASON]) ? sanitize_text_field(wp_unslash($_POST[self::FIELD_REASON])) : '';
        // phpcs:enable
        self::save((int) $postId, $source, $figure, $reason);
    }

    /**
     * @param mixed $variationId
     * @param mixed $loop
     */
    public static function saveVariation($variationId, $loop): void
    {
        $i = (int) $loop;
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified save-variations.
        $source = isset($_POST[self::FIELD_SOURCE][$i]) && ! is_array($_POST[self::FIELD_SOURCE][$i]) ? sanitize_key(wp_unslash($_POST[self::FIELD_SOURCE][$i])) : null;
        $figure = isset($_POST[self::FIELD_FIGURE][$i]) && ! is_array($_POST[self::FIELD_FIGURE][$i]) ? trim((string) wp_unslash($_POST[self::FIELD_FIGURE][$i])) : '';
        $reason = isset($_POST[self::FIELD_REASON][$i]) && ! is_array($_POST[self::FIELD_REASON][$i]) ? sanitize_text_field(wp_unslash($_POST[self::FIELD_REASON][$i])) : '';
        // phpcs:enable
        self::save((int) $variationId, $source, $figure, $reason);
    }

    /**
     * Applies an editor's choice and figure for one item. Returns the error shown to staff, or '' when nothing failed.
     */
    public static function save(int $productId, ?string $source, string $figure, string $reason): string
    {
        if ($productId <= 0 || ! current_user_can('edit_product', $productId)) {
            return '';
        }
        $item = StockIdentity::resolve($productId);
        if ($item === null || StockMode::holder($item)->id !== $item->id) {
            // A variation whose stock its product keeps is set on the product.
            return '';
        }
        $error = '';
        if ($source !== null && in_array($source, ['', StockMode::ERP, StockMode::STORE], true) && $source !== StockMode::choice($item->id)) {
            $was    = StockMode::choice($item->id);
            $fromErp = StockMode::sourceFor($item) === StockMode::ERP;
            // The figure the store last showed, read while the item is still on the ERP.
            $seed = $fromErp ? StockWriteGuard::figure($item->id) : null;
            StockMode::choose($item, $source);
            self::log(sprintf('%s source changed from "%s" to "%s" by user %d', $item->label(), $was === '' ? 'default' : $was, $source === '' ? 'default' : $source, get_current_user_id()));
            if ($fromErp && StockMode::sourceFor($item) === StockMode::STORE) {
                $error = self::seed($item, $seed);
            }
        }
        if ($figure === '') {
            if ($error !== '' && class_exists(\WC_Admin_Meta_Boxes::class)) {
                \WC_Admin_Meta_Boxes::add_error($error);
            }

            return $error;
        }
        if (StockMode::sourceFor($item) !== StockMode::STORE) {
            $error = __('Stock was not changed: this product reads the ERP\'s stock.', 'mizzey-site');
        } elseif (! preg_match('/^\d{1,6}$/', $figure)) {
            $error = __('Stock was not changed: give a whole number from 0 to 999999.', 'mizzey-site');
        } elseif ($reason === '') {
            $error = __('Stock was not changed: give a reason for the change.', 'mizzey-site');
        } else {
            try {
                (new StoreStockSource())->set($item, (int) $figure, 'staff-' . get_current_user_id() . '-' . wp_generate_uuid4(), StoreStockSource::STAFF, $reason, get_current_user_id());
                $error = ''; // The figure given replaces a starting figure that could not be set.
            } catch (StockSourceRefused | StockSourceUnavailable $e) {
                /* translators: %s: why the stock source refused the change. */
                $error = sprintf(__('Stock was not changed: %s.', 'mizzey-site'), $e->getMessage());
            }
        }
        if ($error !== '' && class_exists(\WC_Admin_Meta_Boxes::class)) {
            \WC_Admin_Meta_Boxes::add_error($error);
        }

        return $error;
    }

    /**
     * An item switched from the ERP to its own stock starts from the figure the store last showed for it, recorded as
     * a change; when that figure is not known it starts from nothing, and is not sellable until staff or the feed give
     * one. Returns the error shown to staff, or ''.
     */
    private static function seed(StockItem $item, ?int $figure): string
    {
        $reason = $figure === null
            ? 'switched to own stock (the ERP figure was not known)'
            : 'switched to own stock';
        try {
            (new StoreStockSource())->set($item, max(0, (int) $figure), 'switch-' . get_current_user_id() . '-' . wp_generate_uuid4(), StoreStockSource::STAFF, $reason, get_current_user_id());
        } catch (StockSourceRefused | StockSourceUnavailable $e) {
            /* translators: %s: why the stock source refused the change. */
            return sprintf(__('The product now reads its own stock, but its starting figure was not set: %s. Set it below.', 'mizzey-site'), $e->getMessage());
        }

        return '';
    }

    private static function log(string $line): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->notice('stock source: ' . $line, ['source' => StockGateway::LOG_SOURCE]);
        }
    }
}
