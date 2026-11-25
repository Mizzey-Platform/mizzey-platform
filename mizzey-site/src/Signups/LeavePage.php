<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

use MizzeySite\Marketing\Events;
use MizzeySite\Marketing\TagManager;
use MizzeySite\Storefront\ProductCard;

/**
 * The small page a leave link opens: the site's header and footer around one short panel, in the language of the
 * link's address. It asks for one press ("Leave the list"), then confirms; an unknown link says so. The page is never
 * cached or indexed, and sends no referrer, so the token stays in the link it came in.
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class LeavePage
{
    /**
     * Keeps the leave token on this page: no Tag Manager container and no data layer (Marketing\TagManager,
     * Marketing\Events), and no order attribution, so the page's address, which holds the token, is sent nowhere.
     * The page also sends no referrer. Called before anything is printed, for the page and for its POST.
     */
    public static function quiet(): void
    {
        remove_action('wp_head', [TagManager::class, 'head'], 1);
        remove_action('wp_enqueue_scripts', [Events::class, 'enqueue'], 20);
        add_filter('wc_order_attribution_allow_tracking', '__return_false', 999);
        header(sprintf('%s: %s', 'Referrer-Policy', 'no-referrer'));
    }

    /** @param array<string,mixed>|null $record */
    public static function render(string $state, ?array $record, string $token): void
    {
        $email = (string) ($record['email'] ?? '');
        $home  = (string) apply_filters('wpml_home_url', home_url('/'));
        $mark  = static fn (string $text): string => str_replace('%s', '<bdi dir="ltr">' . esc_html($email) . '</bdi>', esc_html($text));
        if ($state === 'ask') {
            $title = __('Leave the Mizzey list', 'mizzey-site');
            /* translators: %s: the email address on the list. */
            $body   = $mark(__('Press the button to stop the list\'s emails to %s.', 'mizzey-site'));
            $action = sprintf(
                '<form class="mizzey-leave__form" method="post" action="%1$s"><input type="hidden" name="mizzey_leave_confirm" value="1"><button type="submit" class="mizzey-button mizzey-button--primary"><span class="mizzey-button__label">%2$s</span></button></form>',
                esc_url(add_query_arg(SignupRoutes::LEAVE, $token, $home)),
                esc_html__('Leave the list', 'mizzey-site')
            );
        } elseif ($state === 'left') {
            $title = __('You have left the list', 'mizzey-site');
            /* translators: %s: the email address that left. */
            $body   = $mark(__('We will not email %s from this list again. You can sign up again from the home page at any time.', 'mizzey-site'));
            $action = self::home($home);
        } elseif ($state === 'failed') {
            $title  = __('We could not do that just now', 'mizzey-site');
            $body   = esc_html__('Please open the link again in a few minutes.', 'mizzey-site');
            $action = self::home($home);
        } else {
            $title  = __('This link does not work', 'mizzey-site');
            $body   = esc_html__('Open the link from the email again, all of it.', 'mizzey-site');
            $action = self::home($home);
        }
        status_header(['unknown' => 404, 'failed' => 503][$state] ?? 200);
        nocache_headers();
        add_filter('wp_robots', 'wp_robots_no_robots');
        add_filter('document_title_parts', static fn (array $parts): array => ['title' => $title] + $parts);
        if (function_exists('_block_template_viewport_meta_tag')) {
            add_action('wp_head', '_block_template_viewport_meta_tag', 0);
        }

        // The parts are rendered first, as WordPress's own block template canvas does, so their styles reach the head.
        $el     = [ProductCard::class, 'el'];
        $header = function_exists('do_blocks') ? do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->') : '';
        $main   = $el('main', ['id' => 'main', 'class' => 'mizzey-leave', 'data-mizzey-leave' => $state], $el(
            'section',
            ['class' => 'mizzey-leave__panel'],
            $el('h1', ['class' => 'mizzey-leave__title'], esc_html($title)) . $el('p', ['class' => 'mizzey-leave__text'], $body) . $action
        ));
        $footer = function_exists('do_blocks') ? do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->') : '';
        wp_enqueue_style('mizzey-leave', plugins_url('mizzey-site/assets/leave.css'), [], (string) filemtime(dirname(__DIR__, 2) . '/assets/leave.css'));
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class('mizzey-leave-page'); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks"><?php echo $header . $main . $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks, and markup escaped above. ?></div>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    private static function home(string $home): string
    {
        return sprintf('<a class="mizzey-button mizzey-button--secondary" href="%1$s"><span class="mizzey-button__label">%2$s</span></a>', esc_url($home), esc_html__('Back to the home page', 'mizzey-site'));
    }
}
