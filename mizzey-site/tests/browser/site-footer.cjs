#!/usr/bin/env node
/**
 * The footer, the announcement strip, the WhatsApp link and the consent banner in a real browser (spec 066, slice 3;
 * AC-2, AC-14). Driven by t109, which prepares the runtime and passes a JSON plan as the only argument; prints one
 * JSON object: { checks: [{ ok, what }] }.
 *
 * In each language, at 1440 and at 390 wide:
 *
 * - the footer carries the style the footer block is set to, and its columns are the menus the block names for that
 *   language, in order, with their links; a menu it does not name never shows;
 * - every link is at least 44 high; a long link wraps inside its column; a phone number stays on one line, left to
 *   right; the ZZ mark sits still in the footer's end corner, unmirrored;
 * - the WhatsApp link carries the configured number and the greeting of the page's language;
 * - the strip is the variant its block is set to: one fixed message, or the slider, which moves on by itself, pauses
 *   on hover and on focus, and stands still under reduced motion;
 * - on a first visit the consent banner is the first thing the keyboard reaches, in the page's language, and covers
 *   nothing: the foot of the page scrolls clear above it. The footer's cookie preferences link opens the preferences,
 *   and a choice made there is recorded by the banner of specs/043.
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
const TIMEOUT = 120000;
const ROTATE = 6000;
const CONSENT = 'mizzey_consent';
// The blocks under test sit in a page's content; the site's own header and footer stay as they are.
const SCOPE = plan.scope || 'body';

/** Open a page; with a consent cookie already set unless the visit is a first one. */
async function open( browser, url, { width, first = false, reduced = false } ) {
	const context = await browser.newContext( {
		viewport: { width, height: width < 600 ? 844 : 900 },
		reducedMotion: reduced ? 'reduce' : 'no-preference',
		deviceScaleFactor: 1,
	} );
	if ( ! first ) {
		const { hostname } = new URL( url );
		await context.addCookies( [ { name: CONSENT, value: 'denied', domain: hostname, path: '/' } ] );
	}
	const page = await context.newPage();
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.evaluate( () => document.fonts.ready );
	return { context, page };
}

/** What the footer shows, measured. */
function readFooter( page ) {
	return page.evaluate( ( scope ) => {
		const footer = document.querySelector( `${ scope } .mizzey-footer` );
		if ( ! footer ) {
			return null;
		}
		const box = ( el ) => {
			const r = el.getBoundingClientRect();
			return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height, right: r.right, bottom: r.bottom + scrollY };
		};
		const links = [ ...footer.querySelectorAll( 'a' ) ].map( ( a ) => ( {
			text: a.textContent.trim().replace( /\s+/g, ' ' ),
			href: a.getAttribute( 'href' ),
			box: box( a ),
			column: a.closest( '.mizzey-footer__column' ) ? box( a.closest( '.mizzey-footer__column' ) ) : null,
			// The lines its words take: the tops of the text's own boxes, not of the elements around it.
			lines: ( () => {
				const tops = new Set();
				const walker = document.createTreeWalker( a, NodeFilter.SHOW_TEXT );
				for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
					if ( ! node.textContent.trim() || node.parentElement.closest( '.mizzey-visually-hidden' ) ) {
						continue;
					}
					const range = document.createRange();
					range.selectNodeContents( node );
					[ ...range.getClientRects() ].forEach( ( r ) => tops.add( Math.round( r.top ) ) );
				}
				return tops.size;
			} )(),
			dir: getComputedStyle( a.querySelector( '[dir]' ) || a ).direction,
			opener: a.hasAttribute( 'data-mizzey-consent-open' ),
			whatsapp: a.classList.contains( 'mizzey-footer__whatsapp' ),
		} ) );
		const zz = footer.querySelector( '.mizzey-footer__zz' );
		const style = getComputedStyle( footer );
		return {
			classes: footer.className,
			background: style.backgroundImage + ' ' + style.backgroundColor,
			headings: [ ...footer.querySelectorAll( '.mizzey-footer__heading' ) ].map( ( h ) => h.textContent.trim() ),
			columns: [ ...footer.querySelectorAll( '.mizzey-footer__column' ) ].map( ( c ) => ( { heading: ( c.querySelector( '.mizzey-footer__heading' ) || {} ).textContent || '', box: box( c ) } ) ),
			links,
			legal: ( footer.querySelector( '.mizzey-footer__legal' ) || {} ).textContent || '',
			box: box( footer ),
			zz: zz ? { box: box( zz ), transform: getComputedStyle( zz ).transform, animation: getComputedStyle( zz.querySelector( 'svg' ) || zz ).animationName, opacity: getComputedStyle( zz ).opacity } : null,
			dir: document.documentElement.dir || getComputedStyle( document.body ).direction,
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	}, SCOPE );
}

/** The strip's state. */
function readStrip( page ) {
	return page.evaluate( ( scope ) => {
		const strip = document.querySelector( `${ scope } .mizzey-announcement` );
		if ( ! strip ) {
			return null;
		}
		const messages = [ ...strip.querySelectorAll( '.mizzey-announcement__message' ) ];
		const visible = messages.filter( ( m ) => {
			const r = m.getBoundingClientRect();
			const s = strip.getBoundingClientRect();
			return ! m.hidden && getComputedStyle( m ).visibility !== 'hidden' && r.height > 0 && r.top >= s.top - 1 && r.bottom <= s.bottom + 1;
		} );
		const pause = strip.querySelector( '.mizzey-announcement__pause' );
		return {
			classes: strip.className,
			count: messages.length,
			visible: visible.map( ( m ) => m.querySelector( '.mizzey-announcement__text' ).textContent.trim() ),
			current: ( strip.querySelector( '.mizzey-announcement__message.is-current .mizzey-announcement__text' ) || {} ).textContent || '',
			pause: pause ? pause.getAttribute( 'aria-label' ) : null,
			hiddenFocusable: messages.filter( ( m ) => ! m.classList.contains( 'is-current' ) ).some( ( m ) => ! m.inert && m.querySelector( 'a' ) ),
		};
	}, SCOPE );
}

async function waitForChange( page, from, ms ) {
	const end = Date.now() + ms;
	while ( Date.now() < end ) {
		const now = ( await readStrip( page ) ).current;
		if ( now && now !== from ) {
			return now;
		}
		await page.waitForTimeout( 250 );
	}
	return from;
}

async function footerChecks( browser, lang, width ) {
	const where = `[${ lang } ${ width }]`;
	const { context, page } = await open( browser, plan[ lang ], { width } );
	const f = await readFooter( page );
	check( f, `SSC-08 ${ where } the designed footer is on the page` );
	if ( ! f ) {
		await context.close();
		return;
	}
	check( f.classes.includes( `mizzey-footer--${ plan.style }` ), `SSC-08 ${ where } the footer is the ${ plan.style } style its block is set to (${ f.classes })` );
	check( 'indigo' === plan.style ? /gradient/.test( f.background ) : /rgb\(255, 255, 255\)/.test( f.background ), `SSC-08 ${ where } the ${ plan.style } footer's ground: ${ f.background }` );
	const want = plan.columns[ lang ];
	// In reading order: along the line on desktop (from the right in Arabic), down the page on a phone.
	const reading = [ ...f.columns ]
		.sort( ( a, b ) => ( width >= 1024 ? ( 'ar' === lang ? b.box.right - a.box.right : a.box.x - b.box.x ) || a.box.y - b.box.y : a.box.y - b.box.y ) )
		.map( ( c ) => c.heading.trim() )
		.filter( ( h ) => want.includes( h ) );
	const expected = width >= 1024 ? want : [ want[ 3 ], ...want.slice( 0, 3 ) ];
	check( JSON.stringify( reading ) === JSON.stringify( expected ), `SSC-08 SSC-12 ${ where } the columns are the menus named for this language, in order: ${ JSON.stringify( reading ) }` );
	const texts = f.links.map( ( l ) => l.text );
	const missing = plan.links[ lang ].filter( ( t ) => ! texts.includes( t ) );
	check( 0 === missing.length, `SSC-08 ${ where } each column's links are its menu's links${ missing.length ? ', missing ' + JSON.stringify( missing ) : '' }` );
	const other = plan.links[ 'en' === lang ? 'ar' : 'en' ].filter( ( t ) => ! /^[+a-z0-9@.\s]+$/i.test( t ) && texts.includes( t ) );
	check( 0 === other.length && ! texts.includes( plan.unnamed ), `SSC-12 ${ where } no link of the other language's menus or of a menu the footer does not name` );
	const short = f.links.filter( ( l ) => l.box.h < 43.5 );
	check( 0 === short.length, `NFR-11 ${ where } every footer link is at least 44 high${ short.length ? ': ' + JSON.stringify( short.map( ( l ) => [ l.text, l.box.h ] ) ) : '' }` );
	const outside = f.links.filter( ( l ) => l.column && ( l.box.x < l.column.x - 0.5 || l.box.right > l.column.right + 0.5 ) );
	check( 0 === outside.length, `B8 ${ where } every link stays inside its column${ outside.length ? ': ' + JSON.stringify( outside.map( ( l ) => l.text ) ) : '' }` );
	if ( 'en' === lang ) {
		const long = f.links.find( ( l ) => l.text === plan.long );
		check( long && long.lines > 1, `B8 ${ where } the long link wraps onto ${ long ? long.lines : 0 } lines inside its column` );
	}
	const phone = f.links.find( ( l ) => l.href && l.href.startsWith( 'tel:' ) );
	check( phone && 1 === phone.lines && 'ltr' === phone.dir, `F1 ${ where } the phone number stays on one line, left to right (${ phone ? phone.lines + ' ' + phone.dir : 'none' })` );
	const chat = f.links.find( ( l ) => l.whatsapp );
	const greeting = new URL( plan.whatsapp[ lang ] ).searchParams.get( 'text' );
	check( chat && chat.href === plan.whatsapp[ lang ] && chat.href.startsWith( `https://wa.me/${ plan.whatsapp.number }` ) && new URL( chat.href ).searchParams.get( 'text' ) === greeting, `CMS-10 ${ where } the WhatsApp link opens a chat with the configured number and this language's greeting: ${ chat ? chat.href : 'none' }` );
	check( f.legal.trim() === plan.legal[ lang ], `SSC-08 ${ where } the legal line is this language's: ${ f.legal.trim() }` );
	check( ( 'ar' === lang ) === ( 'rtl' === f.dir ), `NFR-04 ${ where } the page reads ${ f.dir }` );
	check( f.overflow <= 0, `K-2 ${ where } nothing in the page runs wider than the screen (${ f.overflow })` );
	if ( f.zz ) {
		const end = 'ar' === lang ? f.zz.box.x <= f.box.x + f.box.w * 0.25 : f.zz.box.right >= f.box.x + f.box.w * 0.75;
		check( end && f.zz.box.bottom >= f.box.bottom - 2 && 'none' === f.zz.transform && 'none' === f.zz.animation, `ZZ ${ where } the ZZ mark sits still in the footer's end corner, unmirrored (${ JSON.stringify( f.zz ) })` );
	} else {
		check( false, `ZZ ${ where } the ZZ mark is in the footer` );
	}
	if ( width < 1024 ) {
		const care = f.columns.findIndex( ( c ) => c.heading.trim() === want[ 3 ] );
		const shop = f.columns.findIndex( ( c ) => c.heading.trim() === want[ 0 ] );
		check( care >= 0 && shop >= 0 && f.columns[ care ].box.y < f.columns[ shop ].box.y, `F1 ${ where } on the phone Customer care comes before Shop` );
		const shopLinks = f.links.filter( ( l ) => plan.links[ lang ].slice( 0, 2 ).includes( l.text ) );
		check( 2 === shopLinks.length && Math.abs( shopLinks[ 0 ].box.y - shopLinks[ 1 ].box.y ) < 2 && shopLinks[ 0 ].box.x !== shopLinks[ 1 ].box.x, `F1 ${ where } Shop sets its links in two columns on the phone` );
	} else {
		const tops = new Set( f.columns.slice( 0, 4 ).map( ( c ) => Math.round( c.box.y ) ) );
		check( 1 === tops.size, `F1 ${ where } the columns stand side by side on desktop` );
	}
	const footer = page.locator( `${ SCOPE } .mizzey-footer` );
	await footer.screenshot( { path: path.join( plan.shots, `s3-footer-${ plan.style }-${ lang }-${ width }.png` ) } );
	await context.close();
}

async function stripChecks( browser, lang, width ) {
	const where = `[${ lang } ${ width }]`;
	const { context, page } = await open( browser, plan[ lang ], { width } );
	const s = await readStrip( page );
	check( s, `G11 ${ where } the announcement strip is on the page` );
	if ( ! s ) {
		await context.close();
		return;
	}
	check( s.classes.includes( `mizzey-announcement--${ plan.variant }` ), `G11 ${ where } the strip is the ${ plan.variant } variant its block is set to (${ s.classes })` );
	check( 1 === s.visible.length && plan.messages[ lang ].includes( s.visible[ 0 ] ), `SSC-12 ${ where } one message shows, in this language: ${ JSON.stringify( s.visible ) }` );
	if ( 'fixed' === plan.variant ) {
		check( null === s.pause && 1 === s.count && s.visible[ 0 ] === plan.messages[ lang ][ 0 ], `G11 ${ where } the fixed strip carries its first message and no pause control` );
	} else {
		check( s.count === plan.messages[ lang ].length && null !== s.pause, `G11 ${ where } the slider carries every message (${ s.count }) and a pause control (${ s.pause })` );
		check( ! s.hiddenFocusable, `NFR-11 ${ where } a message that is not showing cannot be reached by the keyboard` );
	}
	await page.locator( `${ SCOPE } .mizzey-announcement` ).screenshot( { path: path.join( plan.shots, `s3-strip-${ plan.variant }-${ lang }-${ width }.png` ) } );
	if ( 'slider' === plan.variant && 'en' === lang && width >= 1024 ) {
		const first = s.current;
		const next = await waitForChange( page, first, ROTATE + 2500 );
		check( next !== first, `G11 the slider moves on by itself (${ first } then ${ next })` );
		await page.hover( `${ SCOPE } .mizzey-announcement` );
		const held = ( await readStrip( page ) ).current;
		await page.waitForTimeout( ROTATE + 1500 );
		check( ( await readStrip( page ) ).current === held, 'G11 the slider pauses while the pointer is over it' );
		await page.mouse.move( 5, 600 );
		await page.locator( `${ SCOPE } .mizzey-announcement__pause` ).focus();
		const focused = ( await readStrip( page ) ).current;
		await page.waitForTimeout( ROTATE + 1500 );
		check( ( await readStrip( page ) ).current === focused, 'G11 the slider pauses while it holds the keyboard focus' );
		await page.locator( `${ SCOPE } .mizzey-announcement__pause` ).press( 'Enter' );
		check( /play/i.test( ( await readStrip( page ) ).pause || '' ), `NFR-11 the pause control is pressed by keyboard and offers to play again (${ ( await readStrip( page ) ).pause })` );
	}
	await context.close();
	if ( 'slider' === plan.variant && 'en' === lang && width >= 1024 ) {
		const still = await open( browser, plan[ lang ], { width, reduced: true } );
		const start = ( await readStrip( still.page ) ).current;
		await still.page.waitForTimeout( ROTATE + 1500 );
		check( ( await readStrip( still.page ) ).current === start, 'G11 under reduced motion the slider stands still' );
		await still.context.close();
	}
}

async function consentChecks( browser, lang, width ) {
	const where = `[${ lang } ${ width }]`;
	const { context, page } = await open( browser, plan[ lang ], { width, first: true } );
	await page.locator( '#mizzey-consent' ).waitFor( { state: 'visible', timeout: 10000 } ).catch( () => {} );
	const banner = await page.evaluate( () => {
		const el = document.getElementById( 'mizzey-consent' );
		return el ? { shown: ! el.hidden && el.getBoundingClientRect().height > 0, text: el.textContent.trim(), lang: el.closest( '[lang]' ) ? el.closest( '[lang]' ).getAttribute( 'lang' ) : '' } : null;
	} );
	check( banner && banner.shown, `MKT-10 ${ where } on a first visit the consent banner shows` );
	check( banner && ( 'ar' === lang ? /[؀-ۿ]/.test( banner.text ) : /cookies/i.test( banner.text ) ), `MKT-10 ${ where } the banner speaks the page's language` );
	await page.keyboard.press( 'Tab' );
	const first = await page.evaluate( () => ( document.activeElement && document.activeElement.closest( '#mizzey-consent' ) ? document.activeElement.textContent.trim() || document.activeElement.getAttribute( 'aria-label' ) : ( document.activeElement || {} ).outerHTML ) );
	const inBanner = await page.evaluate( () => !! ( document.activeElement && document.activeElement.closest( '#mizzey-consent' ) ) );
	check( inBanner, `MKT-10 NFR-11 ${ where } the first Tab reaches the consent banner (${ String( first ).slice( 0, 120 ) })` );
	await page.evaluate( () => window.scrollTo( 0, document.documentElement.scrollHeight ) );
	await page.waitForTimeout( 400 );
	const covered = await page.evaluate( () => {
		const lines = document.querySelectorAll( '.mizzey-footer__legal' );
		const legal = lines[ lines.length - 1 ];
		const bar = document.getElementById( 'mizzey-consent' );
		if ( ! legal || ! bar ) {
			return 'missing';
		}
		const l = legal.getBoundingClientRect();
		const b = bar.getBoundingClientRect();
		return l.bottom <= b.top + 0.5 ? '' : `legal ${ Math.round( l.top ) }-${ Math.round( l.bottom ) }, banner from ${ Math.round( b.top ) }`;
	} );
	check( '' === covered, `MKT-10 ${ where } the banner covers no content: the foot of the page scrolls clear above it ${ covered }` );
	await page.screenshot( { path: path.join( plan.shots, `s3-consent-${ lang }-${ width }.png` ) } );
	if ( width >= 1024 ) {
		// The footer's own cookie preferences link opens the preferences; Accept all there is recorded as the banner's choice.
		const opener = page.locator( `${ SCOPE } .mizzey-footer a[data-mizzey-consent-open]` ).first();
		check( ( await opener.count() ) > 0, `MKT-10 ${ where } the footer's cookie preferences link reopens the choice` );
		check( 0 === ( await page.locator( `${ SCOPE } .mizzey-cookie-settings` ).count() ), `MKT-10 ${ where } with the preferences link in a column, the fallback "Cookie settings" link is not printed as well` );
		if ( await opener.count() ) {
			await opener.click();
			const dialog = page.locator( '#mizzey-consent-preferences' );
			await dialog.waitFor( { state: 'visible', timeout: 5000 } ).catch( () => {} );
			const state = await page.evaluate( () => {
				const d = document.getElementById( 'mizzey-consent-preferences' );
				if ( ! d ) {
					return null;
				}
				const sw = ( name ) => d.querySelector( `[data-mizzey-consent-category="${ name }"]` );
				return {
					open: d.open,
					necessary: sw( 'necessary' ) && sw( 'necessary' ).getAttribute( 'aria-checked' ) === 'true' && sw( 'necessary' ).disabled,
					analytics: sw( 'analytics' ) && sw( 'analytics' ).getAttribute( 'aria-checked' ),
					marketing: sw( 'marketing' ) && sw( 'marketing' ).getAttribute( 'aria-checked' ),
				};
			} );
			check( state && state.open && state.necessary && 'false' === state.analytics && 'false' === state.marketing, `MKT-10 ${ where } the preferences open: Necessary always on, Analytics and Marketing off (${ JSON.stringify( state ) })` );
			await page.waitForTimeout( 500 );
			await page.screenshot( { path: path.join( plan.shots, `s3-preferences-${ lang }-${ width }.png` ) } );
			if ( 'en' === lang ) {
				await dialog.locator( '[data-mizzey-consent-choice="granted"]' ).click();
			} else {
				await dialog.locator( '[data-mizzey-consent-category="analytics"]' ).click();
				await dialog.locator( '[data-mizzey-consent-choice="save"]' ).click();
			}
			await page.waitForTimeout( 300 );
			const cookie = ( await context.cookies() ).find( ( c ) => c.name === CONSENT );
			const after = await page.evaluate( () => ( { banner: document.getElementById( 'mizzey-consent' ).hidden, dialog: document.getElementById( 'mizzey-consent-preferences' ).open } ) );
			const expect = 'en' === lang ? 'granted' : 'denied';
			check( cookie && cookie.value === expect && after.banner && ! after.dialog, `MKT-10 ${ where } ${ 'en' === lang ? 'Accept all' : 'Save choices with Analytics alone' } is recorded as "${ expect }" (${ cookie ? cookie.value : 'no cookie' }), and the banner and preferences close` );
		}
	} else {
		const widths = await page.evaluate( () => [ ...document.querySelectorAll( '#mizzey-consent [data-mizzey-consent]' ) ].map( ( b ) => Math.round( b.getBoundingClientRect().width ) ) );
		check( 2 === widths.length && Math.abs( widths[ 0 ] - widths[ 1 ] ) <= 1, `B3 ${ where } on the phone the two choices are of equal width (${ widths })` );
	}
	await context.close();
}

( async () => {
	const loaded = loadPlaywright();
	if ( ! loaded ) {
		throw new Error( 'Playwright was not found' );
	}
	const browser = await loaded.pw.chromium.launch( loaded.launch );
	try {
		for ( const lang of [ 'en', 'ar' ] ) {
			for ( const width of [ 1440, 390 ] ) {
				await footerChecks( browser, lang, width );
				await stripChecks( browser, lang, width );
				if ( 1 === plan.run ) {
					await consentChecks( browser, lang, width );
				}
			}
		}
	} finally {
		await browser.close();
	}
	process.stdout.write( JSON.stringify( { checks } ) + '\n' );
} )().catch( ( error ) => {
	process.stdout.write( JSON.stringify( { error: String( error && error.stack ? error.stack : error ), checks } ) + '\n' );
} );
