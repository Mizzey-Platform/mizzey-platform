#!/usr/bin/env node
/**
 * The home page's sign-up in a real browser (spec 066, slice 5; AC-3, HOME-09). Driven by t119, which prepares the
 * runtime and passes a JSON plan as the only argument; prints one JSON object: { checks, shots, signedUp }.
 *
 * In Chromium and WebKit, at 390 wide (a phone, touch) and 1440, in English and Arabic, on a page with the section:
 *
 * - the section as C1-Home-Phone, C1-Home-Desktop and C1-Home-Phone-Arabic measure it (frame A2): paddings, corners,
 *   the field, the box and the button, the gaps between them, the type sizes; the page never scrolls sideways;
 * - focus on the field: the edge turns Indigo and the 2 px line draws;
 * - an address that is not one (frame C2): the red ring and icon, the words in Ink saying how to fix it, 8 under the
 *   field, the box moved down by that line (28, or 30 with Arabic's taller line, as C1-Home-Phone-Arabic draws it), the focus on the field;
 * - the busy button while the server has not answered (the answer is held back here): the i-dot loader inside the
 *   button, its width unchanged, and no success shown yet;
 * - the success (frame C2): it replaces the form at the same height, nothing below moves, the focus is on the message,
 *   which names the address;
 * - a second sign-up of the same address gets the same message;
 * - with JavaScript off, the same states come back from the server;
 * - the leave link opens the Arabic page, and its button leaves the list.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
const signedUp = {};
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const near = ( a, b, tolerance = 1 ) => typeof a === 'number' && Math.abs( a - b ) <= tolerance;
const TIMEOUT = 60000;
const INDIGO = 'rgb(38, 59, 115)';
const DANGER = 'rgb(205, 41, 73)';
const INK = 'rgb(30, 30, 30)';

/** The board's numbers (frame A2), per width; the Arabic type is Tajawal's larger sizes. */
const BOARD = {
	390: { padBlock: 24, padInline: 16, corner: 24, width: 358, field: 48, button: 52, box: 24, textToLabel: 16, labelToField: 8, fieldToBox: 8, boxToButton: 12, errorLine: { en: 28, ar: 30 }, h2: { en: 23, ar: 25 } },
	1440: { padBlock: 56, padInline: 56, corner: 24, width: 1272, field: 56, button: 56, box: 24, labelToField: 8, fieldToBox: 8, fieldToButton: 12, errorLine: { en: 28, ar: 30 }, height: 248, h2: { en: 31, ar: 34 } },
};

async function measure( page ) {
	return page.evaluate( () => {
		const s = document.querySelector( '.mizzey-signup' );
		const r = ( el ) => el.getBoundingClientRect();
		const q = ( sel ) => s.querySelector( sel );
		const cs = getComputedStyle( s );
		const footer = document.querySelector( 'footer' );
		return {
			top: r( s ).top + scrollY,
			height: r( s ).height,
			width: r( s ).width,
			padBlock: parseFloat( cs.paddingTop ),
			padInline: parseFloat( cs.paddingInlineStart ),
			corner: parseFloat( cs.borderTopLeftRadius ),
			h2: parseFloat( getComputedStyle( q( '.mizzey-signup__heading' ) ).fontSize ),
			text: r( q( '.mizzey-signup__text' ) ),
			label: r( q( '.mizzey-signup__label' ) ),
			field: r( q( '.mizzey-signup__input' ) ),
			fieldRadius: parseFloat( getComputedStyle( q( '.mizzey-signup__input' ) ).borderTopLeftRadius ),
			fieldFont: parseFloat( getComputedStyle( q( '.mizzey-signup__input' ) ).fontSize ),
			fieldShadow: getComputedStyle( q( '.mizzey-signup__input' ) ).boxShadow,
			fieldOutline: getComputedStyle( q( '.mizzey-signup__input' ) ).outlineStyle,
			line: getComputedStyle( q( '.mizzey-signup__line' ) ).transform,
			lineColour: getComputedStyle( q( '.mizzey-signup__line' ) ).backgroundColor,
			box: r( q( '.mizzey-signup__optin' ) ),
			consent: r( q( '.mizzey-signup__consent' ) ),
			button: r( q( '.mizzey-signup__button' ) ),
			error: q( '.mizzey-signup__error' ).hidden ? null : r( q( '.mizzey-signup__error' ) ),
			errorText: q( '.mizzey-signup__error-text' ).textContent.trim(),
			errorColour: getComputedStyle( q( '.mizzey-signup__error' ) ).color,
			errorIcon: q( '.mizzey-signup__error-icon' ) ? getComputedStyle( q( '.mizzey-signup__error-icon' ) ).color : '',
			errorAnimation: getComputedStyle( q( '.mizzey-signup__error' ) ).animationName,
			invalid: q( '.mizzey-signup__input' ).getAttribute( 'aria-invalid' ),
			done: q( '.mizzey-signup__done' ).hidden ? null : r( q( '.mizzey-signup__done' ) ),
			doneText: q( '.mizzey-signup__done' ).textContent.replace( /\s+/g, ' ' ).trim(),
			formVisible: getComputedStyle( q( '.mizzey-signup__form' ) ).visibility,
			formShowing: [ ...q( '.mizzey-signup__form' ).querySelectorAll( '*' ) ].filter( ( el ) => getComputedStyle( el ).visibility === 'visible' && r( el ).width > 0 ).length,
			focus: document.activeElement === q( '.mizzey-signup__done' ) ? 'done' : document.activeElement === q( '.mizzey-signup__input' ) ? 'field' : ( document.activeElement && document.activeElement.className ) || '',
			below: footer ? r( footer ).top + scrollY : null,
			sideways: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
			dir: getComputedStyle( s ).direction,
		};
	} );
}

/** The form's token is refused within its first seconds (Security\SignupCheck), as a script would send it: wait them out. */
async function ripe( page ) {
	const age = await page.evaluate( () => performance.now() );
	await page.waitForTimeout( Math.max( 0, 3500 - age ) );
}

async function press( page, touch, selector ) {
	const target = page.locator( selector );
	if ( touch ) {
		await target.tap();
	} else {
		await target.click();
	}
}

async function shoot( page, name ) {
	const file = path.join( plan.shots, `t119-${ name }.png` );
	await page.locator( '.mizzey-signup' ).screenshot( { path: file } ).catch( () => {} );
	shots.push( file );
}

async function run( engineName, engine ) {
	const browser = await engine.launch( engineName === 'chromium' ? playwright.launch : { headless: true } );
	for ( const width of [ 390, 1440 ] ) {
		for ( const lang of [ 'en', 'ar' ] ) {
			const where = `${ engineName } ${ width } ${ lang }`;
			const touch = width === 390;
			const b = BOARD[ width ];
			const context = await browser.newContext( { viewport: { width, height: 900 }, hasTouch: touch, isMobile: touch && engineName === 'chromium' } );
			const page = await context.newPage();
			await page.goto( plan[ lang ], { waitUntil: 'load', timeout: TIMEOUT } );
			await page.locator( '.mizzey-signup' ).scrollIntoViewIfNeeded();
			await page.waitForTimeout( 300 );

			// ---- A2, as the board measures it -----------------------------------------------------------------------
			const a = await measure( page );
			check( ! a.sideways, `K-1 [${ where }] the page does not scroll sideways` );
			check( lang === 'ar' ? a.dir === 'rtl' : a.dir === 'ltr', `K-2 [${ where }] the section reads ${ lang === 'ar' ? 'right to left' : 'left to right' }` );
			check( near( a.padBlock, b.padBlock ) && near( a.padInline, b.padInline ) && near( a.corner, b.corner ) && near( a.width, b.width ), `K-1 [${ where }] panel: padding ${ a.padBlock }/${ a.padInline } (board ${ b.padBlock }/${ b.padInline }), corner ${ a.corner } (24), width ${ a.width } (${ b.width })` );
			check( near( a.h2, b.h2[ lang ], 0.6 ), `K-1 [${ where }] heading ${ a.h2 } px (board ${ b.h2[ lang ] })` );
			check( near( a.field.height, b.field ) && a.fieldRadius >= a.field.height / 2 && a.fieldFont >= 16, `K-1 [${ where }] field ${ a.field.height } high (board ${ b.field }), a pill (radius ${ a.fieldRadius }), type ${ a.fieldFont } px (16 or more, so a phone does not zoom)` );
			check( near( a.button.height, b.button ), `K-1 [${ where }] button ${ a.button.height } high (board ${ b.button })` );
			check( near( a.box.height, b.box ) && near( a.box.width, b.box ) && a.consent.height >= 44, `K-1 [${ where }] the optional box is ${ a.box.width } x ${ a.box.height } (24) on a row ${ a.consent.height } high (44 or more)` );
			check( near( a.field.top - a.label.bottom, b.labelToField ) && near( a.consent.top - a.field.bottom, b.fieldToBox ), `K-1 [${ where }] label to field ${ ( a.field.top - a.label.bottom ).toFixed( 1 ) } (8), field to box ${ ( a.consent.top - a.field.bottom ).toFixed( 1 ) } (8)` );
			if ( width === 390 ) {
				check( near( a.label.top - a.text.bottom, b.textToLabel ) && near( a.button.top - a.consent.bottom, b.boxToButton ) && near( a.button.width, a.field.width ), `K-1 [${ where }] line to label ${ ( a.label.top - a.text.bottom ).toFixed( 1 ) } (16), box to button ${ ( a.button.top - a.consent.bottom ).toFixed( 1 ) } (12), the button as wide as the field` );
			} else {
				const gap = lang === 'ar' ? a.field.left - a.button.right : a.button.left - a.field.right;
				check( near( gap, b.fieldToButton ) && near( a.button.top, a.field.top ) && near( a.height, b.height, 2 ), `K-1 [${ where }] the button beside the field, ${ gap.toFixed( 1 ) } apart (12), on its row; panel ${ a.height } high (board 248)` );
			}
			await shoot( page, `${ engineName }-${ width }-${ lang }-a2` );

			// ---- Focus: the edge turns Indigo and the line draws ----------------------------------------------------
			await press( page, touch, '.mizzey-signup__input' );
			await page.waitForTimeout( 600 );
			const f = await measure( page );
			check( f.fieldShadow.includes( INDIGO ) && /matrix\(1, 0, 0, 1/.test( f.line ) && f.lineColour === INDIGO && f.fieldOutline === 'none', `C1-Motion [${ where }] focus: the edge turns Indigo and the 2 px line draws, with no second ring (${ f.line }, outline ${ f.fieldOutline })` );

			// ---- C2, invalid -----------------------------------------------------------------------------------------
			await page.fill( '.mizzey-signup__input', 'sara@example' );
			await press( page, touch, '.mizzey-signup__button' );
			await page.waitForTimeout( 400 );
			const e = await measure( page );
			const words = lang === 'ar' ? 'اكتب بريدًا إلكترونيًا مثل name@example.com.' : 'Enter an email address like name@example.com.';
			check( e.error && e.errorText === words && e.invalid === 'true' && e.focus === 'field', `HOME-09 [${ where }] invalid: the words say how to fix it ("${ e.errorText }"), the field is marked invalid and has the focus (${ e.focus })` );
			check( e.fieldShadow.includes( DANGER ) && e.errorIcon === DANGER && e.errorColour === INK, `C2 [${ where }] invalid: red ring and icon, the words in Ink (${ e.errorColour })` );
			check( e.error && near( e.error.top - e.field.bottom, 8 ) && near( e.consent.top - a.consent.top, b.errorLine[ lang ] ), `C2 [${ where }] the message sits 8 under the field (${ e.error ? ( e.error.top - e.field.bottom ).toFixed( 1 ) : '-' }) and the box moves down by its line (${ ( e.consent.top - a.consent.top ).toFixed( 1 ) }, board ${ b.errorLine[ lang ] })` );
			check( e.errorAnimation !== 'mizzey-nudge' && ! e.done, `C1-Motion [${ where }] the message fades in, nothing shakes, and nothing claims success` );
			await shoot( page, `${ engineName }-${ width }-${ lang }-invalid` );

			// ---- Busy, then success ----------------------------------------------------------------------------------
			const email = `${ plan.tag }-${ engineName }-${ width }-${ lang }@example.com`;
			let release;
			const held = new Promise( ( done ) => ( release = done ) );
			await page.route( '**/mizzey/v1/signup**', async ( route ) => {
				await held;
				await route.continue();
			} );
			await page.fill( '.mizzey-signup__input', email );
			if ( width === 1440 && lang === 'en' ) {
				await press( page, touch, '.mizzey-signup__optin' );
			}
			const before = await measure( page );
			await ripe( page );
			await press( page, touch, '.mizzey-signup__button' );
			await page.waitForTimeout( 800 );
			const busy = await page.evaluate( () => {
				const button = document.querySelector( '.mizzey-signup__button' );
				const loader = button.querySelector( '.mizzey-loader' );
				const r = ( el ) => el.getBoundingClientRect();
				return {
					busy: button.getAttribute( 'aria-busy' ),
					width: r( button ).width,
					inside: !! loader && r( loader ).left >= r( button ).left && r( loader ).right <= r( button ).right && r( loader ).top >= r( button ).top && r( loader ).bottom <= r( button ).bottom,
					dots: loader ? loader.querySelectorAll( '.mizzey-loader__dot' ).length : 0,
					done: ! document.querySelector( '.mizzey-signup__done' ).hidden,
				};
			} );
			check( busy.busy === 'true' && busy.dots === 3 && busy.inside && near( busy.width, before.button.width ) && ! busy.done, `C1-Motion [${ where }] busy: the i-dot loader inside the button (${ busy.dots } dots, inside ${ busy.inside }), width kept ${ busy.width } (${ before.button.width }), no success before the server answers` );
			await shoot( page, `${ engineName }-${ width }-${ lang }-busy` );
			release();
			await page.locator( '.mizzey-signup__done' ).waitFor( { state: 'visible', timeout: TIMEOUT } );
			await page.waitForTimeout( 300 );
			const d = await measure( page );
			signedUp[ email ] = lang;
			const success = lang === 'ar' ? 'أنت على القائمة' : 'You are on the list';
			check( await page.evaluate( () => document.querySelector( '.mizzey-signup__done-title' ).tagName ) === 'H2', `C2 [${ where }] the success title is a heading, as the phone board draws it` );
			check( d.doneText.includes( success ) && d.doneText.includes( email ) && d.focus === 'done' && d.formVisible === 'hidden' && d.formShowing === 0, `HOME-09 [${ where }] success: "${ d.doneText.slice( 0, 60 ) }..." names the address, has the focus, and replaces the form (${ d.formShowing } parts of it still showing)` );
			check( near( d.height, before.height, 0.5 ) && near( d.below, before.below, 0.5 ), `C2 [${ where }] success keeps the panel's height (${ d.height } after ${ before.height }) and nothing below moves (${ d.below } after ${ before.below })` );
			if ( width === 1440 ) {
				check( d.done && d.done.left >= ( lang === 'ar' ? 0 : d.field.left - 1 ), `C2 [${ where }] on a desktop the message takes the form's place and the words stay` );
			}
			await shoot( page, `${ engineName }-${ width }-${ lang }-success` );
			await page.unroute( '**/mizzey/v1/signup**' );

			// ---- The same address again: the same answer ------------------------------------------------------------
			if ( width === 390 && lang === 'en' ) {
				await page.reload( { waitUntil: 'load' } );
				await page.fill( '.mizzey-signup__input', email );
				await ripe( page );
				await press( page, touch, '.mizzey-signup__button' );
				await page.locator( '.mizzey-signup__done' ).waitFor( { state: 'visible', timeout: TIMEOUT } );
				const again = await measure( page );
				check( again.doneText === d.doneText, `HOME-09 [${ where }] signing up again gives the same message, so it never says the address was already there` );
			}
			await context.close();
		}
	}

	// ---- No answer from the site: offline, closed for maintenance, a record that could not be written --------------
	{
		const context = await browser.newContext( { viewport: { width: 390, height: 900 }, hasTouch: true } );
		const page = await context.newPage();
		await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		const words = {
			offline: 'We could not reach Mizzey. Check your connection and try again.',
			unavailable: 'Sign-up is paused for a moment while we work on the site. Please try again shortly.',
			failed: 'We could not sign you up just now. Please try again in a few minutes.',
		};
		const answers = {
			offline: ( route ) => route.abort( 'internetdisconnected' ),
			unavailable: ( route ) => route.fulfill( { status: 503, contentType: 'text/html', body: '<p>maintenance</p>' } ),
			failed: ( route ) => route.fulfill( { status: 500, contentType: 'application/json', body: JSON.stringify( { state: 'failed', email: '' } ) } ),
		};
		for ( const [ state, answer ] of Object.entries( answers ) ) {
			await page.route( '**/mizzey/v1/signup**', answer );
			await page.fill( '.mizzey-signup__input', `${ plan.tag }-${ state }@example.com` );
			await page.locator( '.mizzey-signup__button' ).tap();
			await page.waitForFunction( () => ! document.querySelector( '.mizzey-signup__error' ).hidden && ! document.querySelector( '.mizzey-signup__button' ).hasAttribute( 'aria-busy' ), null, { timeout: TIMEOUT } );
			const shown = await measure( page );
			check( shown.errorText === words[ state ] && ! shown.done, `HOME-09 [${ engineName }] ${ state }: the form says so in its own words ("${ shown.errorText }") and claims no sign-up` );
			await page.unroute( '**/mizzey/v1/signup**' );
		}
		await shoot( page, `${ engineName }-390-en-failed` );
		await context.close();
	}

	// ---- A page from a shared cache: the same HTML, and so the same printed token, for every visitor ----------------
	{
		const probe = await browser.newContext();
		const cached = await ( await probe.request.get( plan.en ) ).text();
		await probe.close();
		// With JavaScript: two visitors in turn, each served exactly that HTML, both sign up.
		for ( const visitor of [ 'first', 'second' ] ) {
			const context = await browser.newContext( { viewport: { width: 390, height: 900 }, hasTouch: true } );
			const page = await context.newPage();
			await page.route( plan.en, ( route ) => ( route.request().method() === 'GET' ? route.fulfill( { status: 200, contentType: 'text/html; charset=UTF-8', body: cached } ) : route.continue() ) );
			await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
			const email = `${ plan.tag }-${ engineName }-cached-${ visitor }@example.com`;
			await page.locator( '.mizzey-signup__input' ).tap();
			await page.fill( '.mizzey-signup__input', email );
			await page.locator( '.mizzey-signup__button' ).tap();
			await page.locator( '.mizzey-signup__done' ).waitFor( { state: 'visible', timeout: TIMEOUT } );
			const done = await measure( page );
			signedUp[ email ] = 'en';
			check( done.doneText.includes( email ), `HOME-09 [${ engineName } cached page] the ${ visitor } visitor served the same cached HTML signs up (the script uses its own token)` );
			await context.close();
		}
		// Without JavaScript: a page every visitor is sent carries no token (NFR-02), so each visitor's first press is
		// answered with one more press asked for, the address kept, and that press signs up.
		const plainCached = cached;
		for ( const visitor of [ 'first', 'second' ] ) {
			const context = await browser.newContext( { viewport: { width: 390, height: 900 }, hasTouch: true, javaScriptEnabled: false } );
			const page = await context.newPage();
			let served = false;
			await page.route( plan.en, ( route ) => {
				if ( route.request().method() === 'GET' && ! served ) {
					served = true;
					return route.fulfill( { status: 200, contentType: 'text/html; charset=UTF-8', body: plainCached } );
				}
				return route.continue();
			} );
			await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
			const email = `${ plan.tag }-${ engineName }-stale-${ visitor }@example.com`;
			await page.fill( '.mizzey-signup__input', email );
			await page.waitForTimeout( 3500 );
			await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.locator( '.mizzey-signup__button' ).tap() ] );
			let shown = await measure( page );
			const kept = await page.inputValue( '.mizzey-signup__input' );
			check( ! shown.done && shown.errorText === 'Your address is kept. Press Sign up again to finish.' && kept === email && shown.invalid !== 'true', `HOME-09 [${ engineName } no script, cached page] the ${ visitor } visitor's first press comes back with the address kept (${ kept }) and the words to press Sign up again ("${ shown.errorText }"), the field not marked invalid` );
			if ( visitor === 'second' ) {
				await shoot( page, `${ engineName }-noscript-again` );
			}
			await page.waitForTimeout( 3500 );
			await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.locator( '.mizzey-signup__button' ).tap() ] );
			shown = await measure( page );
			signedUp[ email ] = 'en';
			check( shown.done && shown.doneText.includes( email ), `HOME-09 [${ engineName } no script, cached page] the ${ visitor } visitor ends signed up` );
			await context.close();
		}
	}

	// ---- Without JavaScript: the server answers in the same states -----------------------------------------------
	const context = await browser.newContext( { viewport: { width: 390, height: 900 }, hasTouch: true, javaScriptEnabled: false } );
	const page = await context.newPage();
	await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.fill( '.mizzey-signup__input', 'sara@example' );
	await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.locator( '.mizzey-signup__button' ).tap() ] );
	// The page carried no token; an address that is not one is still answered as such first (it is judged before the
	// token), and that answer, built for this visitor, carries the token the next press uses.
	const n = await measure( page );
	check( n.error && n.errorText === 'Enter an email address like name@example.com.' && n.invalid === 'true' && n.fieldShadow.includes( DANGER ), `HOME-09 [${ engineName } no script] an invalid address comes back with the red field and the words` );
	const plainEmail = `${ plan.tag }-${ engineName }-noscript@example.com`;
	await page.fill( '.mizzey-signup__input', plainEmail );
	await page.waitForTimeout( 3500 );
	await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.locator( '.mizzey-signup__button' ).tap() ] );
	const ns = await measure( page );
	signedUp[ plainEmail ] = 'en';
	check( ns.done && ns.doneText.includes( plainEmail ) && ns.formVisible === 'hidden', `HOME-09 [${ engineName } no script] the sign-up comes back as the success message with the address` );
	await shoot( page, `${ engineName }-noscript-success` );
	await context.close();

	// ---- The leave page, in Arabic ------------------------------------------------------------------------------
	if ( plan.leave ) {
		const leave = await browser.newContext( { viewport: { width: 390, height: 900 }, hasTouch: true } );
		const lp = await leave.newPage();
		await lp.goto( plan.leave, { waitUntil: 'load', timeout: TIMEOUT } );
		const shown = await lp.evaluate( () => ( { lang: document.documentElement.lang, dir: document.documentElement.dir, state: ( document.querySelector( '[data-mizzey-leave]' ) || {} ).dataset?.mizzeyLeave, text: document.querySelector( '.mizzey-leave' )?.textContent.replace( /\s+/g, ' ' ).trim() || '', header: !! document.querySelector( 'header' ), footer: !! document.querySelector( 'footer' ) } ) );
		if ( engineName === 'chromium' ) {
			check( shown.state === 'ask' && shown.dir === 'rtl' && shown.text.includes( 'مغادرة قائمة ميزي' ) && shown.text.includes( plan.leaveEmail ) && shown.header && shown.footer, `HOME-09 [${ engineName }] the leave link opens a small Arabic page asking for one press (${ shown.text.slice( 0, 60 ) })` );
			await Promise.all( [ lp.waitForNavigation( { timeout: TIMEOUT } ), lp.locator( '.mizzey-leave__form button' ).tap() ] );
		}
		const left = await lp.evaluate( () => ( { state: document.querySelector( '[data-mizzey-leave]' )?.dataset.mizzeyLeave, text: document.querySelector( '.mizzey-leave' )?.textContent.replace( /\s+/g, ' ' ).trim() || '' } ) );
		check( left.state === 'left' && left.text.includes( 'لقد غادرت القائمة' ), `HOME-09 [${ engineName }] after the press the page confirms, in Arabic, that the address has left (${ left.state })` );
		const file = path.join( plan.shots, `t119-${ engineName }-leave-ar.png` );
		await lp.screenshot( { path: file, fullPage: true } );
		shots.push( file );
		await leave.close();
	}

	// ---- Reduced motion: the message appears without movement ----------------------------------------------------
	const calm = await browser.newContext( { viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' } );
	const cp = await calm.newPage();
	await cp.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
	await cp.fill( '.mizzey-signup__input', 'sara@example' );
	await cp.click( '.mizzey-signup__button' );
	const c = await measure( cp );
	check( c.error && c.errorAnimation === 'none', `K-5 [${ engineName }] under reduced motion the message appears without movement (${ c.errorAnimation })` );
	await calm.close();
	await browser.close();
}

const playwright = loadPlaywright();

( async () => {
	if ( ! playwright ) {
		console.log( JSON.stringify( { checks: [ { ok: false, what: 'Playwright is not available' } ], shots, signedUp } ) );
		return;
	}
	await run( 'chromium', playwright.pw.chromium );
	await run( 'webkit', playwright.pw.webkit );
	console.log( JSON.stringify( { checks, shots, signedUp } ) );
} )().catch( ( error ) => {
	console.log( JSON.stringify( { checks, shots, signedUp, error: String( error && error.stack ? error.stack : error ).slice( 0, 1500 ) } ) );
} );
