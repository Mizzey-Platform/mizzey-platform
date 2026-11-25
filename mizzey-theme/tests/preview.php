<?php

/**
 * The component preview: every base component of slice 1 on one static page, rendered by the theme's own helpers
 * and styled by the theme's own stylesheets, for comparison with C1-Components and for the browser check of the
 * overlays (mizzey-site/tests/browser/theme-components.cjs). A test fixture, not shipped: tests/ is excluded from
 * the release.
 *
 *     php mizzey-theme/tests/preview.php [ltr|rtl] > preview.html
 *
 * It expects to be served from the theme folder (its stylesheets are linked as ../assets/...) with the
 * Interactivity API module at /wp-interactivity.js; the browser script does both.
 *
 * @package MizzeySite\Tests
 */

declare(strict_types=1);

// A test fixture for the command line only: never served by WordPress or a web server.
if ('cli' !== PHP_SAPI || defined('ABSPATH')) {
    exit;
}

$dir = ($argv[1] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
$ar  = 'rtl' === $dir;

// Arabic words come from the compiled catalogue the site loads, so a missing translation shows as English here too.
$GLOBALS['mizzey_preview_locale'] = $ar ? 'ar' : 'en';

require __DIR__ . '/wp-shim.php';
require dirname(__DIR__) . '/inc/icons.php';
require dirname(__DIR__) . '/inc/components.php';

use MizzeySite\Theme\Components as C;
use function MizzeySite\Theme\Icons\icon;

/** Pick the English or the Arabic sample. */
function t(string $en, string $ar): string
{
    return 'rtl' === ($GLOBALS['preview_dir'] ?? 'ltr') ? $ar : $en;
}

$GLOBALS['preview_dir'] = $dir;

/** One labelled cell, as the board labels every sample. */
function cell(string $caption, string $html, string $force = ''): string
{
    return sprintf(
        '<div class="pv-cell"%s><span class="pv-caption">%s</span>%s</div>',
        '' === $force ? '' : ' data-force="' . esc_attr($force) . '"',
        esc_html($caption),
        $html
    );
}

function row(string ...$cells): string
{
    return '<div class="pv-row">' . implode('', $cells) . '</div>';
}

function section(string $number, string $title, string $html): string
{
    return sprintf('<section class="pv-section" id="s%1$s"><h2 class="pv-title"><span>%1$s</span> %2$s</h2>%3$s</section>', $number, esc_html($title), $html);
}

$add     = t('Add to cart', 'أضف إلى السلة');
$view    = t('View cart', 'عرض السلة');
$clear   = t('Clear filters', 'مسح الفلاتر');
$sample  = static fn (string $en, string $ar): string => t($en, $ar);
$cart_rows = '<p>' . esc_html(t('Two items in your cart.', 'منتجان في سلتك.')) . '</p>'
    . C\field(['id' => 'drawer-coupon', 'label' => t('Coupon code', 'رمز القسيمة')]);

$html  = '';

$html .= section('01', t('Buttons', 'الأزرار'),
    '<h3 class="pv-sub">' . esc_html(t('Regular: 52 high, padding 24', 'عادي: ارتفاع 52')) . '</h3>'
    . row(
        cell(t('Primary, default', 'أساسي'), C\button(['label' => $add])),
        cell(t('hover', 'تمرير'), C\button(['label' => $add]), 'hover'),
        cell(t('pressed, scale 0.97', 'ضغط'), C\button(['label' => $add]), 'active'),
        cell(t('focus, 2 px ring', 'تركيز'), C\button(['label' => $add]), 'focus-visible'),
        cell(t('busy, from 400 ms', 'انتظار'), C\button(['label' => $add, 'busy' => true, 'busy_label' => t('Adding', 'جارٍ الإضافة')])),
        cell(t('done, the tick draws', 'تم'), C\button(['label' => t('Added', 'أُضيف'), 'done' => true])),
        cell(t('disabled', 'معطل'), C\button(['label' => $add, 'disabled' => true]))
    )
    . row(
        cell(t('Secondary, default', 'ثانوي'), C\button(['label' => $view, 'variant' => 'secondary'])),
        cell(t('hover', 'تمرير'), C\button(['label' => $view, 'variant' => 'secondary']), 'hover'),
        cell(t('pressed, scale 0.97', 'ضغط'), C\button(['label' => $view, 'variant' => 'secondary']), 'active'),
        cell(t('focus, 2 px ring', 'تركيز'), C\button(['label' => $view, 'variant' => 'secondary']), 'focus-visible'),
        cell(t('busy, from 400 ms', 'انتظار'), C\button(['label' => $view, 'variant' => 'secondary', 'busy' => true, 'busy_label' => t('Saving', 'جارٍ الحفظ')])),
        cell(t('done, the tick draws', 'تم'), C\button(['label' => t('Saved', 'حُفظ'), 'variant' => 'secondary', 'done' => true])),
        cell(t('disabled', 'معطل'), C\button(['label' => $view, 'variant' => 'secondary', 'disabled' => true]))
    )
    . row(
        cell(t('Text button, default', 'زر نصي'), C\button(['label' => $clear, 'variant' => 'text'])),
        cell(t('Text button, hover', 'تمرير'), C\button(['label' => $clear, 'variant' => 'text']), 'hover'),
        cell(t('Text button, focus', 'تركيز'), C\button(['label' => $clear, 'variant' => 'text']), 'focus-visible'),
        cell(t('Text button, disabled', 'معطل'), C\button(['label' => $clear, 'variant' => 'text', 'disabled' => true])),
        cell(t('With an icon, 20 px, gap 8', 'مع أيقونة'), C\button(['label' => t('Share', 'مشاركة'), 'variant' => 'secondary', 'icon' => 'share'])),
        cell(t('Full width in a 358 column', 'بعرض كامل'), '<div class="pv-358">' . C\button(['label' => $add, 'block' => true]) . '</div>')
    )
    . '<h3 class="pv-sub">' . esc_html(t('Large: 56 high, padding 24, desktop only', 'كبير: ارتفاع 56')) . '</h3>'
    . row(
        cell(t('Primary', 'أساسي'), C\button(['label' => t('Place order', 'تأكيد الطلب'), 'size' => 'large'])),
        cell(t('hover', 'تمرير'), C\button(['label' => t('Place order', 'تأكيد الطلب'), 'size' => 'large']), 'hover'),
        cell(t('busy', 'انتظار'), C\button(['label' => t('Place order', 'تأكيد الطلب'), 'size' => 'large', 'busy' => true, 'busy_label' => t('Placing your order', 'جارٍ تأكيد طلبك')])),
        cell(t('Secondary', 'ثانوي'), C\button(['label' => t('Buy now', 'اشترِ الآن'), 'size' => 'large', 'variant' => 'secondary']))
    )
    . '<h3 class="pv-sub">' . esc_html(t('Compact: 44 high, padding 16, no glow', 'مضغوط: ارتفاع 44')) . '</h3>'
    . row(
        cell(t('Primary', 'أساسي'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact'])),
        cell(t('hover', 'تمرير'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact']), 'hover'),
        cell(t('pressed', 'ضغط'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact']), 'active'),
        cell(t('focus', 'تركيز'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact']), 'focus-visible'),
        cell(t('busy', 'انتظار'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact', 'busy' => true, 'busy_label' => t('Saving', 'جارٍ الحفظ')])),
        cell(t('disabled', 'معطل'), C\button(['label' => t('Accept all', 'قبول الكل'), 'size' => 'compact', 'disabled' => true])),
        cell(t('Secondary', 'ثانوي'), C\button(['label' => t('Decline', 'رفض'), 'size' => 'compact', 'variant' => 'secondary']))
    )
    . '<h3 class="pv-sub">' . esc_html(t('Toolbar buttons and icon buttons', 'أزرار الشريط والأيقونات')) . '</h3>'
    . row(
        cell(t('Sort', 'ترتيب'), C\button(['label' => t('Sort: Relevance', 'الترتيب: الأكثر صلة'), 'variant' => 'secondary', 'size' => 'toolbar', 'icon' => 'sort'])),
        cell(t('Filters, with the count', 'الفلاتر'), C\button(['label' => t('Filters', 'الفلاتر'), 'variant' => 'secondary', 'size' => 'toolbar', 'icon' => 'filter', 'count' => 3])),
        cell(t('hover', 'تمرير'), C\button(['label' => t('Filters', 'الفلاتر'), 'variant' => 'secondary', 'size' => 'toolbar', 'icon' => 'filter']), 'hover'),
        cell(t('Icon button', 'زر أيقونة'), C\button(['label' => t('Wishlist', 'المفضلة'), 'variant' => 'icon', 'icon' => 'heart'])),
        cell(t('hover', 'تمرير'), C\button(['label' => t('Wishlist', 'المفضلة'), 'variant' => 'icon', 'icon' => 'heart']), 'hover'),
        cell(t('pressed', 'ضغط'), C\button(['label' => t('Wishlist', 'المفضلة'), 'variant' => 'icon', 'icon' => 'heart']), 'active'),
        cell(t('focus', 'تركيز'), C\button(['label' => t('Wishlist', 'المفضلة'), 'variant' => 'icon', 'icon' => 'heart']), 'focus-visible'),
        cell(t('Cart, with its count', 'السلة'), C\button(['label' => t('Cart', 'السلة'), 'variant' => 'icon', 'icon' => 'cart', 'count' => 3]))
    )
    . row(
        cell(t('On Indigo: a White button, White icons, a White 2 px focus ring', 'على النيلي'),
            '<div class="mizzey-on-brand pv-band">'
            . C\button(['label' => t('Shop the deals', 'تسوّق العروض'), 'variant' => 'secondary'])
            . '<span data-force="focus-visible">' . C\button(['label' => t('Shop the deals', 'تسوّق العروض'), 'variant' => 'secondary']) . '</span>'
            . C\button(['label' => t('Cart', 'السلة'), 'variant' => 'icon', 'icon' => 'cart'])
            . '<span data-force="hover">' . C\button(['label' => t('Cart', 'السلة'), 'variant' => 'icon', 'icon' => 'cart']) . '</span>'
            . '</div>')
    )
    . row(
        cell(t('Two that fit side by side', 'زران متجاوران'), '<div class="pv-box mizzey-button-pair">' . C\button(['label' => $view, 'variant' => 'secondary']) . C\button(['label' => t('Checkout', 'إتمام الطلب')]) . '</div>'),
        cell(t('Two that do not fit: they stack, full width, primary first', 'زران مكدسان'), '<div class="pv-box mizzey-button-pair mizzey-button-pair--stacked">' . C\button(['label' => t('Continue to payment', 'المتابعة إلى الدفع')]) . C\button(['label' => t('Back to shipping details', 'العودة إلى بيانات الشحن'), 'variant' => 'secondary']) . '</div>')
    )
);

$html .= section('02', t('Fields', 'الحقول'),
    row(
        cell(t('Empty', 'فارغ'), C\field(['id' => 'f-empty', 'label' => t('Full name', 'الاسم الكامل'), 'placeholder' => t('Your full name', 'اسمك الكامل')])),
        cell(t('Focus: Indigo border, the line draws', 'تركيز'), C\field(['id' => 'f-focus', 'label' => t('Full name', 'الاسم الكامل'), 'value' => t('Sample Cust', 'عميل تج')]), 'focus'),
        cell(t('Filled', 'ممتلئ'), C\field(['id' => 'f-filled', 'label' => t('Full name', 'الاسم الكامل'), 'value' => t('Sample Customer', 'عميل تجريبي')])),
        cell(t('Format accepted: green line, the tick', 'صيغة مقبولة'), C\field(['id' => 'f-ok', 'label' => t('Mobile number', 'رقم الموبايل'), 'value' => '+20 100 000 0000', 'type' => 'tel', 'ok' => true]))
    )
    . row(
        cell(t('Error: red border and icon, the words in Ink', 'خطأ'), C\field(['id' => 'f-error', 'label' => t('Mobile number', 'رقم الموبايل'), 'value' => '+20 100 00', 'type' => 'tel', 'error' => t('Enter a mobile number with 10 digits after +20.', 'أدخل رقم موبايل من 10 أرقام بعد +20.')])),
        cell(t('With a helper line', 'مع سطر مساعد'), C\field(['id' => 'f-help', 'label' => t('Email address', 'البريد الإلكتروني'), 'placeholder' => 'name@example.com', 'helper' => t('We send the order confirmation here.', 'نرسل تأكيد الطلب إلى هنا.')])),
        cell(t('Disabled', 'معطل'), C\field(['id' => 'f-off', 'label' => t('Governorate', 'المحافظة'), 'value' => t('Cairo', 'القاهرة'), 'disabled' => true]))
    )
    . row(
        cell(t('Textarea: 106 high, padding 16', 'نص طويل'), C\field(['id' => 'f-notes', 'label' => t('Order notes', 'ملاحظات الطلب'), 'multiline' => true])),
        cell(t('Select, closed', 'قائمة'), C\select(['id' => 's-area', 'label' => t('City or area', 'المدينة أو المنطقة'), 'options' => ['nasr' => t('Nasr City', 'مدينة نصر'), 'helio' => t('Heliopolis', 'مصر الجديدة'), 'maadi' => t('Maadi', 'المعادي'), 'zamalek' => t('Zamalek', 'الزمالك')]])),
        cell(t('Select, open: our own list, level 3, rows 44', 'قائمة مفتوحة'), C\select(['id' => 's-area-open', 'label' => t('City or area', 'المدينة أو المنطقة'), 'options' => ['nasr' => t('Nasr City', 'مدينة نصر'), 'helio' => t('Heliopolis', 'مصر الجديدة'), 'maadi' => t('Maadi', 'المعادي'), 'zamalek' => t('Zamalek', 'الزمالك')]]), 'open')
    )
);

$html .= section('04', t('Choices', 'الاختيارات'),
    row(
        cell(t('Checkbox: 24 box, radius 8', 'مربع اختيار'), '<div class="pv-240">'
            . C\check(['name' => 'brand', 'label' => 'Sports Research', 'count' => 24, 'checked' => true])
            . C\check(['name' => 'brand', 'label' => 'Vital Proteins', 'count' => 18])
            . C\check(['name' => 'brand', 'label' => 'Otiem', 'count' => 0, 'disabled' => true]) . '</div>'),
        cell(t('Radio: plain circles', 'اختيار واحد'), '<div class="pv-280" role="radiogroup" aria-label="' . esc_attr(t('Payment', 'الدفع')) . '">'
            . C\radio(['name' => 'pay', 'value' => 'cod', 'label' => t('Cash on delivery', 'الدفع عند الاستلام'), 'checked' => true])
            . C\radio(['name' => 'pay', 'value' => 'card', 'label' => t('Card', 'بطاقة'), 'hint' => t('You enter the card on a secure page', 'تُدخل البطاقة في صفحة آمنة')]) . '</div>'),
        cell(t('Switch: 52 by 32, thumb 22', 'مفتاح'), '<div class="pv-220">'
            . C\switch_control(['label' => t('In stock only', 'المتوفر فقط'), 'checked' => true])
            . C\switch_control(['label' => t('On sale', 'عليه خصم')]) . '</div>')
    )
);

$html .= section('05', t('Chips, badges and counts', 'الشرائح والشارات'),
    row(
        cell(t('Filter chip', 'شريحة'), C\chip(['label' => t('Multivitamins', 'فيتامينات متعددة'), 'count' => 48])),
        cell(t('hover', 'تمرير'), C\chip(['label' => t('Multivitamins', 'فيتامينات متعددة'), 'count' => 48]), 'hover'),
        cell(t('focus', 'تركيز'), C\chip(['label' => t('Multivitamins', 'فيتامينات متعددة'), 'count' => 48]), 'focus-visible'),
        cell(t('Chosen', 'مختارة'), C\chip(['label' => t('All', 'الكل'), 'count' => 312, 'chosen' => true])),
        cell(t('Applied, with remove', 'مطبقة'), C\chip(['label' => 'Vital Proteins', 'applied' => true])),
        cell(t('Offers: the orange tag', 'العروض'), C\chip(['label' => t('Offers', 'العروض'), 'icon' => 'tag', 'url' => '#offers']))
    )
    . row(
        cell(t('Out of stock', 'نفد'), C\badge(t('Out of stock', 'نفدت الكمية'), 'ink')),
        cell(t('Save', 'وفّر'), C\badge(t('Save 15%', 'وفّر 15%'), 'sale')),
        cell(t('Limited', 'محدود'), C\badge(t('Limited', 'كمية محدودة'))),
        cell(t('Gallery counter, 32', 'عداد'), C\gallery_counter(1, 4)),
        cell(t('Cart count', 'عدد السلة'), '<span class="pv-inline">' . C\counter(3) . C\counter(12) . C\counter(120) . '</span>'),
        cell(t('Corner tag, flush with the card corner', 'وسم الزاوية'), '<span class="pv-card">' . C\corner_tag(t('Best Seller', 'الأكثر مبيعًا')) . '</span>'),
        cell(t('Sale tag in the price line', 'وسم الخصم'), C\sale_tag(20))
    )
);

$html .= section('08', t('Notices', 'الإشعارات'),
    row(
        cell(t('Added to cart', 'أُضيف'), C\notice(['type' => 'cart', 'text' => t('Added to your cart', 'أُضيف إلى سلتك'), 'action' => ['label' => $view, 'url' => '#cart'], 'life' => true])),
        cell(t('Success', 'نجاح'), C\notice(['type' => 'success', 'text' => t('Your address is saved.', 'تم حفظ عنوانك.'), 'life' => true])),
        cell(t('Information', 'معلومة'), C\notice(['type' => 'info', 'text' => t('Orders placed before 2 pm ship the same day.', 'الطلبات قبل الساعة 2 ظهرًا تُشحن في اليوم نفسه.')]))
    )
    . row(
        cell(t('Warning', 'تحذير'), C\notice(['type' => 'warning', 'text' => t('A price in your cart changed since you added it.', 'تغيّر سعر منتج في سلتك منذ أن أضفته.'), 'action' => ['label' => t('Review', 'مراجعة'), 'url' => '#cart']])),
        cell(t('Error: the words stay Ink', 'خطأ'), C\notice(['type' => 'error', 'text' => t('We could not update your cart.', 'تعذّر تحديث سلتك.'), 'action' => ['label' => t('Try again', 'حاول مجددًا'), 'url' => '#retry']]))
    )
    . '<div class="pv-wide">' . C\strip(['text' => t('Free delivery on orders over EGP 1,000', 'توصيل مجاني للطلبات فوق 1,000 ج.م'), 'link' => ['label' => t('See details', 'التفاصيل'), 'url' => '#delivery']])
    . C\sitewide(['text' => t('Deliveries to South Sinai and the Red Sea take one extra day.', 'التوصيل إلى جنوب سيناء والبحر الأحمر يستغرق يومًا إضافيًا.'), 'link' => ['label' => t('Details', 'التفاصيل'), 'url' => '#delivery']])
    . C\sitewide(['type' => 'warning', 'text' => t('Card payments are slow right now. Cash on delivery works as usual.', 'الدفع بالبطاقة بطيء الآن. الدفع عند الاستلام يعمل كالمعتاد.')]) . '</div>'
);

$html .= section('09', t('Loader and skeletons', 'التحميل والهياكل'),
    row(
        cell(t('Loader, Indigo', 'المؤشر'), '<span class="pv-tile">' . C\loader() . '</span>'),
        cell(t('On Indigo: White', 'على النيلي'), '<span class="pv-tile mizzey-on-brand">' . C\loader() . '</span>'),
        cell(t('Still version, with the word', 'ثابت'), '<span class="pv-tile"><span class="mizzey-loader-row">' . C\loader(['still' => true]) . esc_html(t('Loading', 'جارٍ التحميل')) . '</span></span>'),
        cell(t('Card, phone', 'بطاقة'), '<span class="pv-skel-card">' . '<span class="mizzey-well pv-well">' . C\zz() . '</span>' . C\skeleton(100) . C\skeleton(70) . C\skeleton(40) . C\skeleton(56, 'space-4') . '</span>'),
        cell(t('Image well while the photo loads: the breathing ZZ', 'صورة قيد التحميل'), '<span class="pv-well-big">' . C\loading_well() . '</span>')
    )
);

$html .= section('10', t('Empty states and pagination', 'الحالات الفارغة والصفحات'),
    row(
        cell(t('Empty cart: the cart line icon in a 96 disc', 'سلة فارغة'), '<div class="pv-empty">' . C\empty_state(['icon' => 'cart', 'title' => t('Your cart is empty', 'سلتك فارغة'), 'text' => t('Add something you like and it will wait for you here.', 'أضف ما يعجبك وسينتظرك هنا.'), 'action' => ['label' => t('Start shopping', 'ابدأ التسوق')], 'level' => 3]) . '</div>'),
        cell(t('Listing: its own line icon', 'قائمة فارغة'), '<div class="pv-empty">' . C\empty_state(['icon' => 'grid-empty', 'title' => t('No products match these filters', 'لا منتجات تطابق هذه الفلاتر'), 'text' => t('Try removing a filter.', 'جرّب إزالة فلتر.'), 'action' => ['label' => $clear, 'variant' => 'secondary'], 'level' => 3]) . '</div>')
    )
    . row(
        cell(t('First page: Previous unavailable', 'الصفحة الأولى'), C\pagination(1, 24, static fn (int $p): string => '#page-' . $p)),
        cell(t('A middle page', 'صفحة وسطى'), C\pagination(7, 24, static fn (int $p): string => '#page-' . $p))
    )
);

$html .= section('11', t('Drawers, bottom sheets and dialogs', 'الأدراج والنوافذ'),
    row(
        cell(t('Mini cart drawer, from the end edge', 'درج السلة'), C\button(['label' => $view, 'variant' => 'secondary', 'attrs' => C\opens('pv-cart')])),
        cell(t('Phone menu drawer, from the start edge', 'درج القائمة'), C\button(['label' => t('Menu', 'القائمة'), 'variant' => 'icon', 'icon' => 'menu', 'attrs' => C\opens('pv-menu')])),
        cell(t('Bottom sheet, 28 top corners', 'لوحة سفلية'), C\button(['label' => t('Sort by', 'ترتيب حسب'), 'variant' => 'secondary', 'size' => 'toolbar', 'icon' => 'sort', 'attrs' => C\opens('pv-sheet')])),
        cell(t('Dialog: radius 24, level 4', 'نافذة'), C\button(['label' => t('Remove', 'إزالة'), 'variant' => 'secondary', 'attrs' => C\opens('pv-dialog')])),
        cell(t('Popover: radius 20, level 3, rows 44', 'قائمة منبثقة'), '<ul class="mizzey-popover" role="listbox" aria-label="' . esc_attr(t('Sort by', 'ترتيب حسب')) . '">'
            . '<li class="mizzey-select__option" role="option" aria-selected="true">' . esc_html(t('Relevance', 'الأكثر صلة')) . icon('check', ['size' => 20]) . '</li>'
            . '<li class="mizzey-select__option" role="option" aria-selected="false">' . esc_html(t('Price, low to high', 'السعر من الأقل')) . icon('check', ['size' => 20]) . '</li>'
            . '<li class="mizzey-select__option" role="option" aria-selected="false">' . esc_html(t('Newest', 'الأحدث')) . icon('check', ['size' => 20]) . '</li></ul>')
    )
);

$html .= section('13', t('Steps line, tooltips and tabs', 'الخطوات والتلميحات والتبويبات'),
    row(
        cell(t('Steps line: step 2 of 4', 'الخطوة 2 من 4'), '<div class="pv-600">' . C\steps([t('Address', 'العنوان'), t('Shipping', 'الشحن'), t('Payment', 'الدفع'), t('Review', 'المراجعة')], 2) . '</div>'),
        cell(t('Steps line in a 358 column: labels under the discs', 'الخطوات بعرض 358'), '<div class="pv-358" id="pv-steps-358">' . C\steps([t('Address', 'العنوان'), t('Shipping', 'الشحن'), t('Payment', 'الدفع'), t('Review', 'المراجعة')], 2) . '</div>'),
        cell(t('Tooltips: below, and beside', 'تلميحات'), '<span class="pv-tips">'
            . C\tooltip(C\button(['label' => t('Wishlist', 'المفضلة'), 'variant' => 'icon', 'icon' => 'heart']), t('Wishlist', 'المفضلة'))
            . C\tooltip(C\button(['label' => t('Account', 'حسابي'), 'variant' => 'icon', 'icon' => 'user']), t('Account', 'حسابي'), 'start') . '</span>', 'tips')
    )
    . row(
        cell(t('Tabs: 48 high, a 3 px indicator that slides', 'تبويبات'), '<div class="pv-560">' . C\tabs('pv-tabs', [
            ['label' => t('Details', 'التفاصيل'), 'panel' => '<p>' . esc_html(t('Details panel.', 'لوحة التفاصيل.')) . '</p>'],
            ['label' => t('Specifications', 'المواصفات'), 'panel' => '<p>' . esc_html(t('Specifications panel.', 'لوحة المواصفات.')) . '</p>'],
            ['label' => t('Reviews (128)', 'التقييمات (128)'), 'panel' => '<p>' . esc_html(t('Reviews panel.', 'لوحة التقييمات.')) . '</p>'],
            ['label' => t('Delivery', 'التوصيل'), 'panel' => '<p>' . esc_html(t('Delivery panel.', 'لوحة التوصيل.')) . '</p>'],
        ], 0, t('Product information', 'معلومات المنتج')) . '</div>')
    )
);

$overlays = C\overlay(['id' => 'pv-cart', 'kind' => 'drawer-end', 'title' => t('Your cart', 'سلتك'), 'body' => $cart_rows, 'footer' => C\button(['label' => t('Checkout', 'إتمام الطلب'), 'block' => true])])
    . C\overlay(['id' => 'pv-menu', 'kind' => 'drawer-start', 'title' => t('Menu', 'القائمة'), 'body' => C\field(['id' => 'pv-menu-search', 'label' => t('Search', 'بحث')]) . '<p><a href="#beauty">' . esc_html(t('Beauty', 'الجمال')) . '</a></p><p><a href="#health">' . esc_html(t('Health & Household', 'الصحة والمنزل')) . '</a></p>'])
    . C\overlay(['id' => 'pv-sheet', 'kind' => 'sheet', 'title' => t('Sort by', 'ترتيب حسب'), 'body' => '<div role="radiogroup" aria-label="' . esc_attr(t('Sort by', 'ترتيب حسب')) . '">' . C\radio(['name' => 'sort', 'value' => 'rel', 'label' => t('Relevance', 'الأكثر صلة'), 'checked' => true]) . C\radio(['name' => 'sort', 'value' => 'new', 'label' => t('Newest', 'الأحدث')]) . '</div>'])
    . C\overlay(['id' => 'pv-dialog', 'kind' => 'dialog', 'title' => t('Remove this item?', 'إزالة هذا المنتج؟'), 'body' => esc_html(t('You can add it again at any time.', 'يمكنك إضافته مرة أخرى في أي وقت.')),
        'footer' => '<div class="mizzey-button-pair">' . C\button(['label' => t('Keep it', 'إبقاء'), 'variant' => 'secondary', 'attrs' => ['data-wp-interactive' => 'mizzey/ui', 'data-wp-on--click' => 'actions.close']]) . C\button(['label' => t('Remove', 'إزالة'), 'attrs' => ['data-wp-interactive' => 'mizzey/ui', 'data-wp-on--click' => 'actions.close']]) . '</div>']);

?><!doctype html>
<html lang="<?php echo $ar ? 'ar' : 'en'; ?>" dir="<?php echo $dir; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Mizzey components preview</title>
<?php
$fonts = json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/fonts/fonts.json'), true);
echo '<style>';
foreach ($fonts['faces'] as $face) {
    printf(
        '@font-face{font-family:"%s";src:url("../assets/fonts/%s") format("woff2");font-weight:%s;font-style:normal;font-display:swap;unicode-range:%s}',
        $face['fontFamily'],
        $face['file'],
        $face['fontWeight'],
        $face['unicodeRange']
    );
}
echo '</style>';
?>
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/fonts.css">
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="preview.css">
<script type="importmap">{"imports":{"@wordpress/interactivity":"/wp-interactivity.js"}}</script>
<script type="module" src="../assets/js/ui.js"></script>
</head>
<body>
<main class="pv-page">
<p class="pv-kicker">MIZZEY, SLICE 1, THE BASE COMPONENTS</p>
<h1 class="pv-h1"><?php echo esc_html(t('Components', 'المكوّنات')); ?></h1>
<?php echo $html; ?>
</main>
<?php echo $overlays; ?>
</body>
</html>
