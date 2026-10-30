#!/usr/bin/env node
/**
 * AUTH-15 in a real browser (specs/009-sign-in-protection, FR-002 and AC-2). Driven by t49, which prepares the
 * runtime and passes a JSON plan as the only argument; prints one JSON object of results.
 *
 * It submits the three account-creating forms as a person would, waiting longer than the check's minimum age:
 * the account page's registration in English and Arabic, the block checkout with account creation (capturing the
 * Store API request to show the check travels in `extensions.mizzey-signup`), and the order-received account offer.
 * It then follows the sign-in flows with CoreX's limit on: the links a page prints, wp-login.php, CoreX's address,
 * the account page's sign-in, WP 2FA's challenge with a computed code, lost password, the reset link and sign-out.
 * Whether each account exists is judged by t49 on the server, not here.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site: point PLAYWRIGHT_DIR at an
 * installed copy, or let the script find the one `npx playwright` has cached, as browser-matrix.cjs does.
 */

const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

function loadPlaywright() {
	const candidates = [process.env.PLAYWRIGHT_DIR, 'playwright'].filter(Boolean);
	for (const root of [path.join(os.homedir(), 'AppData', 'Local', 'npm-cache', '_npx'), path.join(os.homedir(), '.npm', '_npx')]) {
		if (fs.existsSync(root)) {
			for (const dir of fs.readdirSync(root)) {
				candidates.push(path.join(root, dir, 'node_modules', 'playwright'));
			}
		}
	}
	for (const candidate of candidates) {
		try {
			const pw = require(candidate);
			if (fs.existsSync(pw.chromium.executablePath())) {
				return pw;
			}
		} catch (error) {
			// Try the next one.
		}
	}
	console.log(JSON.stringify({ error: 'Playwright with a Chromium build was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' }));
	process.exit(2);
}

/** RFC 6238 code for a base32 secret (SHA-1, six digits, 30 seconds), computed independently of WP 2FA. */
function totp(base32) {
	const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	let bits = '';
	for (const char of base32.replace(/=+$/, '').toUpperCase()) {
		bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
	}
	const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
	const counter = Buffer.alloc(8);
	counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30)));
	const hmac = crypto.createHmac('sha1', key).update(counter).digest();
	const offset = hmac[hmac.length - 1] & 0xf;
	const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1000000;
	return String(code).padStart(6, '0');
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Click something that submits or navigates, and wait for the next page to load. */
async function clickAndLoad(page, locator) {
	await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {}), locator.click()]);
}

async function registerOnAccountPage(browser, plan, lang) {
	const page = await (await browser.newContext()).newPage();
	const row = { route: 'account', lang, email: plan.account[lang].email };
	await page.goto(plan.account[lang].url, { waitUntil: 'domcontentloaded' });
	row.tokenField = await page.locator('form.register input[name="mizzey_signup_token"]').count();
	row.trapField = await page.locator('form.register input[name="mizzey_signup_extra"]').count();
	row.trapVisible = row.trapField ? await page.locator('form.register input[name="mizzey_signup_extra"]').isVisible() : null;
	await page.fill('form.register input[name="email"]', row.email);
	if (await page.locator('form.register input[name="password"]').count()) {
		await page.fill('form.register input[name="password"]', plan.password);
	}
	await wait(4000);
	// Keyboard submission, with Enter in the password field. A pointer click on the button submitted nothing in the
	// observed headless run; this case does not establish that pointer submission works.
	await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {}), page.press('form.register input[name="password"]', 'Enter')]);
	row.landed = page.url();
	row.notice = (await page.locator('.woocommerce-error, .woocommerce-notices-wrapper').allInnerTexts()).join(' ').trim().slice(0, 300);
	await page.context().close();
	return row;
}

async function checkoutWithAccount(browser, plan) {
	const context = await browser.newContext();
	const page = await context.newPage();
	const row = { route: 'checkout', email: plan.checkout.email };
	await page.goto(plan.checkout.addToCart, { waitUntil: 'domcontentloaded' });
	await page.goto(plan.checkout.url, { waitUntil: 'networkidle' });
	page.on('request', (request) => {
		if (request.method() === 'POST' && /\/wc\/store\/v1\/checkout/.test(request.url())) {
			try {
				const body = JSON.parse(request.postData() || '{}');
				row.payloadHasCheck = Boolean(body.extensions && body.extensions['mizzey-signup'] && body.extensions['mizzey-signup'].token);
				row.payloadTrapEmpty = body.extensions && body.extensions['mizzey-signup'] ? body.extensions['mizzey-signup'].trap === '' : null;
			} catch (error) {
				row.payloadError = String(error);
			}
		}
	});
	page.on('response', (response) => {
		if (response.request().method() === 'POST' && /\/wc\/store\/v1\/checkout/.test(response.url())) {
			row.checkoutStatus = response.status();
		}
	});
	await page.fill('#email', row.email);
	const create = page.locator('.wc-block-checkout__create-account input[type="checkbox"]');
	if (await create.count()) {
		await create.check();
	}
	// The password field appears once the box is ticked.
	const password = page.getByLabel(/password/i).first();
	await password.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
	if (await password.count()) {
		await password.fill(plan.password);
	}
	for (const [field, value] of Object.entries(plan.checkout.billing)) {
		const input = page.locator(`#billing-${field}`);
		if (await input.count() && await input.isEditable()) {
			await input.fill(value);
		}
	}
	const state = page.locator('#billing-state');
	if (await state.count()) {
		const tag = await state.evaluate((el) => el.tagName);
		if (tag === 'SELECT') {
			await state.selectOption(plan.checkout.state);
		} else if (await state.isEditable()) {
			await state.fill(plan.checkout.state);
		}
	}
	await wait(4000);
	await page.click('.wc-block-components-checkout-place-order-button');
	await page.waitForURL(/order-received/, { timeout: 45000 }).catch(() => {});
	row.landed = page.url();
	row.notice = (await page.locator('.wc-block-components-notice-banner').allInnerTexts()).join(' ').trim().slice(0, 300);
	row.validation = (await page.locator('.wc-block-components-validation-error').allInnerTexts()).join(' | ').slice(0, 300);
	if (!/order-received/.test(row.landed) && plan.screenshotDir) {
		row.screenshot = path.join(plan.screenshotDir, 'checkout-failed.png');
		await page.screenshot({ path: row.screenshot, fullPage: true });
	}
	await context.close();
	return row;
}

async function checkoutWithTrapFilled(browser, plan) {
	const context = await browser.newContext();
	const page = await context.newPage();
	const row = { route: 'checkout-trap', email: plan.checkoutTrap.email };
	await page.goto(plan.checkout.addToCart, { waitUntil: 'domcontentloaded' });
	await page.goto(plan.checkout.url, { waitUntil: 'networkidle' });
	page.on('request', (request) => {
		if (request.method() === 'POST' && /\/wc\/store\/v1\/checkout/.test(request.url())) {
			try {
				const body = JSON.parse(request.postData() || '{}');
				row.payloadTrap = body.extensions && body.extensions['mizzey-signup'] ? body.extensions['mizzey-signup'].trap : null;
			} catch (error) {
				row.payloadError = String(error);
			}
		}
	});
	page.on('response', (response) => {
		if (response.request().method() === 'POST' && /\/wc\/store\/v1\/checkout/.test(response.url())) {
			row.checkoutStatus = response.status();
		}
	});
	await page.fill('#email', row.email);
	const create = page.locator('.wc-block-checkout__create-account input[type="checkbox"]');
	if (await create.count()) {
		await create.check();
	}
	const password = page.getByLabel(/password/i).first();
	await password.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
	if (await password.count()) {
		await password.fill(plan.password);
	}
	for (const [field, value] of Object.entries(plan.checkout.billing)) {
		const input = page.locator(`#billing-${field}`);
		if (await input.count() && await input.isEditable()) {
			await input.fill(value);
		}
	}
	const state = page.locator('#billing-state');
	if (await state.count() && (await state.evaluate((el) => el.tagName)) === 'SELECT') {
		await state.selectOption(plan.checkout.state);
	}
	// What a form-filling script does: put a value in every input, the hidden one included.
	row.trapRendered = await page.locator('input[name="mizzey_signup_extra"]').count();
	await page.evaluate(() => {
		const trap = document.querySelector('input[name="mizzey_signup_extra"]');
		if (trap) {
			trap.value = 'https://spam.example';
			trap.dispatchEvent(new Event('input', { bubbles: true }));
		}
	});
	await wait(4000);
	await page.click('.wc-block-components-checkout-place-order-button');
	await page.waitForResponse((response) => /\/wc\/store\/v1\/checkout/.test(response.url()) && response.request().method() === 'POST', { timeout: 45000 }).catch(() => {});
	row.landed = page.url();
	await context.close();
	return row;
}

async function orderReceivedOffer(browser, plan) {
	const page = await (await browser.newContext()).newPage();
	const row = { route: 'order-received', email: plan.orderReceived.email };
	await page.goto(plan.orderReceived.url, { waitUntil: 'networkidle' });
	const form = page.locator('.wc-block-order-confirmation-create-account form, form.wc-block-order-confirmation-create-account-form').first();
	await form.waitFor({ timeout: 20000 }).catch(() => {});
	row.formFound = await form.count();
	row.tokenField = await page.locator('input[name="mizzey_signup_token"]').count();
	const password = page.locator('input[type="password"]').first();
	if (await password.count()) {
		await password.fill(plan.password);
	}
	await wait(4000);
	const submit = form.locator('button[type="submit"], input[type="submit"]').first();
	if (await submit.count()) {
		await clickAndLoad(page, submit);
	}
	row.landed = page.url();
	await page.context().close();
	return row;
}

async function signInFlows(browser, plan) {
	const rows = [];
	for (const lang of ['en', 'ar']) {
		const context = await browser.newContext();
		const page = await context.newPage();
		for (const from of plan.signIn[lang].pages) {
			await page.goto(from, { waitUntil: 'domcontentloaded' });
			const links = await page.$$eval('a[href]', (anchors) => anchors.map((a) => a.href).filter((href) => /wp-login\.php|corex-login|lost-password|my-account/.test(href)));
			for (const href of [...new Set(links)]) {
				const response = await context.request.get(href, { maxRedirects: 5 }).catch((error) => ({ status: () => 0, url: () => String(error) }));
				rows.push({ flow: 'printed link', lang, from, href, status: response.status(), landed: response.url() });
			}
		}
		await context.close();
	}

	for (const lang of ['en', 'ar']) {
		const context = await browser.newContext();
		const page = await context.newPage();
		const who = plan.signIn[lang].customer;
		// The reset link first: signing in, or asking for a new link, ends the key it carries.
		await page.goto(plan.signIn[lang].resetLink, { waitUntil: 'domcontentloaded' });
		rows.push({ flow: 'reset link', lang, landed: page.url(), form: (await page.locator('input[name="password_1"], #pass1').count()) > 0, htmlLang: await page.getAttribute('html', 'lang') });

		await page.goto(plan.signIn[lang].account, { waitUntil: 'domcontentloaded' });
		await page.fill('form.login input[name="username"]', who.login);
		await page.fill('form.login input[name="password"]', who.password);
		await clickAndLoad(page, page.locator('form.login [name="login"]'));
		const signedIn = await page.locator('.woocommerce-MyAccount-navigation').count();
		rows.push({ flow: 'account sign-in', lang, landed: page.url(), signedIn: signedIn > 0, htmlLang: await page.getAttribute('html', 'lang') });
		const logout = page.locator('.woocommerce-MyAccount-navigation-link--customer-logout a').first();
		if (await logout.count()) {
			await clickAndLoad(page, logout);
			const confirm = page.locator('a[href*="customer-logout"], a[href*="action=logout"]').first();
			if (await confirm.count()) {
				await clickAndLoad(page, confirm);
			}
		}
		await page.goto(plan.signIn[lang].account, { waitUntil: 'domcontentloaded' });
		rows.push({ flow: 'sign-out', lang, signedOut: (await page.locator('form.login').count()) > 0, htmlLang: await page.getAttribute('html', 'lang') });

		await page.goto(plan.signIn[lang].lostPassword, { waitUntil: 'domcontentloaded' });
		await page.fill('form.lost_reset_password input[name="user_login"]', who.login);
		await clickAndLoad(page, page.locator('form.lost_reset_password [type="submit"]'));
		rows.push({ flow: 'lost password', lang, landed: page.url(), confirmed: /reset-link-sent=true/.test(page.url()), htmlLang: await page.getAttribute('html', 'lang') });
		await context.close();
	}

	for (const entry of plan.signIn.entries) {
		const context = await browser.newContext();
		const page = await context.newPage();
		await page.goto(entry.url, { waitUntil: 'domcontentloaded' });
		await page.fill('#user_login', entry.login);
		await page.fill('#user_pass', entry.password);
		await clickAndLoad(page, page.locator('#wp-submit'));
		const row = { flow: 'wp sign-in', entry: entry.name, challenge: false, signedIn: false };
		if (entry.secret) {
			const field = page.locator('#authcode, input[name="authcode"]').first();
			await field.waitFor({ timeout: 15000 }).catch(() => {});
			row.challenge = (await field.count()) > 0;
			if (row.challenge) {
				await field.fill(totp(entry.secret));
				await clickAndLoad(page, page.locator('#submit, button[type="submit"], input[type="submit"]').first());
			}
		}
		// Staff are judged on their profile screen; customers, whom WooCommerce keeps out of the administration, on
		// the account page.
		await page.goto(entry.check, { waitUntil: 'domcontentloaded' });
		row.signedIn = entry.staff
			? /profile\.php/.test(page.url()) && (await page.locator('#your-profile').count()) > 0
			: (await page.locator('.woocommerce-MyAccount-navigation').count()) > 0;
		row.landed = page.url();
		rows.push(row);
		await context.close();
	}
	return rows;
}

(async () => {
	const plan = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
	const pw = loadPlaywright();
	const browser = await pw.chromium.launch({ headless: true });
	const results = { forms: [], flows: [] };
	try {
		if (plan.part === 'forms') {
			results.forms.push(await registerOnAccountPage(browser, plan, 'en'));
			results.forms.push(await registerOnAccountPage(browser, plan, 'ar'));
			results.forms.push(await checkoutWithAccount(browser, plan));
			results.forms.push(await checkoutWithTrapFilled(browser, plan));
			results.forms.push(await orderReceivedOffer(browser, plan));
		} else {
			results.flows = await signInFlows(browser, plan);
		}
	} catch (error) {
		results.error = String(error && error.stack ? error.stack : error);
	} finally {
		await browser.close();
	}
	console.log(JSON.stringify(results));
})();
