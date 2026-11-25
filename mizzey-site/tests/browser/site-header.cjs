#!/usr/bin/env node
/**
 * The designed header in a real browser (spec 066, slice 2; AC-1, AC-14). Driven by t108, which prepares the runtime
 * and passes a JSON plan as the only argument; prints one JSON object: { checks: [{ ok, what }], menus: {...} }.
 *
 * In English and in Arabic, at 1440 and at 390 wide:
 *
 * - the entry points: the logo, the labelled search field, the language switcher, the account, the wishlist and the
 *   cart with its count, each leading where the header's data says (NAV-01 to NAV-06, NAV-09);
 * - the mega menu (1440): "All categories" opens it, focus lands on the first row and stays inside, the arrow keys
 *   move between rows and the panel follows, Escape closes it and focus returns to the control (NAV-07);
 * - the phone menu (390): the menu control opens the drawer from the start edge, the same categories as the mega
 *   menu, a category opens its own level with "Shop all" and its children, back returns, focus stays inside and
 *   returns on Escape; the Categories tab shows the same categories (NAV-08, NAV-10);
 * - the phone header's three forms: chips on the home page, the search row on a listing, a search control on a
 *   product page (NAV-08, NAV-09);
 * - an add to the cart made on the page raises the cart's count without a reload (NAV-06);
 * - the language switcher leads to the same page in the other language and keeps the cart (NAV-02);
 * - signed in, the account shows the first name, and the switcher carries the signed-in nonce (D-31, a gift).
 *
 * The plan's part "after" reopens both menus after a category was taken out of the main menu in the admin (SSC-07).
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site: PLAYWRIGHT_DIR, or the copy
 * `npx playwright` has cached; when it has no Chromium of its own, the installed Chrome is used.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 90000;
const T = plan.tag;
const menus = {};

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );
const tagged = ( names ) => names.filter( ( n ) => n.includes( T ) );

async function open( browser, url, width, cookies = [] ) {
	const context = await browser.newContext( { viewport: { width, height: 900 }, deviceScaleFactor: 1 } );
	if ( cookies.length ) {
		const { hostname } = new URL( url );
		await context.addCookies( cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: hostname, path: '/' } ) ) );
	}
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForTimeout( 300 );
	return { context, page, errors };
}

/** Where the focused element is: inside the selector or not. */
const focusInside = ( page, selector ) => page.evaluate( ( s ) => {
	const root = document.querySelector( s );
	return !! root && root.contains( document.activeElement );
}, selector );

/** The visible names of a list of rows. */
const names = ( page, selector ) => page.$$eval( selector, ( els ) => els.filter( ( e ) => e.getClientRects().length ).map( ( e ) => e.textContent.replace( /\s+/g, ' ' ).trim() ) );

async function entryPoints( page, lang, width ) {
	const where = `[${ lang }, ${ width }]`;
	const data = await page.evaluate( () => {
		const header = document.querySelector( '.mizzey-header' );
		if ( ! header ) {
			return null;
		}
		const visible = ( el ) => !! el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
		const pick = ( s ) => [ ...header.querySelectorAll( s ) ].find( visible ) || null;
		const logo = pick( '.mizzey-header__logo' );
		const form = pick( 'form[role="search"]' );
		const input = form ? form.querySelector( 'input[name="s"]' ) : null;
		const label = input ? ( input.labels && input.labels[ 0 ] ? input.labels[ 0 ].textContent.trim() : input.getAttribute( 'aria-label' ) ) : null;
		const lang = pick( '.mizzey-header__language' );
		const account = pick( '.mizzey-header__account' );
		const wishlist = pick( '.mizzey-header__wishlist' );
		const cart = pick( '.mizzey-header__cart' );
		const count = cart ? cart.querySelector( '.mizzey-count' ) : null;
		return {
			dir: document.documentElement.dir || getComputedStyle( document.documentElement ).direction,
			logo: logo ? logo.getAttribute( 'href' ) : null,
			search: form ? { action: form.getAttribute( 'action' ), label, type: ( form.querySelector( 'input[name="post_type"]' ) || {} ).value || null } : null,
			searchControl: !! pick( '.mizzey-header__search-toggle' ),
			language: lang ? { text: lang.textContent.trim(), href: lang.getAttribute( 'href' ), hreflang: lang.getAttribute( 'hreflang' ) } : null,
			account: account ? { href: account.getAttribute( 'href' ), name: account.textContent.replace( /\s+/g, ' ' ).trim(), label: account.getAttribute( 'aria-label' ) } : null,
			wishlist: wishlist ? { href: wishlist.getAttribute( 'href' ), label: wishlist.getAttribute( 'aria-label' ) || wishlist.textContent.trim() } : null,
			cart: cart ? { href: cart.getAttribute( 'href' ), label: cart.textContent.replace( /\s+/g, ' ' ).trim() || cart.getAttribute( 'aria-label' ), count: count ? count.textContent.trim() : null } : null,
			chips: !! pick( '.mizzey-header__chips' ),
			row: !! pick( '.mizzey-header__row' ),
			menuControl: !! pick( '.mizzey-header__menu' ),
		};
	} );
	return { where, data };
}

async function megaMenu( page, lang, expected ) {
	const where = `[${ lang }, 1440]`;
	const button = page.locator( '.mizzey-header__all' );
	check( await button.count() === 1 && await button.isVisible(), `NAV-07 ${ where } the "All categories" control is in the category row` );
	if ( ! await button.count() ) {
		return [];
	}
	await button.focus();
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 400 );
	const mega = '.mizzey-mega';
	const shown = await page.locator( mega ).isVisible();
	check( shown && await button.getAttribute( 'aria-expanded' ) === 'true', `NAV-07 ${ where } Enter opens the mega menu` );
	const firstRow = await page.evaluate( () => {
		const rows = [ ...document.querySelectorAll( '.mizzey-mega__row' ) ];
		return rows.length > 0 && document.activeElement === rows[ 0 ];
	} );
	check( firstRow, `NFR-11 ${ where } focus lands on the mega menu's first row` );
	const rows = tagged( await names( page, '.mizzey-mega__row .mizzey-mega__name' ) );
	check( same( rows, expected.top ), `NAV-07 ${ where } the mega menu lists the admin's categories in order: ${ JSON.stringify( rows ) }` );
	// Move to the tagged parent with the arrow keys: the panel follows.
	const all = await names( page, '.mizzey-mega__row .mizzey-mega__name' );
	const index = all.indexOf( expected.parent );
	for ( let i = 0; i < index; i++ ) {
		await page.keyboard.press( 'ArrowDown' );
	}
	await page.waitForTimeout( 200 );
	const focused = await page.evaluate( () => ( document.activeElement.querySelector( '.mizzey-mega__name' ) || {} ).textContent || '' );
	const children = await names( page, '.mizzey-mega__panel:not([hidden]) .mizzey-mega__child-name' );
	const title = await page.evaluate( () => ( document.querySelector( '.mizzey-mega__panel:not([hidden]) .mizzey-mega__title' ) || {} ).textContent || '' );
	check( index >= 0 && focused.trim() === expected.parent && title.trim() === expected.parent && same( children, expected.children ), `NAV-07 ${ where } the arrow keys reach ${ expected.parent } and its panel shows its children: ${ JSON.stringify( children ) }` );
	const shopAll = await page.evaluate( () => {
		const a = document.querySelector( '.mizzey-mega__panel:not([hidden]) .mizzey-mega__all' );
		return a ? a.getAttribute( 'href' ) : null;
	} );
	check( shopAll === expected.parentUrl, `NAV-07 ${ where } the panel's "Shop all" leads to the category (${ shopAll })` );
	const photo = await page.evaluate( () => !! document.querySelector( '.mizzey-mega__panel:not([hidden]) .mizzey-mega__photo img' ) );
	check( photo === expected.photo, `NAV-07 ${ where } the panel shows the category's photo tile` );
	let trapped = true;
	for ( let i = 0; i < 40; i++ ) {
		await page.keyboard.press( 'Tab' );
		trapped = trapped && await focusInside( page, mega );
	}
	for ( let i = 0; i < 5; i++ ) {
		await page.keyboard.press( 'Shift+Tab' );
		trapped = trapped && await focusInside( page, mega );
	}
	check( trapped, `NFR-11 ${ where } Tab and Shift+Tab stay inside the open mega menu` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 400 );
	const returned = await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'mizzey-header__all' ) );
	check( ! await page.locator( mega ).isVisible() && returned && await button.getAttribute( 'aria-expanded' ) === 'false', `NFR-11 ${ where } Escape closes the mega menu and focus returns to "All categories"` );
	// A click opens it too, and a click on the page behind closes it.
	await button.click();
	await page.waitForTimeout( 300 );
	const clicked = await page.locator( mega ).isVisible();
	const megaBox = await page.locator( mega ).boundingBox();
	const fits = !! megaBox && megaBox.y + megaBox.height <= 900;
	check( fits, `NAV-07 ${ where } the open mega menu stays inside the window (${ megaBox ? Math.round( megaBox.y + megaBox.height ) : 'none' })` );
	// A click elsewhere closes it: on the search field, which stays above the dimmed page.
	await page.locator( '.mizzey-header__search-input' ).click();
	await page.waitForTimeout( 400 );
	check( clicked && ! await page.locator( mega ).isVisible(), `NAV-07 ${ where } a click opens the mega menu and a click elsewhere closes it` );
	return rows;
}

async function phoneMenu( page, lang, expected ) {
	const where = `[${ lang }, 390]`;
	const button = page.locator( '.mizzey-header__menu' );
	check( await button.count() === 1 && await button.isVisible(), `NAV-10 ${ where } the menu control is in the phone header` );
	if ( ! await button.count() ) {
		return [];
	}
	const drawer = '#mizzey-menu';
	await button.click();
	await page.waitForTimeout( 600 );
	const open = await page.evaluate( ( s ) => !! document.querySelector( s ) && document.querySelector( s ).open, drawer );
	check( open && await focusInside( page, drawer ), `NAV-10 ${ where } the menu control opens the drawer and moves focus into it` );
	const box = await page.locator( `${ drawer } .mizzey-overlay__panel` ).boundingBox();
	const fromStart = box && ( lang === 'ar' ? Math.abs( box.x + box.width - 390 ) <= 1 : Math.abs( box.x ) <= 1 );
	check( fromStart, `AC-14 ${ where } the drawer comes out of the start edge (${ box ? Math.round( box.x ) + '+' + Math.round( box.width ) : 'none' })` );
	const rows = tagged( await names( page, `${ drawer } .mizzey-menu__level:not([hidden]) .mizzey-menu__row .mizzey-menu__name` ) );
	check( same( rows, expected.top ), `NAV-10 ${ where } the drawer lists the same categories as the mega menu: ${ JSON.stringify( rows ) }` );
	const offers = await names( page, `${ drawer } .mizzey-menu__level:not([hidden]) .mizzey-menu__offer` );
	check( offers.some( ( n ) => n.includes( expected.offer ) ), `SSC-07 ${ where } the menu's offer link leads the drawer: ${ JSON.stringify( offers ) }` );
	await page.locator( `${ drawer } .mizzey-menu__row`, { hasText: expected.parent } ).first().click();
	await page.waitForTimeout( 400 );
	const level = await page.evaluate( ( s ) => {
		const l = document.querySelector( `${ s } .mizzey-menu__level:not([hidden])` );
		if ( ! l ) {
			return null;
		}
		const all = l.querySelector( '.mizzey-menu__all' );
		return {
			title: ( l.querySelector( '.mizzey-menu__title' ) || {} ).textContent || '',
			all: all ? all.getAttribute( 'href' ) : null,
			children: [ ...l.querySelectorAll( '.mizzey-menu__row .mizzey-menu__name' ) ].map( ( e ) => e.textContent.trim() ),
			focus: l.contains( document.activeElement ),
			banner: !! l.querySelector( '.mizzey-menu__banner img' ),
		};
	}, drawer );
	check( level && level.title.trim() === expected.parent && level.all === expected.parentUrl && same( level.children, expected.children ) && level.focus, `NAV-10 ${ where } a category opens its level: its name, "Shop all" and its children, with focus inside (${ JSON.stringify( level ) })` );
	check( level && level.banner === expected.photo, `NAV-10 ${ where } the level shows the category's photo banner with "Shop all" on it` );
	await page.locator( `${ drawer } .mizzey-menu__level:not([hidden]) .mizzey-menu__back` ).click();
	await page.waitForTimeout( 300 );
	const back = tagged( await names( page, `${ drawer } .mizzey-menu__level:not([hidden]) .mizzey-menu__row .mizzey-menu__name` ) );
	check( same( back, expected.top ), `NAV-10 ${ where } back returns to the first level` );
	// A tap on the scrim beside the drawer closes it, and focus returns to the menu control.
	await page.mouse.click( lang === 'ar' ? 20 : 370, 450 );
	await page.waitForTimeout( 500 );
	const tapped = await page.evaluate( ( s ) => ! document.querySelector( s ).open && document.activeElement && document.activeElement.classList.contains( 'mizzey-header__menu' ), drawer );
	check( tapped, `NAV-10 ${ where } a tap on the scrim closes the drawer and focus returns to the menu control` );
	await button.click();
	await page.waitForTimeout( 600 );
	// The drawer is a native modal dialog: the page behind is inert, so Tab never reaches a control of the page;
	// past the last control the browser may hold focus itself (the document) before Tab comes back in.
	let trapped = true;
	for ( let i = 0; i < 30; i++ ) {
		await page.keyboard.press( 'Tab' );
		trapped = trapped && await page.evaluate( ( s ) => {
			const active = document.activeElement;
			return ! active || active === document.body || document.querySelector( s ).contains( active );
		}, drawer );
	}
	check( trapped, `NFR-11 ${ where } Tab never leaves the open drawer for the page behind` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 500 );
	const closed = await page.evaluate( ( s ) => ! document.querySelector( s ).open && document.activeElement && document.activeElement.classList.contains( 'mizzey-header__menu' ), drawer );
	check( closed, `NFR-11 ${ where } Escape closes the drawer and focus returns to the menu control` );
	return rows;
}

async function categoriesTab( page, lang, expected ) {
	const where = `[${ lang }, 390]`;
	const tab = page.locator( '.mizzey-tabbar__categories' );
	check( await tab.count() === 1 && await tab.isVisible(), `NAV-10 ${ where } the tab bar has its Categories tab` );
	if ( ! await tab.count() ) {
		return;
	}
	await tab.click();
	await page.waitForTimeout( 400 );
	const shown = tagged( await names( page, '.mizzey-categories:not([hidden]) .mizzey-categories__name' ) );
	check( same( shown, expected.top ) && await tab.getAttribute( 'aria-expanded' ) === 'true', `NAV-10 ${ where } the Categories tab shows the same categories: ${ JSON.stringify( shown ) }` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );
	check( await page.locator( '.mizzey-categories' ).isHidden(), `NFR-11 ${ where } Escape closes the Categories tab` );
}

async function main( browser ) {
	for ( const lang of [ 'en', 'ar' ] ) {
		const L = plan[ lang ];
		const expected = L.expected;
		// ---- 1440 --------------------------------------------------------------------------------------------
		{
			const { context, page, errors } = await open( browser, L.home, 1440 );
			const { where, data } = await entryPoints( page, lang, 1440 );
			check( !! data, `AC-1 ${ where } the page carries the designed header` );
			if ( data ) {
				check( data.dir === ( lang === 'ar' ? 'rtl' : 'ltr' ), `AC-14 ${ where } the page reads ${ lang === 'ar' ? 'right to left' : 'left to right' }` );
				check( data.logo === L.home, `NAV-01 ${ where } the logo leads home (${ data.logo })` );
				check( !! data.search && data.search.action === L.search && data.search.type === 'product' && !! data.search.label, `NAV-03 ${ where } the search field is labelled and searches the products (${ JSON.stringify( data.search ) })` );
				check( !! data.language && data.language.text === L.other.name && data.language.href.includes( `mizzey_language=${ L.other.code }` ), `NAV-02 ${ where } the switcher names the other language in itself and carries the choice (${ JSON.stringify( data.language ) })` );
				check( !! data.account && data.account.href === L.account, `NAV-04 ${ where } the account entry point leads to the account (${ JSON.stringify( data.account ) })` );
				check( !! data.wishlist && data.wishlist.href === L.wishlist, `NAV-05 ${ where } the wishlist entry point leads to the wishlist (${ JSON.stringify( data.wishlist ) })` );
				check( !! data.cart && data.cart.href === L.cart && /\S/.test( data.cart.label ), `NAV-06 ${ where } the cart entry point leads to the cart and is named (${ JSON.stringify( data.cart ) })` );
				check( data.row && ! data.menuControl, `NAV-07 ${ where } the category row shows and the phone's menu control does not` );
			}
			const links = tagged( await names( page, '.mizzey-header__links .mizzey-header__link' ) );
			check( links.includes( expected.offer ) && links.includes( expected.parent ), `SSC-07 ${ where } the category row carries the admin's menu links: ${ JSON.stringify( links ) }` );
			menus[ `${ lang }-mega` ] = await megaMenu( page, lang, expected );
			check( errors.length === 0, `${ where } no script error (${ errors.join( '; ' ).slice( 0, 300 ) })` );
			if ( plan.shots ) {
				await page.screenshot( { path: path.join( plan.shots, `s2-header-${ lang }-1440.png` ), clip: { x: 0, y: 0, width: 1440, height: 200 } } );
			}
			await context.close();
		}
		// ---- 390 ----------------------------------------------------------------------------------------------
		{
			const { context, page, errors } = await open( browser, L.home, 390 );
			const { where, data } = await entryPoints( page, lang, 390 );
			if ( data ) {
				check( data.menuControl && ! data.row && data.chips && !! data.search && !! data.cart && !! data.language, `NAV-08 ${ where } the home page's phone header: menu, logo, language, cart, the search row and the chips` );
				check( data.logo === L.home && data.cart.href === L.cart, `NAV-01, NAV-06 ${ where } the logo and the cart lead where they should` );
			} else {
				check( false, `AC-1 ${ where } the page carries the designed header` );
			}
			menus[ `${ lang }-drawer` ] = await phoneMenu( page, lang, expected );
			await categoriesTab( page, lang, expected );
			check( errors.length === 0, `${ where } no script error (${ errors.join( '; ' ).slice( 0, 300 ) })` );
			await context.close();
		}
		check( same( menus[ `${ lang }-mega` ], menus[ `${ lang }-drawer` ] ) && menus[ `${ lang }-mega` ].length > 0, `NAV-10 [${ lang }] the mega menu and the phone menu show the same structure` );
		// ---- the phone header's other forms ------------------------------------------------------------------
		{
			const { context, page } = await open( browser, L.category, 390 );
			const { where, data } = await entryPoints( page, lang, 390 );
			check( !! data && ! data.chips && !! data.search, `NAV-08, NAV-09 ${ where } a listing's phone header: the search row, no chips` );
			await context.close();
		}
		{
			const { context, page } = await open( browser, L.product, 390 );
			const { where, data } = await entryPoints( page, lang, 390 );
			const toggle = page.locator( '.mizzey-header__search-toggle' );
			let opened = false;
			if ( data && data.searchControl ) {
				await toggle.click();
				await page.waitForTimeout( 300 );
				opened = await page.evaluate( () => {
					const input = document.querySelector( '.mizzey-header form[role="search"] input[name="s"]' );
					return !! input && input.getClientRects().length > 0 && document.activeElement === input;
				} );
			}
			check( !! data && ! data.chips && ! data.search && data.searchControl && opened, `NAV-09 ${ where } a product page's phone header: a search control that opens the field and focuses it` );
			await context.close();
		}
	}

	// ---- The count after an add, without a reload ----------------------------------------------------------------
	{
		const { context, page, errors } = await open( browser, plan.addPage, 1440 );
		const read = () => page.evaluate( () => {
			const c = document.querySelector( '.mizzey-header__cart .mizzey-count' );
			return c && c.getClientRects().length ? Number( c.textContent.trim() ) : 0;
		} );
		// WooCommerce's add to cart script and the header have both bound their handlers.
		await page.waitForFunction( () => {
			const events = window.jQuery && window.jQuery._data( document.body, 'events' );
			return !! events && !! events.click && !! events.added_to_cart;
		}, null, { timeout: TIMEOUT } );
		const before = await read();
		await page.evaluate( () => { window.__s2NoReload = true; } );
		const add = page.locator( plan.addSelector ).first();
		check( await add.count() === 1, `NAV-06 [en, 1440] the page has an add to cart control (${ plan.addSelector })` );
		if ( await add.count() ) {
			await add.click();
			let after = before;
			for ( let i = 0; i < 120 && after === before; i++ ) {
				await page.waitForTimeout( 250 );
				after = await read();
			}
			const kept = await page.evaluate( () => window.__s2NoReload === true );
			const label = await page.evaluate( () => document.querySelector( '.mizzey-header__cart' ).textContent.replace( /\s+/g, ' ' ).trim() );
			check( after === before + 1 && kept, `NAV-06 [en, 1440] the cart's count goes from ${ before } to ${ after } after an add, without a reload (label: ${ label })` );
			check( label.includes( String( after ) ), `NAV-06 [en, 1440] the cart's name says the new count` );
			// The product card's own event (slice 4) carries the server's count: the header shows it as told.
			await page.evaluate( () => document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count: 5 } } ) ) );
			await page.waitForTimeout( 300 );
			const told = await page.evaluate( () => {
				const link = document.querySelector( '.mizzey-header__cart' );
				return { count: link.querySelector( '.mizzey-count' ).textContent.trim(), label: link.querySelector( '.mizzey-header__cart-label' ).textContent.trim() };
			} );
			check( told.count === '5' && told.label.includes( '5' ), `NAV-06 [en, 1440] the card's "mizzey-cart-changed" event sets the count it carries (${ JSON.stringify( told ) })` );
		}
		check( errors.length === 0, `[en, add] no script error (${ errors.join( '; ' ).slice( 0, 300 ) })` );
		// ---- Switching language keeps the page and the cart ----------------------------------------------------
		await page.goto( plan.en.category, { waitUntil: 'load', timeout: TIMEOUT } );
		const cartBefore = await read();
		const langLink = page.locator( '.mizzey-header__language' ).first();
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), langLink.click() ] );
		await page.waitForLoadState( 'load' );
		const landed = page.url();
		const cartAfter = await read();
		check( landed === plan.ar.category && cartAfter === cartBefore && cartBefore > 0, `NAV-02 the switcher leads to the same page in Arabic (${ landed }) and keeps the cart (${ cartBefore } -> ${ cartAfter })` );
		// And back, so the device's choice is English again for what follows.
		const back = page.locator( '.mizzey-header__language' ).first();
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), back.click() ] );
		await context.close();
	}

	// ---- Signed in -------------------------------------------------------------------------------------------------
	if ( plan.customer ) {
		const { context, page } = await open( browser, plan.en.home, 1440, plan.customer.cookies );
		const { data } = await entryPoints( page, 'en', 1440 );
		check( !! data && !! data.account && data.account.name.includes( plan.customer.first ), `NAV-04 [en, 1440, signed in] the account shows the first name (${ data && data.account ? data.account.name : '' })` );
		check( !! data && !! data.language && data.language.href.includes( 'mizzey_language_nonce=' ), 'NAV-02 [en, signed in] the switcher carries the signed-in nonce (D-31, a gift)' );
		await context.close();
		const phone = await open( browser, plan.en.home, 390, plan.customer.cookies );
		await phone.page.locator( '.mizzey-header__menu' ).click();
		await phone.page.waitForTimeout( 500 );
		const tile = await phone.page.evaluate( () => ( document.querySelector( '#mizzey-menu .mizzey-menu__account' ) || {} ).textContent || '' );
		check( tile.includes( plan.customer.first ), `NAV-04 [en, 390, signed in] the drawer's account tile shows the first name (${ tile.trim() })` );
		await phone.context.close();
	}
}

async function after( browser ) {
	for ( const lang of [ 'en', 'ar' ] ) {
		const L = plan[ lang ];
		const desk = await open( browser, L.home, 1440 );
		await desk.page.locator( '.mizzey-header__all' ).click();
		await desk.page.waitForTimeout( 300 );
		const mega = tagged( await names( desk.page, '.mizzey-mega__row .mizzey-mega__name' ) );
		await desk.page.keyboard.press( 'Escape' );
		const row = tagged( await names( desk.page, '.mizzey-header__links .mizzey-header__link' ) );
		await desk.context.close();
		check( same( row, L.expected.row ), `SSC-07 [${ lang }] with a footer menu published later, the category row still carries the header's menu: ${ JSON.stringify( row ) }` );
		const phone = await open( browser, L.home, 390 );
		await phone.page.locator( '.mizzey-header__menu' ).click();
		await phone.page.waitForTimeout( 500 );
		const drawer = tagged( await names( phone.page, '#mizzey-menu .mizzey-menu__level:not([hidden]) .mizzey-menu__row .mizzey-menu__name' ) );
		await phone.context.close();
		check( same( mega, L.expected.after ) && same( drawer, L.expected.after ), `SSC-07 [${ lang }] after the admin's change and a reload, the mega menu and the phone menu both show ${ JSON.stringify( L.expected.after ) } (mega ${ JSON.stringify( mega ) }, drawer ${ JSON.stringify( drawer ) })` );
	}
}

( async () => {
	const found = loadPlaywright();
	if ( ! found ) {
		throw new Error( 'Playwright was not found. Set PLAYWRIGHT_DIR.' );
	}
	const browser = await found.pw.chromium.launch( found.launch );
	try {
		if ( plan.part === 'after' ) {
			await after( browser );
		} else {
			await main( browser );
		}
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks, menus } ) );
} )().catch( ( error ) => {
	console.log( JSON.stringify( { error: String( error && error.stack ? error.stack : error ), checks } ) );
	process.exit( 0 );
} );
