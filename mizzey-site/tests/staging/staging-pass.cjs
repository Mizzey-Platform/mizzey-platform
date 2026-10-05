#!/usr/bin/env node
/**
 * A staging pass in a real browser window, for the features that were waiting for staging:
 *
 *   node mizzey-site/tests/staging/staging-pass.cjs ia      #242, the pages and addresses in both languages
 *   node mizzey-site/tests/staging/staging-pass.cjs stock   #246, the stock report as a signed-in member of staff
 *
 * It does what a tester at the keyboard would do: opens the installed Chrome, visits each address, signs in
 * where a screen needs it, reads what is on the screen and saves a screenshot. It is driven by a script, so it
 * is repeatable, and it is NOT a person looking: each run says so in its record. It reads and never edits.
 *
 * It signs in with the synthetic staging accounts in ../app-staging/CREDENTIALS.json, which exist only on this
 * machine, and it refuses any address but the local staging one.
 */

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const BASE = 'http://127.0.0.1:8088';
const STAGING = path.resolve(__dirname, '..', '..', '..', '..', 'app-staging');
const WP = path.join(STAGING, 'wp');
const mode = process.argv[2];
if (!['ia', 'stock'].includes(mode)) {
	console.error('usage: staging-pass.cjs ia|stock');
	process.exit(2);
}
const stamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15);
const OUT = path.join(STAGING, 'evidence', `staging-pass-${mode}-${stamp}`);
fs.mkdirSync(OUT, { recursive: true });

function loadPlaywright() {
	const candidates = [process.env.PLAYWRIGHT_DIR, 'playwright'].filter(Boolean);
	for (const root of [path.join(os.homedir(), 'AppData', 'Local', 'npm-cache', '_npx'), path.join(os.homedir(), '.npm', '_npx')]) {
		if (fs.existsSync(root)) {
			for (const dir of fs.readdirSync(root)) candidates.push(path.join(root, dir, 'node_modules', 'playwright'));
		}
	}
	for (const candidate of candidates) {
		try {
			return require(candidate);
		} catch (error) {
			// try the next
		}
	}
	console.error('Playwright was not found. Run `npx playwright install` once, or set PLAYWRIGHT_DIR.');
	process.exit(2);
}

/**
 * Run PHP in the staging runtime and return its last line. The code goes through a file: the WP-CLI launcher on
 * Windows is a batch file, and a command line holding PHP operators does not survive it.
 */
function wp(code) {
	const file = path.join(OUT, 'query.php');
	fs.writeFileSync(file, ['<?php', code, ''].join(os.EOL));
	try {
		const out = execFileSync(process.platform === 'win32' ? 'wp.bat' : 'wp', [`--path=${WP}`, '--skip-themes', 'eval-file', file], { encoding: 'utf8', shell: process.platform === 'win32' });
		return out.trim().split(/\r?\n/).pop();
	} finally {
		fs.rmSync(file, { force: true });
	}
}

const credentials = JSON.parse(fs.readFileSync(path.join(STAGING, 'CREDENTIALS.json'), 'utf8'));

async function signIn(page, account) {
	await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'load' });
	await page.fill('#user_login', credentials[account].user);
	await page.fill('#user_pass', credentials[account].password);
	await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#wp-submit')]);
	return !page.url().includes('wp-login.php');
}

async function head(page) {
	return page.evaluate(() => {
		const link = (rel) => [...document.querySelectorAll(`link[rel="${rel}"]`)];
		return {
			lang: document.documentElement.getAttribute('lang'),
			dir: document.documentElement.getAttribute('dir') || 'ltr',
			title: document.title,
			canonical: link('canonical').map((l) => l.href)[0] || null,
			hreflang: Object.fromEntries(link('alternate').filter((l) => l.hreflang).map((l) => [l.hreflang, l.href])),
			description: (document.querySelector('meta[name="description"]') || {}).content || null,
			banner: Boolean(document.getElementById('mizzey-staging-banner')),
			// A placeholder standing in front of the page: the commerce "coming soon" page, marked by its meta tag in
			// any language, or a WordPress error or maintenance page, whose body carries the id error-page.
			comingSoon: Boolean(document.querySelector('meta[name="woo-coming-soon-page"]')) || /Great things are on the horizon/.test(document.body.innerText),
			errorPage: document.body.id === 'error-page',
			h1: (document.querySelector('h1') || {}).innerText || null,
		};
	});
}

async function passIa(browser, record) {
	const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
	const page = await context.newPage();
	// row, page, English address, Arabic address, and for an archive the term it must show in each language. From
	// specs/003-information-architecture-urls/url-map.md. An archive that answers 200 is not yet the term's archive:
	// the collection archive answered 200 with nothing on it until 5 October 2026.
	const pages = [
		['IA-01', 'Home', '/', '/ar/'],
		['IA-02', 'Products', '/shop/', '/ar/shop/'],
		['IA-08', 'Cart', '/cart/', '/ar/cart/'],
		['IA-11', 'Wishlist', '/wishlist/', '/ar/wishlist/'],
		['IA-17', 'My Account', '/my-account/', '/ar/my-account/'],
		['IA-21', 'Tracking', '/track-your-order/', '/ar/track-your-order/'],
		['IA-24', 'About', '/about/', '/ar/about/'],
		['IA-25', 'Contact us', '/contact-us/', '/ar/contact-us/'],
		['IA-26', 'Help and FAQ', '/help/', '/ar/help/'],
		['IA-27', 'Authenticity guarantee', '/authenticity-guarantee/', '/ar/authenticity-guarantee/'],
		['IA-28', 'Shipping policy', '/shipping-policy/', '/ar/shipping-policy/'],
		['IA-29', 'Return and refund policy', '/returns-and-refunds/', '/ar/returns-and-refunds/'],
		['IA-30', 'Privacy policy', '/privacy-policy/', '/ar/privacy-policy/'],
		['IA-31', 'Terms and conditions', '/terms-and-conditions/', '/ar/terms-and-conditions/'],
		['IA-32', 'Offers and campaigns', '/offers/', '/ar/offers/'],
		['IA-07', 'Product', '/product/sample-product-01/', '/ar/product/sample-product-01/'],
		['IA-03', 'Category', '/product-category/sample-care/', '/ar/product-category/sample-care-ar/', 'Sample Care', 'عناية تجريبية'],
		['IA-05', 'Brand', '/brand/sample-brand-north/', '/ar/brand/sample-brand-north-ar/', 'Sample Brand North', 'علامة تجريبية شمال'],
		['IA-04', 'Collection', '/collection/sample-collection/', '/ar/collection/sample-collection-ar/', 'Sample Collection', 'مجموعة تجريبية'],
		['IA-06', 'Search results', '/?s=Sample', '/ar/?s=Sample'],
		['IA-16', 'Lost password', '/my-account/lost-password/', '/ar/my-account/lost-password-ar/'],
	];
	for (const [row, name, en, ar, termEn, termAr] of pages) {
		for (const [lang, dir, address, term] of [['en', 'ltr', en, termEn], ['ar', 'rtl', ar, termAr]]) {
			const entry = { row, page: name, language: lang, address };
			const response = await page.goto(BASE + address, { waitUntil: 'load', timeout: 90000 });
			entry.status = response.status();
			entry.landed = page.url().replace(BASE, '');
			Object.assign(entry, await head(page));
			const problems = [];
			if (entry.status !== 200) problems.push(`status ${entry.status}`);
			if (entry.landed !== address) problems.push(`landed on ${entry.landed}`);
			if (!String(entry.lang || '').toLowerCase().startsWith(lang)) problems.push(`lang is ${entry.lang}`);
			if (entry.dir !== dir) problems.push(`dir is ${entry.dir}`);
			if (entry.comingSoon) problems.push('the "coming soon" screen is showing');
			if (entry.errorPage) problems.push('a WordPress error or maintenance page is showing');
			if (!entry.banner) problems.push('no staging banner');
			if (term) {
				entry.showsTerm = await page.evaluate((text) => document.body.innerText.includes(text), term);
				entry.productsListed = await page.locator('li.product, .wc-block-product, .wp-block-post.product').count();
				if (!entry.showsTerm) problems.push(`the archive does not show its term, "${term}"`);
				if (!entry.productsListed) problems.push('the archive lists no product');
			}
			const isSearch = address.includes('?s=');
			// An account endpoint is a view of the account page, and its canonical form is that page.
			const canonicalWanted = BASE + (row === 'IA-16' ? address.replace(/lost-password(-ar)?\/$/, '') : address);
			if (!isSearch && entry.canonical !== canonicalWanted) problems.push(`canonical is ${decodeURI(entry.canonical || 'missing')}, expected ${canonicalWanted}`);
			for (const [code, target] of Object.entries(isSearch ? {} : entry.hreflang)) {
				const answer = await context.request.get(target, { maxRedirects: 0 });
				if (answer.status() !== 200) problems.push(`hreflang ${code} points at ${decodeURI(target.replace(BASE, ''))}, which answers ${answer.status()}`);
			}
			if (!isSearch && !(entry.hreflang.en && entry.hreflang.ar)) problems.push(`hreflang is ${JSON.stringify(entry.hreflang)}`);
			if (/[?&](page_id|p)=/.test(entry.landed)) problems.push('not a clean address');
			entry.problems = problems;
			entry.screenshot = `${row}-${lang}.png`;
			await page.screenshot({ path: path.join(OUT, entry.screenshot) });
			record.checks.push(entry);
		}
	}

	// The checkout with an empty cart answers with the cart, and stays in its own language.
	for (const [lang, address, expected] of [['en', '/checkout/', '/cart/'], ['ar', '/ar/checkout/', '/ar/cart/']]) {
		await page.goto(BASE + address, { waitUntil: 'load' });
		const landed = page.url().replace(BASE, '');
		record.checks.push({ row: 'IA-09', page: 'Checkout, empty cart', language: lang, address, landed,
			problems: landed === expected ? [] : [`landed on ${landed}, expected ${expected}`] });
	}
	// An address that names nothing is a 404 in its own language, not the home page.
	for (const [lang, address] of [['en', '/no-such-page-here/'], ['ar', '/ar/no-such-page-here/']]) {
		const response = await page.goto(BASE + address, { waitUntil: 'load' });
		const info = await head(page);
		record.checks.push({ row: 'NFR-03', page: 'Unknown address', language: lang, address, status: response.status(), lang: info.lang,
			problems: [response.status() === 404 ? null : `status ${response.status()}`, String(info.lang || '').toLowerCase().startsWith(lang) ? null : `lang is ${info.lang}`].filter(Boolean) });
	}
	// The sitemap and robots.txt, as a crawler meets them.
	const robots = await (await page.goto(`${BASE}/robots.txt`)).text();
	record.checks.push({ row: 'NFR-03', page: 'robots.txt', address: '/robots.txt', hasSitemapLine: /Sitemap:/i.test(robots),
		note: 'On staging every response also carries X-Robots-Tag noindex, by design.',
		problems: /Sitemap:/i.test(robots) ? [] : ['no Sitemap line'] });
	// Fetched as a crawler fetches them. Opened in a window, the browser applies the sitemap's stylesheet and the
	// page no longer holds the XML, which is how the first run of this check read an empty sitemap.
	const index = await (await context.request.get(`${BASE}/wp-sitemap.xml`)).text();
	const pagesMap = await (await context.request.get(`${BASE}/wp-sitemap-posts-page-1.xml`)).text();
	await page.goto(`${BASE}/wp-sitemap.xml`, { waitUntil: 'load' });
	await page.screenshot({ path: path.join(OUT, 'sitemap-index.png') });
	const locs = [...pagesMap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replace(BASE, ''));
	const arabic = locs.filter((l) => l.startsWith('/ar/')).length;
	record.checks.push({ row: 'NFR-03', page: 'Sitemap', address: '/wp-sitemap-posts-page-1.xml', sitemaps: (index.match(/<sitemap>/g) || []).length,
		pageAddresses: locs.length, arabicAddresses: arabic, englishAddresses: locs.length - arabic,
		problems: arabic > 0 && locs.length - arabic > 0 ? [] : ['the page sitemap does not list both languages'] });

	// Signed in as the demonstration customer: the account screens are endpoints of one page, in both languages.
	record.signedInAs = 'demo_customer (synthetic)';
	const ok = await signIn(page, 'demo_customer');
	record.checks.push({ row: 'IA-17', page: 'Sign in', address: '/wp-login.php', problems: ok ? [] : ['the demonstration customer could not sign in'] });
	for (const [row, name, en, ar] of [
		['IA-19', 'Orders', '/my-account/orders/', '/ar/my-account/orders-ar/'],
		['IA-18', 'Addresses', '/my-account/edit-address/', '/ar/my-account/edit-address-ar/'],
	]) {
		for (const [lang, address] of [['en', en], ['ar', ar]]) {
			const response = await page.goto(BASE + address, { waitUntil: 'load' });
			const info = await head(page);
			const orders = await page.locator('table.woocommerce-orders-table tbody tr, .woocommerce-orders-table__row').count();
			const entry = { row, page: name, language: lang, address, status: response.status(), landed: page.url().replace(BASE, ''), lang: info.lang, ordersListed: row === 'IA-19' ? orders : undefined };
			entry.problems = [response.status() === 200 ? null : `status ${response.status()}`, entry.landed === address ? null : `landed on ${entry.landed}`,
				String(info.lang || '').toLowerCase().startsWith(lang) ? null : `lang is ${info.lang}`, row === 'IA-19' && orders === 0 ? 'no order is listed' : null].filter(Boolean);
			entry.screenshot = `${row}-${lang}-signed-in.png`;
			await page.screenshot({ path: path.join(OUT, entry.screenshot) });
			record.checks.push(entry);
		}
	}
	// IA-20: one order of that customer, opened from the list.
	await page.goto(`${BASE}/my-account/orders/`, { waitUntil: 'load' });
	const first = page.locator('a[href*="/view-order/"]').first();
	if (await first.count()) {
		await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), first.click()]);
		const landed = page.url().replace(BASE, '');
		await page.screenshot({ path: path.join(OUT, 'IA-20-en-signed-in.png') });
		record.checks.push({ row: 'IA-20', page: 'Order detail', language: 'en', landed, problems: /\/my-account\/view-order\/\d+\/$/.test(landed) ? [] : [`landed on ${landed}`] });
	} else {
		record.checks.push({ row: 'IA-20', page: 'Order detail', language: 'en', problems: ['no order link to open'] });
	}
	await context.close();
}

async function passStock(browser, record) {
	// What the store holds, read from the database through WooCommerce: one entry per physical item, however many
	// language records describe it. This is the answer the screen is compared with.
	const truth = JSON.parse(wp(
		"global $wpdb; $out = array('lowstock' => array(), 'outofstock' => array()); $seen = array(); "
		+ "$ids = $wpdb->get_col(\"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status = 'publish' ORDER BY ID\"); "
		+ "foreach ($ids as $id) { $p = wc_get_product($id); if (!$p || !$p->managing_stock() || $p->is_type('variable')) continue; $sku = $p->get_sku(); if (isset($seen[$sku])) continue; $seen[$sku] = 1; "
		+ "$q = (int) $p->get_stock_quantity(); $low = (int) wc_get_low_stock_amount($p); if ($q <= 0) $out['outofstock'][] = $sku; elseif ($q <= $low) $out['lowstock'][] = $sku; } "
		+ 'sort($out["lowstock"]); sort($out["outofstock"]); echo wp_json_encode($out);'
	));
	record.expected = truth;

	const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, acceptDownloads: true });
	const page = await context.newPage();
	record.signedInAs = 'staging_admin (synthetic)';
	if (!(await signIn(page, 'staging_admin'))) {
		record.checks.push({ check: 'sign in', problems: ['the staging administrator could not sign in'] });
		return;
	}
	for (const type of ['lowstock', 'outofstock']) {
		for (const context_ of ['en', 'ar', 'all']) {
			const address = `/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fstock&type=${type}&per_page=100&lang=${context_}`;
			// What the screen itself asks the store for, and what it is given: the request the report makes, with its
			// address, the total the store reports, and the SKUs in the answer.
			const asked = [];
			const listen = async (response) => {
				const url = response.url();
				if (!/wc-analytics(\/|%2F)reports(\/|%2F)stock(\?|&|$)/.test(url)) return;
				try {
					const body = await response.json();
					asked.push({ request: decodeURIComponent(url.replace(BASE, '')).slice(0, 260), total: response.headers()['x-wp-total'], skus: Array.isArray(body) ? body.map((r) => r.sku).sort() : body.code });
				} catch (error) {
					// not a list answer
				}
			};
			page.on('response', listen);
			await page.goto(BASE + address, { waitUntil: 'networkidle', timeout: 120000 });
			await page.waitForSelector('.woocommerce-table__table tbody tr, .woocommerce-table__empty-item', { timeout: 120000 });
			await page.waitForTimeout(2500);
			page.off('response', listen);
			const read = await page.evaluate(() => {
				const rows = [...document.querySelectorAll('.woocommerce-table__table tbody tr')].map((tr) => [...tr.querySelectorAll('th, td')].map((c) => c.innerText.trim()));
				const headers = [...document.querySelectorAll('.woocommerce-table__table thead th')].map((c) => c.innerText.trim().split('\n')[0]);
				const summary = [...document.querySelectorAll('.woocommerce-table__summary li, .woocommerce-table__summary-item')].map((li) => li.innerText.replace(/\s+/g, ' ').trim());
				return { headers, rows: rows.filter((r) => r.length > 1), summary, adminLang: document.documentElement.getAttribute('lang'), banner: Boolean(document.getElementById('wp-admin-bar-mizzey-staging')) };
			});
			// The SKU is found by its shape, not by its column position: the table's header and body cells do not line
			// up one to one in the page's markup, which is how the first run of this check read every SKU as empty.
			const skus = read.rows.map((r) => r.find((cell) => /^DEMO-[A-Z0-9-]+$/.test(cell))).filter(Boolean).sort();
			const totals = Object.fromEntries(read.summary.map((t) => { const m = t.match(/^(\d+)\s*(.+)$/); return m ? [m[2], Number(m[1])] : [t, null]; }));
			const label = type === 'lowstock' ? 'Low stock' : 'Out of stock';
			const expected = truth[type];
			const repeated = skus.filter((s, i) => skus.indexOf(s) !== i);
			const missing = expected.filter((s) => !skus.includes(s));
			const extra = skus.filter((s) => !expected.includes(s));
			const entry = { check: `stock report, ${type}, admin language context ${context_}`, address, adminLang: read.adminLang, headers: read.headers,
				lines: skus.length, skus, summary: read.summary, summaryFigure: totals[label], marker: read.banner, asked };
			entry.problems = [
				repeated.length ? `listed more than once: ${[...new Set(repeated)].join(', ')}` : null,
				missing.length ? `not listed: ${missing.join(', ')}` : null,
				extra.length ? `listed and not expected: ${extra.join(', ')}` : null,
				String(read.adminLang || '').toLowerCase().startsWith('en') ? null : `the admin screen is in ${read.adminLang}, not English (ADM-159)`,
				totals[label] !== undefined && totals[label] !== skus.length ? `the summary under the table reads "${totals[label]} ${label}" while the list holds ${skus.length} lines` : null,
				read.banner ? null : 'no staging marker on the screen',
			].filter(Boolean);
			entry.screenshot = `stock-${type}-${context_}.png`;
			await page.screenshot({ path: path.join(OUT, entry.screenshot), fullPage: true });
			record.checks.push(entry);
		}
	}
	// The report's own export, as the operator downloads it.
	await page.goto(`${BASE}/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fstock&type=lowstock&per_page=100`, { waitUntil: 'load' });
	await page.waitForSelector('.woocommerce-table__table tbody tr', { timeout: 120000 });
	await page.waitForTimeout(1500);
	const button = page.getByRole('button', { name: /Download/ });
	await button.first().waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});
	if (await button.count()) {
		const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60000 }), button.first().click()]);
		const file = path.join(OUT, 'stock-lowstock-export.csv');
		await download.saveAs(file);
		const lines = fs.readFileSync(file, 'utf8').trim().split(/\r?\n/);
		const exported = lines.slice(1).map((l) => (l.match(/DEMO-[A-Z0-9-]+/) || [''])[0]).filter(Boolean).sort();
		const same = JSON.stringify(exported) === JSON.stringify(truth.lowstock);
		record.checks.push({ check: 'stock report export, lowstock', file: 'stock-lowstock-export.csv', dataLines: lines.length - 1, skus: exported,
			problems: same ? [] : [`the export lists ${exported.join(', ')}; expected ${truth.lowstock.join(', ')}`] });
	} else {
		record.checks.push({ check: 'stock report export, lowstock', problems: ['no download button on the screen'] });
	}
	await context.close();
}

(async () => {
	const pw = loadPlaywright();
	const browser = await pw.chromium.launch({ headless: false, channel: 'chrome' });
	const build = JSON.parse(fs.readFileSync(path.join(STAGING, 'BUILD.json'), 'utf8'));
	const record = { ran: new Date().toISOString(), pass: mode, base: BASE, browser: `Google Chrome ${browser.version()}, in a window`,
		how: 'A scripted pass in a real browser. Not a person looking at the screens.', build, checks: [] };
	try {
		await (mode === 'ia' ? passIa : passStock)(browser, record);
	} finally {
		await browser.close();
	}
	record.checked = record.checks.length;
	record.withProblems = record.checks.filter((c) => c.problems.length).length;
	fs.writeFileSync(path.join(OUT, 'pass.json'), JSON.stringify(record, null, 1) + '\n');
	for (const c of record.checks) {
		if (c.problems.length) console.log(`FINDING  ${c.row || ''} ${c.page || c.check} ${c.language || ''}: ${c.problems.join('; ')}`);
	}
	console.log(`${mode}: ${record.checked - record.withProblems}/${record.checked} checks without a finding`);
	console.log(`evidence: ${OUT}`);
})();
