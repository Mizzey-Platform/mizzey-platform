#!/usr/bin/env node
/**
 * The browser matrix against staging: both reading directions, in real browser windows.
 *
 *   node mizzey-site/tests/staging/browser-matrix.cjs [--url http://127.0.0.1:8088] [--only chrome,edge]
 *
 * What a row here is, and is not. The approved matrix (D-09) is current Chrome, Safari, Edge and Firefox on
 * desktop, Chrome on Android and Safari on iOS. This machine is Windows:
 *
 *   chrome   the installed Google Chrome, in a window                    a real browser
 *   edge     the installed Microsoft Edge, in a window                   a real browser
 *   firefox  the Firefox build Playwright ships, in a window             the real engine, not the release channel
 *   webkit   the WebKit build Playwright ships                           NOT Safari. Indicative only
 *   phone    the installed Chrome at a phone-sized viewport              NOT Android Chrome. A layout check only
 *
 * Safari on a Mac, Safari on an iPhone and Chrome on an Android phone can only be exercised on those devices,
 * through the tunnel. This script says so in its output and never reports those rows as exercised.
 *
 * It measures and records. It opens each page, reads what the browser itself computed, and saves a screenshot
 * of the window, as a visitor first sees it, for a person to look at. A layout that is wrong but overflows nothing passes these checks, which is why the
 * screenshots are part of the evidence and not decoration.
 *
 * Playwright is test tooling on the developer's machine and is not a dependency of the site. Point
 * PLAYWRIGHT_DIR at an installed copy, or let the script find the one `npx playwright` has cached.
 */

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

function loadPlaywright() {
	const tried = [];
	const candidates = [process.env.PLAYWRIGHT_DIR, 'playwright'].filter(Boolean);
	const cache = path.join(os.homedir(), 'AppData', 'Local', 'npm-cache', '_npx');
	const unixCache = path.join(os.homedir(), '.npm', '_npx');
	for (const root of [cache, unixCache]) {
		if (fs.existsSync(root)) {
			for (const dir of fs.readdirSync(root)) {
				candidates.push(path.join(root, dir, 'node_modules', 'playwright'));
			}
		}
	}
	// Several versions can be cached, each wanting its own browser builds. Take the one whose builds are present.
	const found = [];
	for (const candidate of candidates) {
		try {
			const pw = require(candidate);
			const version = candidate === 'playwright' ? 'installed' : require(path.join(candidate, 'package.json')).version;
			const builds = ['firefox', 'webkit'].filter((engine) => fs.existsSync(pw[engine].executablePath())).length;
			found.push({ pw, version, builds });
		} catch (error) {
			tried.push(candidate);
		}
	}
	if (found.length) {
		return found.sort((a, b) => b.builds - a.builds)[0];
	}
	console.error('Playwright was not found. Run `npx playwright install` once, or set PLAYWRIGHT_DIR.');
	process.exit(2);
}

const args = process.argv.slice(2);
const option = (name, fallback) => {
	const at = args.indexOf(`--${name}`);
	return at === -1 ? fallback : args[at + 1];
};
const BASE = option('url', 'http://127.0.0.1:8088').replace(/\/$/, '');
const ONLY = option('only', '').split(',').filter(Boolean);
if (!/^http:\/\/(127\.0\.0\.1|localhost):8088$/.test(BASE) && !process.env.MIZZEY_MATRIX_ALLOW_URL) {
	console.error(`refusing: ${BASE} is not the local staging address`);
	process.exit(2);
}

const STAGING = path.resolve(__dirname, '..', '..', '..', '..', 'app-staging');
const stamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15);
const OUT = path.join(STAGING, 'evidence', `browser-matrix-${stamp}`);
fs.mkdirSync(OUT, { recursive: true });

// The same page in each direction. The Arabic address of a page is its English address under /ar.
// Each page names something that must be on it: text in the language of the page, or an element. Without that
// a placeholder passes every structural check, which is exactly what happened on the first run of this file,
// when the store's "coming soon" screen stood in front of every store page.
const PAGES = [
	['home', '/', { text: { en: 'Placeholder', ar: 'Placeholder' } }],
	['shop', '/shop/', { text: { en: 'Sample Product', ar: 'منتج تجريبي' } }],
	['product', '/product/sample-product-01/', { text: { en: 'Sample Product 01', ar: 'منتج تجريبي 01' }, selector: 'form.cart, .wp-block-add-to-cart-form, .wc-block-add-to-cart-form' }],
	['variable-product', '/product/sample-variable-product-01/', { text: { en: 'Sample Variable Product 01', ar: 'منتج تجريبي بخيارات 01' }, selector: 'form.variations_form, .variations' }],
	['cart', '/cart/', { selector: '.wp-block-woocommerce-cart, .wc-block-cart, .woocommerce-cart-form, .cart-empty, .wp-block-woocommerce-empty-cart-block' }],
	['account', '/my-account/', { selector: 'form.woocommerce-form-login, .woocommerce-MyAccount-navigation, form.login' }],
	['about', '/about/', { text: { en: 'Placeholder', ar: 'Placeholder' } }],
];
const DIRECTIONS = [
	['ltr', 'en', ''],
	['rtl', 'ar', '/ar'],
];

const ROWS = [
	{ key: 'chrome', engine: 'chromium', channel: 'chrome', matrixRow: 'Chrome, current, desktop', kind: 'the installed browser' },
	{ key: 'edge', engine: 'chromium', channel: 'msedge', matrixRow: 'Edge, current, desktop', kind: 'the installed browser' },
	{ key: 'firefox', engine: 'firefox', matrixRow: 'Firefox, current, desktop', kind: 'the Firefox build Playwright ships, not the release channel' },
	{ key: 'webkit', engine: 'webkit', matrixRow: 'Safari, current, desktop', kind: 'WebKit build, NOT Safari: indicative only, the row stays unexercised' },
	{ key: 'phone', engine: 'chromium', channel: 'chrome', viewport: { width: 390, height: 844 }, matrixRow: 'Chrome on Android, current', kind: 'desktop Chrome at a phone viewport, NOT Android Chrome: a layout check only, the row stays unexercised' },
];

async function inspect(page, wantDir, wantLang, expect) {
	return page.evaluate(([dir, lang, expect]) => {
		const html = document.documentElement;
		const body = document.body;
		const main = document.querySelector('main') || body;
		const heading = document.querySelector('h1, h2');
		const banner = document.getElementById('mizzey-staging-banner');
		const wide = [];
		// Content a visitor can see that sits past either edge. Elements that are off screen on purpose are left
		// out: text for screen readers, the skip link, a loading bar and the like are positioned, tiny or clipped.
		for (const el of body.querySelectorAll('*')) {
			const box = el.getBoundingClientRect();
			const past = Math.round(Math.max(box.right - html.clientWidth, -box.left));
			if (past <= 1 || box.width < 24 || box.height < 12) continue;
			const style = getComputedStyle(el);
			if (style.position === 'absolute' || style.position === 'fixed' || style.visibility === 'hidden' || style.clip !== 'auto' || style.clipPath !== 'none') continue;
			wide.push(`${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}.${String(el.className).split(' ')[0]} by ${past}px`);
			if (wide.length >= 4) break;
		}
		return {
			htmlDir: html.getAttribute('dir') || 'ltr',
			htmlLang: html.getAttribute('lang'),
			computedDirection: getComputedStyle(body).direction,
			mainTextAlign: getComputedStyle(heading || main).textAlign,
			horizontalOverflow: html.scrollWidth > html.clientWidth + 1,
			scrollWidth: html.scrollWidth,
			clientWidth: html.clientWidth,
			elementsOutsideViewport: wide,
			headingText: heading ? heading.textContent.trim().slice(0, 60) : null,
			stagingBanner: Boolean(banner),
			bodyTextLength: body.innerText.length,
			comingSoonScreen: Boolean(document.querySelector('[class*="coming-soon"]')) || /Great things are on the horizon/.test(body.innerText),
			expectedText: expect.text ? expect.text[lang] : null,
			expectedTextFound: expect.text ? body.innerText.includes(expect.text[lang]) : null,
			expectedElement: expect.selector || null,
			expectedElementFound: expect.selector ? Boolean(document.querySelector(expect.selector)) : null,
			expected: { dir, lang },
		};
	}, [wantDir, wantLang, expect]);
}

(async () => {
	const { pw, version } = loadPlaywright();
	const record = {
		ran: new Date().toISOString(),
		base: BASE,
		playwright: version,
		machine: `${os.type()} ${os.release()}`,
		notExercisedHere: [
			'Safari, current, desktop: needs a Mac',
			'Safari on iOS, current: needs an iPhone or iPad',
			'Chrome on Android, current: needs an Android phone',
		],
		rows: [],
	};

	for (const row of ROWS) {
		if (ONLY.length && !ONLY.includes(row.key)) continue;
		const result = { key: row.key, matrixRow: row.matrixRow, kind: row.kind, pages: [], launched: false };
		record.rows.push(result);
		let browser;
		try {
			browser = await pw[row.engine].launch({ headless: false, channel: row.channel });
			result.launched = true;
			result.browserVersion = browser.version();
		} catch (error) {
			result.error = String(error.message).split('\n')[0];
			continue;
		}
		const context = await browser.newContext({ viewport: row.viewport || { width: 1366, height: 900 } });
		for (const [dir, lang, prefix] of DIRECTIONS) {
			for (const [name, address, expect] of PAGES) {
				const page = await context.newPage();
				const consoleErrors = [];
				const failedRequests = [];
				page.on('console', (message) => message.type() === 'error' && consoleErrors.push(message.text().slice(0, 200)));
				page.on('pageerror', (error) => consoleErrors.push(`pageerror: ${String(error.message).slice(0, 200)}`));
				page.on('response', (response) => {
					if (response.status() >= 400 && response.url().startsWith(BASE)) {
						failedRequests.push(`${response.status()} ${response.url().replace(BASE, '')}`.slice(0, 160));
					}
				});
				const entry = { page: name, direction: dir, url: prefix + address };
				try {
					const response = await page.goto(BASE + prefix + address, { waitUntil: 'load', timeout: 90000 });
					await page.waitForTimeout(600);
					entry.status = response ? response.status() : null;
					entry.landed = page.url().replace(BASE, '');
					Object.assign(entry, await inspect(page, dir, lang, expect));
					entry.consoleErrors = consoleErrors;
					entry.failedRequests = failedRequests;
					entry.screenshot = `${row.key}-${dir}-${name}.png`;
					await page.screenshot({ path: path.join(OUT, entry.screenshot), fullPage: false });
					const problems = [];
					if (entry.status !== 200) problems.push(`status ${entry.status}`);
					if (entry.htmlDir !== dir) problems.push(`html dir is ${entry.htmlDir}`);
					if (!String(entry.htmlLang || '').toLowerCase().startsWith(lang)) problems.push(`html lang is ${entry.htmlLang}`);
					if (entry.computedDirection !== dir) problems.push(`computed direction is ${entry.computedDirection}`);
					if (entry.horizontalOverflow) problems.push(`horizontal overflow ${entry.scrollWidth} > ${entry.clientWidth}`);
					if (entry.elementsOutsideViewport.length) problems.push(`content past the viewport edge: ${entry.elementsOutsideViewport.join(', ')}`);
					if (!entry.stagingBanner) problems.push('no staging banner');
					if (entry.comingSoonScreen) problems.push('the "coming soon" screen is showing instead of the page');
					if (entry.expectedTextFound === false) problems.push(`expected text is not on the page: ${entry.expectedText}`);
					if (entry.expectedElementFound === false) problems.push(`expected element is not on the page: ${entry.expectedElement}`);
					if (consoleErrors.length) problems.push(`${consoleErrors.length} console error(s)`);
					if (failedRequests.length) problems.push(`${failedRequests.length} failed request(s)`);
					entry.problems = problems;
				} catch (error) {
					entry.problems = [`did not load: ${String(error.message).split('\n')[0]}`];
				}
				result.pages.push(entry);
				await page.close();
			}
		}
		await browser.close();
		result.checked = result.pages.length;
		result.withProblems = result.pages.filter((p) => p.problems.length).length;
	}

	fs.writeFileSync(path.join(OUT, 'matrix.json'), JSON.stringify(record, null, 1) + '\n');
	for (const row of record.rows) {
		const state = !row.launched ? `DID NOT START (${row.error})` : `${row.checked - row.withProblems}/${row.checked} pages without a finding`;
		console.log(`${row.key.padEnd(8)} ${String(row.browserVersion || '').padEnd(16)} ${state}  [${row.kind}]`);
		for (const p of row.pages || []) {
			if (p.problems.length) console.log(`           ${p.direction} ${p.page}: ${p.problems.join('; ')}`);
		}
	}
	console.log(`not exercised on this machine: ${record.notExercisedHere.join(' | ')}`);
	console.log(`evidence: ${OUT}`);
})();
