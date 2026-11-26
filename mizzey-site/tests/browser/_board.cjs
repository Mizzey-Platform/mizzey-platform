/**
 * The closed boards' numbers for the header, the footer and the home page (spec 066, the staging review of
 * 25 November 2026), checked in a real browser at 390 (touch) and 1440, in English and Arabic, in Chromium and in
 * WebKit (the owner tests on an iPhone). Shared by site-header.cjs (t108), site-footer.cjs (t109) and
 * home-sections.cjs (t110), each of which calls board() with its own pages: { en, ar } addresses.
 *
 * Every number is read from the page board (C1-Home-Phone, C1-Home-Phone-Arabic, C1-Home-Desktop; C1-Menus-Desktop
 * for the mega menu; C1-Motion-Playground for motion). A number is within 1 px of the board, a colour exact.
 */

const { loadPlaywright } = require( './_theme-preview.cjs' );

const TIMEOUT = 120000;
const INDIGO = 'rgb(38, 59, 115)';
const RING = 'rgb(123, 132, 160)';

/** The boards' numbers, by part, width and language. */
const BOARD = {
	header: {
		390: {
			'search height': 44, 'search words from the field edge': 44, 'search icon': 20,
			'tab item width': 93.5, 'tab item height': 56,
			'tab mark width': 6.4, 'tab mark height': 5.5,
		},
		1440: {
			'search height': 48, 'search words from the field edge': 48, 'search icon': 24,
			'search under the bar top': 12, 'search over the bar foot': 12,
		},
		type: {
			en: { 'tab label size': 12, 'tab label line': 16, 'tab label tracking': 0.12, 'tab icon from the item top': 4, 'chip line': 20 },
			ar: { 'tab label size': 13, 'tab label line': 18, 'tab label tracking': 0, 'tab icon from the item top': 3, 'chip line': 24 },
		},
	},
	footer: {
		390: { 'gap above the footer': 40, 'tagged row height': 48, 'tag height': 18 },
		1440: { 'gap above the footer': 72, 'tagged row height': 48, 'tag height': 18 },
		type: {
			en: { 'language link size': 17, 'language link font': 'Tajawal', 'strip line': 20 },
			ar: { 'language link size': 16, 'language link font': 'Inter', 'strip line': 24 },
		},
	},
	home: {
		390: {
			'hero to trust': 32, 'rail heading row': 48, 'rail head to card': 16, 'view all height': 44,
			'all brands height': 44, 'collection chevron': 16,
		},
		1440: {
			'hero controls to trust': 36, 'rail heading row': 48, 'rail head to card': 24, 'view all height': 44,
			'all brands height': 44, 'collection chevron': 20, 'hero arrow icon': 24, 'rail arrow icon': 24,
		},
		type: {
			en: { 'h2 tracking em': -0.02 },
			ar: { 'h2 tracking em': 0 },
		},
		// The deals heading row (48) and its line under it on a phone: 24 in English, Tajawal's 28 in Arabic.
		deals: {
			en: { 'deals heading row and line': 72 },
			ar: { 'deals heading row and line': 76 },
		},
	},
};

/* ---- Measures, run in the page --------------------------------------------------------------------------------- */

function measureHeader( width ) {
	const r = ( el ) => el.getBoundingClientRect();
	const rtl = getComputedStyle( document.documentElement ).direction === 'rtl';
	const out = {};
	const field = document.querySelector( '.mizzey-header__search' );
	const input = document.querySelector( '.mizzey-header__search-input' );
	const icon = document.querySelector( '.mizzey-header__search-icon' );
	const bar = document.querySelector( '.mizzey-header__bar' );
	out[ 'search height' ] = r( field ).height;
	out[ 'search words from the field edge' ] = rtl ? r( field ).right - r( input ).right : r( input ).left - r( field ).left;
	out[ 'search icon' ] = r( icon ).width;
	out[ 'search ring' ] = getComputedStyle( field ).boxShadow;
	if ( width > 1000 ) {
		out[ 'search under the bar top' ] = r( field ).top - r( bar ).top;
		out[ 'search over the bar foot' ] = r( bar ).bottom - r( field ).bottom;
		const tip = document.querySelector( '.mizzey-header__actions .mizzey-tooltip-host .mizzey-tooltip' );
		const control = tip && tip.parentElement.firstElementChild;
		out[ 'tooltip above its control' ] = !! tip && r( tip ).bottom <= r( control ).top;
		const more = document.querySelector( '.mizzey-header__more' );
		out[ 'row shows every link' ] = !! more && more.hidden && document.querySelectorAll( '.mizzey-header__links > .mizzey-header__item' ).length > 0;
	} else {
		const item = document.querySelector( '.mizzey-tabbar__item' );
		const svg = item.querySelector( '.mizzey-tabbar__icon svg' );
		const label = item.querySelector( '.mizzey-tabbar__label' );
		const dot = document.querySelector( '.mizzey-tabbar__item[aria-current="page"] .mizzey-tabbar__dot' );
		const cs = getComputedStyle( label );
		out[ 'tab item width' ] = r( item ).width;
		out[ 'tab item height' ] = r( item ).height;
		out[ 'tab icon from the item top' ] = r( svg ).top - r( item ).top;
		out[ 'tab label size' ] = parseFloat( cs.fontSize );
		out[ 'tab label line' ] = Math.round( parseFloat( cs.lineHeight ) );
		out[ 'tab label tracking' ] = cs.letterSpacing === 'normal' ? 0 : Math.round( parseFloat( cs.letterSpacing ) * 100 ) / 100;
		out[ 'tab shadow' ] = getComputedStyle( document.querySelector( '.mizzey-tabbar' ) ).boxShadow;
		out[ 'tab mark width' ] = dot ? Math.round( r( dot ).width * 10 ) / 10 : null;
		out[ 'tab mark height' ] = dot ? Math.round( r( dot ).height * 10 ) / 10 : null;
		out[ 'tab mark shown' ] = !! dot && getComputedStyle( dot ).visibility === 'visible';
		// The chips' words: 13/20 in English, Tajawal on a 24 line in Arabic (C1-Home-Phone-Arabic).
		const chip = document.querySelector( '.mizzey-header__chips .mizzey-chip' );
		out[ 'chip line' ] = chip ? Math.round( parseFloat( getComputedStyle( chip ).lineHeight ) ) : null;
	}
	return out;
}

function measureFooter( scope ) {
	const r = ( el ) => el.getBoundingClientRect();
	const out = {};
	const part = document.querySelector( '.wp-block-template-part:has(> .mizzey-footer)' );
	out[ 'gap above the footer' ] = part ? parseFloat( getComputedStyle( part ).paddingTop ) : null;
	const root = ( scope && document.querySelector( scope ) ) || document;
	const lang = root.querySelector( '.mizzey-footer__language' );
	out[ 'language link size' ] = lang ? parseFloat( getComputedStyle( lang ).fontSize ) : null;
	out[ 'language link font' ] = lang ? getComputedStyle( lang ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ).trim() : null;
	const tagged = root.querySelector( '.mizzey-footer__link--tagged' );
	out[ 'tagged row height' ] = tagged ? r( tagged ).height : null;
	out[ 'tag height' ] = tagged ? r( tagged.querySelector( '.mizzey-footer__tag' ) ).height : null;
	// The strip's words: on a 20 line in English, Tajawal on a 24 line in Arabic (C1-Home-Phone-Arabic).
	const strip = root.querySelector( '.mizzey-announcement__text' ) || document.querySelector( '.mizzey-announcement__text' );
	out[ 'strip line' ] = strip ? Math.round( parseFloat( getComputedStyle( strip ).lineHeight ) ) : null;
	return out;
}

function measureHome( width ) {
	const r = ( el ) => ( el ? el.getBoundingClientRect() : { top: NaN, bottom: NaN, height: NaN, width: NaN } );
	const of = ( type ) => document.querySelector( `[data-mizzey-section="${ type }"]` );
	const out = {};
	const hero = of( 'hero' );
	const trust = document.querySelector( '.mizzey-trust' );
	if ( hero && trust ) {
		const list = trust.querySelector( '.mizzey-trust__list' );
		out[ 'hero to trust' ] = Math.round( ( r( list ).top - r( hero.querySelector( '.mizzey-hero__viewport' ) ).bottom ) * 2 ) / 2;
		out[ 'hero controls to trust' ] = Math.round( ( r( list.querySelector( '.mizzey-trust__mark' ) ).top - r( hero.querySelector( '.mizzey-hero__controls' ) ).bottom ) * 2 ) / 2;
		const line = trust.querySelector( 'a.mizzey-trust__line' );
		out[ 'trust line link' ] = line && getComputedStyle( line ).display !== 'none' ? `${ getComputedStyle( line ).color } ${ getComputedStyle( line ).textDecorationLine }` : null;
	}
	const rail = of( 'products' );
	if ( rail ) {
		const head = rail.querySelector( '.mizzey-products__head' );
		out[ 'rail heading row' ] = r( head ).height;
		out[ 'rail head to card' ] = Math.round( r( rail.querySelector( '.mizzey-card' ) ).top - r( head ).bottom );
		out[ 'view all height' ] = r( rail.querySelector( '.mizzey-products__all' ) ).height;
		const arrow = rail.querySelector( '.mizzey-products__arrow svg' );
		out[ 'rail arrow icon' ] = arrow ? r( arrow ).width : null;
		const h2 = rail.querySelector( 'h2' );
		const cs = getComputedStyle( h2 );
		out[ 'h2 tracking em' ] = cs.letterSpacing === 'normal' ? 0 : Math.round( ( parseFloat( cs.letterSpacing ) / parseFloat( cs.fontSize ) ) * 1000 ) / 1000;
	}
	if ( hero ) {
		const arrow = hero.querySelector( '.mizzey-hero__arrow svg' );
		out[ 'hero arrow icon' ] = arrow ? r( arrow ).width : null;
		const well = hero.querySelector( '.mizzey-hero__slide.is-active .mizzey-hero__media .mizzey-zz' );
		out[ 'zz still once the photo is in' ] = !! well && well.getAnimations().length === 0;
	}
	const brands = of( 'brand-highlights' );
	if ( brands ) {
		out[ 'all brands height' ] = r( brands.querySelector( '.mizzey-home__all' ) ).height;
	}
	const deals = of( 'deals' );
	if ( deals ) {
		const strong = deals.querySelector( '.mizzey-deals__line strong' );
		out[ 'deals figure bold' ] = strong ? `${ strong.textContent } ${ getComputedStyle( strong ).fontWeight }` : null;
		out[ 'deals heading row and line' ] = r( deals.querySelector( '.mizzey-deals__head' ) ).height;
		out[ 'deals corner tags' ] = deals.querySelectorAll( '.mizzey-corner-tag, .mizzey-card__tag' ).length;
	}
	const colls = of( 'featured-collections' );
	if ( colls ) {
		const chevron = colls.querySelector( '.mizzey-collections__name svg' );
		out[ 'collection chevron' ] = chevron ? r( chevron ).width : null;
		out[ 'collection chevron colour' ] = chevron ? getComputedStyle( chevron ).color : null;
	}
	out[ 'page glows' ] = getComputedStyle( document.body ).backgroundImage.split( 'radial-gradient' ).length - 1;
	return out;
}

/* ---- The runner ----------------------------------------------------------------------------------------------- */

async function contexts( pw, engine, width ) {
	const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
	const phone = width < 600;
	const ctx = await browser.newContext( phone
		? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: engine !== 'firefox', deviceScaleFactor: 2 }
		: { viewport: { width: 1440, height: 900 } } );
	// The consent bar is answered, so it covers nothing being measured.
	return { browser, ctx };
}

async function load( page, url, { reveal = true } = {} ) {
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.evaluate( () => document.fonts.ready );
	if ( reveal ) {
		// Every section revealed (the scroll reveal) and settled before anything is measured.
		await page.evaluate( async () => {
			for ( let y = 0; y < document.documentElement.scrollHeight; y += 400 ) {
				window.scrollTo( 0, y );
				await new Promise( ( resolve ) => setTimeout( resolve, 40 ) );
			}
			window.scrollTo( 0, 0 );
		} );
	}
	await page.waitForTimeout( 900 );
	// header-lazy.js (the sticky tuck, "More", the row's underline) arrives after the load; a test server can be slow.
	await page.waitForFunction( () => ! document.querySelector( '.mizzey-header__row' ) || document.querySelector( '.mizzey-header__row.has-indicator' ), null, { timeout: 30000 } ).catch( () => {} );
}

/**
 * Run one part's checks.
 *
 * @param {string} part   header | footer | home
 * @param {{en:string, ar:string, scope?:string, consent?:{name:string,value:string}}} urls
 * @return {Promise<Array<{ok:boolean, what:string}>>}
 */
async function board( part, urls ) {
	const pw = loadPlaywright();
	const checks = [];
	const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
	const near = ( got, want ) => typeof got === 'number' && Math.abs( got - want ) <= 1;
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 390, 1440 ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const tag = `[${ engine } ${ lang } ${ width }]`;
				const { browser, ctx } = await contexts( pw, engine, width );
				try {
					if ( urls.consent ) {
						await ctx.addCookies( [ { name: urls.consent.name, value: urls.consent.value, url: new URL( urls[ lang ] ).origin } ] );
					}
					const page = await ctx.newPage();
					await load( page, urls[ lang ] );
					const want = { ...BOARD[ part ][ width ], ...( width > 1000 && part === 'header' ? {} : BOARD[ part ].type[ lang ] ), ...( part === 'home' && width < 600 ? BOARD.home.deals[ lang ] : {} ) };
					if ( part === 'header' && width > 1000 ) {
						delete want[ 'tab label size' ];
					}
					const got = await page.evaluate( part === 'header' ? measureHeader : part === 'footer' ? measureFooter : measureHome, part === 'footer' ? urls.scope || '' : width );
					for ( const [ key, value ] of Object.entries( want ) ) {
						if ( part === 'header' && width > 1000 && key.startsWith( 'tab ' ) ) {
							continue;
						}
						if ( part === 'home' && key === 'h2 tracking em' ) {
							check( Math.abs( ( got[ key ] ?? NaN ) - value ) <= 0.002, `K-1 ${ tag } section headings tracked ${ value }em: ${ got[ key ] }` );
							continue;
						}
						check( typeof value === 'string' ? got[ key ] === value : near( got[ key ], value ), `K-1 ${ tag } ${ key }: ${ got[ key ] } against the board's ${ value }` );
					}
					if ( part === 'header' ) {
						await headerMore( page, { width, tag, check, engine } );
					} else if ( part === 'home' ) {
						homeMore( got, { width, tag, check } );
						await heroMotion( page, { width, tag, check } );
					}
				} catch ( error ) {
					check( false, `K-1 ${ tag } ${ part }: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
	if ( part === 'header' ) {
		await moreList( pw, urls, check );
		if ( urls.pages ) {
			await sharedParts( pw, urls, check );
		}
	}
	if ( part === 'home' ) {
		await revealChecks( pw, urls, check );
	}
	if ( part === 'footer' ) {
		await consentChecks( pw, urls, check );
		await preferencesOnPhones( pw, urls, check );
		await noBandUnderFooter( pw, urls, check );
	}
	await tapChecks( pw, part, urls, check );
	return checks;
}

/*
 * What a finger taps, per part: [ selector, toggles ]. A toggle (it opens or closes something) changes its own look on
 * purpose, so only the ring is checked; a link must look as it did before the tap.
 */
const TAPPED = {
	header: [
		[ '.mizzey-announcement__link', false, true ], [ '.mizzey-header__logo', false ], [ '.mizzey-header__language', false ],
		[ '.mizzey-header__bar .mizzey-button--icon', true ], [ '.mizzey-chip--category', false ],
		[ 'a.mizzey-tabbar__item', false ], [ 'button.mizzey-tabbar__item', true ],
	],
	footer: [
		[ 'a.mizzey-footer__link', false ], [ '.mizzey-footer__language', false ], [ '.mizzey-footer__logo', false ],
		[ 'button.mizzey-footer__link, .mizzey-footer__button', true ], [ '.mizzey-consent__preferences', true ], [ '.mizzey-consent__choice', true ],
	],
	home: [
		[ '.mizzey-hero__button', false ], [ '.mizzey-shortcuts__link', false ], [ '.mizzey-products__all', false ],
		[ '.mizzey-collections__link', false ],
		[ '.mizzey-brands__link', false ], [ '.mizzey-offer__button', false, true ], [ '.mizzey-home__all', false ],
	],
};

/**
 * No ring and no stuck highlight after a tap (owner rule, 26 November 2026; he tests on an iPhone): in WebKit at 390
 * with touch, each control is tapped where a finger would; after it nothing matches :focus-visible, and a link keeps
 * the colours it had before the tap (its hover and pressed colours do not stay). Links do not navigate during the check.
 */
async function tapChecks( pw, part, urls, check ) {
	const looks = ( el ) => {
		const cs = getComputedStyle( el );
		return [ cs.color, cs.backgroundColor, cs.borderColor, cs.boxShadow, cs.outlineStyle, cs.transform ].join( ' | ' );
	};
	for ( const lang of [ 'en', 'ar' ] ) {
		const browser = await pw.pw.webkit.launch( { headless: true } );
		try {
			const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 } );
			const consent = part === 'footer' ? null : urls.consent;
			if ( consent ) {
				await ctx.addCookies( [ { name: consent.name, value: consent.value, url: new URL( urls[ lang ] ).origin } ] );
			}
			const page = await ctx.newPage();
			for ( const [ selector, toggles, optional = false ] of TAPPED[ part ] || [] ) {
				const tag = `[webkit ${ lang } 390 touch] ${ selector }`;
				try {
					await load( page, urls[ lang ], { reveal: false } );
					await page.evaluate( () => document.addEventListener( 'click', ( event ) => {
						if ( event.target.closest && event.target.closest( 'a[href]' ) ) {
							event.preventDefault();
						}
					} ) );
					const target = page.locator( selector ).filter( { visible: true } ).first();
					if ( ! await target.count() ) {
						// The strip's link shows only when the message has one.
						check( optional, `K-7 ${ tag }: nothing to tap${ optional ? ' (shown only when set)' : '' }` );
						continue;
					}
					await target.scrollIntoViewIfNeeded();
					await page.waitForTimeout( 400 );
					const before = await target.evaluate( looks );
					await target.tap();
					await page.waitForTimeout( 600 );
					const ring = await page.evaluate( () => {
						const el = document.querySelector( ':focus-visible' );
						return el ? `${ el.tagName.toLowerCase() }.${ String( el.className ).split( ' ' )[ 0 ] }` : null;
					} );
					const after = { ring, looks: await target.evaluate( looks ).catch( () => before ) };
					const stuck = ! toggles && after.looks !== before;
					check( ! after.ring && ! stuck, `K-7 ${ tag }: after the tap no ring (${ after.ring || 'none' })${ toggles ? '' : ' and no colour stays' }${ stuck ? `: ${ before } -> ${ after.looks }` : '' }` );
				} catch ( error ) {
					check( false, `K-7 ${ tag }: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				}
			}
		} finally {
			await browser.close();
		}
	}
}

/**
 * Where the row still overflows (narrower desktops, and Arabic), "More" opens its list whole, outside the row's
 * clipping: every item is the element at its own centre. Escape and a press outside close it (the owner's report,
 * 25 November 2026: the list opened clipped to a sliver).
 */
async function moreList( pw, urls, check ) {
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 1100, 1280 ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const tag = `[${ engine } ${ lang } ${ width }]`;
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const ctx = await browser.newContext( { viewport: { width, height: 900 } } );
					if ( urls.consent ) {
						await ctx.addCookies( [ { name: urls.consent.name, value: urls.consent.value, url: new URL( urls[ lang ] ).origin } ] );
					}
					const page = await ctx.newPage();
					await load( page, urls[ lang ] );
					const button = page.locator( '.mizzey-header__more:not([hidden]) .mizzey-header__more-button' );
					if ( ! await button.count() ) {
						// Everything fits at this width: then every link of the row is whole and visible.
						const row = await page.evaluate( () => {
							const links = [ ...document.querySelectorAll( '.mizzey-header__links > .mizzey-header__item > a' ) ];
							const cut = links.filter( ( a ) => {
								const box = a.getBoundingClientRect();
								const at = document.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 );
								return ! ( at && ( at === a || a.contains( at ) ) ) || box.right > document.documentElement.clientWidth;
							} ).map( ( a ) => a.textContent.trim() );
							return { count: links.length, cut };
						} );
						check( width > 1200 && row.count > 0 && row.cut.length === 0, `K-1 ${ tag } no "More" at this width: every link of the row is visible (${ row.count } links, cut: ${ JSON.stringify( row.cut ) })` );
						continue;
					}
					await button.click();
					await page.waitForTimeout( 300 );
					const seen = await page.evaluate( () => {
						const links = [ ...document.querySelectorAll( '#mizzey-header-more a' ) ];
						const hidden = links.filter( ( a ) => {
							const box = a.getBoundingClientRect();
							const at = document.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 );
							return ! ( at && ( at === a || a.contains( at ) ) );
						} ).map( ( a ) => a.textContent.trim() );
						const list = document.getElementById( 'mizzey-header-more' ).getBoundingClientRect();
						const more = document.querySelector( '.mizzey-header__more-button' ).getBoundingClientRect();
						return { count: links.length, hidden, expanded: document.querySelector( '.mizzey-header__more-button' ).getAttribute( 'aria-expanded' ), under: Math.round( list.top - more.bottom ), endGap: Math.round( getComputedStyle( document.documentElement ).direction === 'rtl' ? list.left - more.left : more.right - list.right ) };
					} );
					check( seen.count > 0 && seen.hidden.length === 0 && seen.expanded === 'true', `K-1 ${ tag } "More" opens its list whole, every item visible (${ seen.count } items, hidden: ${ JSON.stringify( seen.hidden ) }, expanded ${ seen.expanded })` );
					check( seen.under >= 0 && seen.under <= 8 && Math.abs( seen.endGap ) <= 1, `K-1 ${ tag } the list sits under "More", end edges aligned: ${ seen.under } under, ${ seen.endGap } off` );
					// By keyboard: Enter on "More" opens it, Escape closes it and the focus is back on "More". (A mouse press
					// does not focus a button in WebKit, so the keyboard is what the return is checked with.)
					await page.mouse.click( 20, 600 );
					await page.waitForTimeout( 100 );
					await button.focus();
					await page.keyboard.press( 'Enter' );
					await page.waitForTimeout( 250 );
					await page.keyboard.press( 'Escape' );
					await page.waitForTimeout( 100 );
					const afterEscape = await page.evaluate( () => ( { open: document.getElementById( 'mizzey-header-more' ).matches( ':popover-open' ), focus: document.activeElement && document.activeElement.classList.contains( 'mizzey-header__more-button' ) } ) );
					await button.click();
					await page.waitForTimeout( 250 );
					await page.mouse.click( 20, 600 );
					await page.waitForTimeout( 100 );
					const afterOutside = await page.evaluate( () => document.getElementById( 'mizzey-header-more' ).matches( ':popover-open' ) );
					check( ! afterEscape.open && afterEscape.focus && ! afterOutside, `K-1 ${ tag } Escape closes the list and returns the focus to "More"; a press outside closes it (${ JSON.stringify( afterEscape ) }, open after outside: ${ afterOutside })` );
				} catch ( error ) {
					check( false, `K-1 ${ tag } "More": stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
}

/**
 * The scroll reveal (the owner's ruling of 25 November 2026): a section below the first screen fades in and rises
 * 12 px once, in 320 ms, with frames between; nothing on the first screen moves; a rail's cards follow 30 ms apart;
 * under reduced motion everything shows at once.
 */
async function revealChecks( pw, urls, check ) {
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 390, 1440 ] ) {
			for ( const calm of [ false, true ] ) {
				const tag = `[${ engine } en ${ width }${ calm ? ' reduced motion' : '' }]`;
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const phone = width < 600;
					const ctx = await browser.newContext( { viewport: { width, height: phone ? 844 : 900 }, hasTouch: phone, isMobile: phone, reducedMotion: calm ? 'reduce' : 'no-preference' } );
					if ( urls.consent ) {
						await ctx.addCookies( [ { name: urls.consent.name, value: urls.consent.value, url: new URL( urls.en ).origin } ] );
					}
					const page = await ctx.newPage();
					await load( page, urls.en, { reveal: false } );
					const first = await page.evaluate( () => {
						const sections = [ ...document.querySelectorAll( '.mizzey-section' ) ];
						const onScreen = sections.filter( ( el ) => el.getBoundingClientRect().top < window.innerHeight );
						const below = sections.filter( ( el ) => el.getBoundingClientRect().top >= window.innerHeight );
						return {
							marked: document.documentElement.classList.contains( 'mizzey-reveal' ),
							onScreenStill: onScreen.every( ( el ) => ! el.hasAttribute( 'data-reveal' ) && getComputedStyle( el ).opacity === '1' ),
							onScreen: onScreen.length,
							belowHidden: below.filter( ( el ) => getComputedStyle( el ).opacity === '0' ).length,
							below: below.length,
						};
					} );
					if ( calm ) {
						check( ! first.marked && first.belowHidden === 0, `K-5 ${ tag } nothing waits to be revealed: every section shows at once (${ first.belowHidden } of ${ first.below } hidden)` );
						continue;
					}
					check( first.marked && first.onScreen > 0 && first.onScreenStill && first.below > 0 && first.belowHidden === first.below, `K-1 ${ tag } the first screen does not move; the sections below it wait (${ first.onScreen } on screen, ${ first.belowHidden } of ${ first.below } waiting)` );
					// A rail below the fold, brought into view: sampled frame by frame.
					const seen = await page.evaluate( async () => {
						const target = [ ...document.querySelectorAll( '.mizzey-section' ) ].find( ( el ) => el.getAttribute( 'data-reveal' ) === 'waiting' && el.querySelector( '.mizzey-products__item' ) )
							|| [ ...document.querySelectorAll( '.mizzey-section' ) ].find( ( el ) => el.getAttribute( 'data-reveal' ) === 'waiting' );
						if ( ! target ) {
							return null;
						}
						const frames = [];
						let timing = null;
						let delays = [];
						const midway = [];
						target.scrollIntoView( { block: 'center' } );
						const t0 = performance.now();
						while ( performance.now() - t0 < 700 ) {
							await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
							frames.push( Number( getComputedStyle( target ).opacity ) );
							const anim = target.getAnimations()[ 0 ];
							if ( anim && ! timing ) {
								timing = anim.effect.getTiming().duration;
								// The fade read at a tenth, a quarter and half of its time (it eases out), the animation held
								// still: a machine under load draws few frames, the curve is the same.
								anim.pause();
								const at = anim.currentTime;
								for ( const part of [ 0.1, 0.25, 0.5 ] ) {
									anim.currentTime = timing * part;
									midway.push( Number( getComputedStyle( target ).opacity ) );
								}
								anim.currentTime = at;
								anim.play();
								delays = [ ...target.querySelectorAll( '.mizzey-products__item' ) ].slice( 0, 7 ).map( ( el ) => ( el.getAnimations()[ 0 ] ? el.getAnimations()[ 0 ].effect.getTiming().delay : null ) );
							}
						}
						// Away and back: it does not play again.
						window.scrollTo( 0, 0 );
						await new Promise( ( resolve ) => setTimeout( resolve, 200 ) );
						target.scrollIntoView( { block: 'center' } );
						await new Promise( ( resolve ) => setTimeout( resolve, 120 ) );
						return { frames, timing, delays, midway, again: target.getAnimations().filter( ( a ) => a.playState === 'running' ).length, end: Number( getComputedStyle( target ).opacity ) };
					} );
					if ( ! seen ) {
						check( false, `K-1 ${ tag } a section below the first screen to reveal` );
						continue;
					}
					const m = seen.midway;
					const gradual = m.length === 3 && m[ 0 ] > 0.05 && m[ 0 ] < 0.95 && m[ 0 ] < m[ 1 ] && m[ 1 ] <= m[ 2 ];
					check( seen.timing === 320 && gradual && seen.end === 1, `K-1 ${ tag } a section below the fold fades in and rises once, 320 ms, gradually (${ seen.timing } ms, at a tenth, a quarter and half ${ JSON.stringify( m.map( ( o ) => Math.round( o * 100 ) / 100 ) ) }, ends at ${ seen.end })` );
					const want = [ 0, 30, 60, 90, 120, 150, 150, 150 ].slice( 0, seen.delays.length );
					check( JSON.stringify( seen.delays ) === JSON.stringify( want ), `K-1 ${ tag } the rail's cards follow 30 ms apart, the first six only: ${ JSON.stringify( seen.delays ) }` );
					check( seen.again === 0, `K-1 ${ tag } scrolled away and back, the section does not reveal again` );
				} catch ( error ) {
					check( false, `K-1 ${ tag } reveal: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
}

/**
 * The consent bar on a first visit and the cookie preferences (C1-Global B3): the bar 16 inside, corners 20, its two
 * choices 44 high (equal halves on a phone), its link a 44 target in medium; 76 high and 24 from the foot from 1440;
 * the preferences a bottom sheet with its 40 by 4 handle on a phone and a 560 dialog with corners 24 from 600 wide, a
 * 52 head, the choices 44 high.
 */
/**
 * No empty band under the footer (the owner's report, 26 November 2026): at the end of the page, before and after the
 * cookie choice, the footer is the last thing painted (nothing under it), its last row is clear above whatever is fixed
 * at the screen's foot (the cookie bar, the phone's tab bar), and once the bar is answered the room it took is gone.
 */
async function noBandUnderFooter( pw, urls, check ) {
	const end = () => {
		const footer = document.querySelector( '.wp-block-template-part > .mizzey-footer' );
		const foot = [ ...footer.querySelectorAll( '.mizzey-footer__foot' ) ].pop().getBoundingClientRect();
		const consent = document.getElementById( 'mizzey-consent' );
		const tabs = [ ...document.querySelectorAll( '.mizzey-tabbar' ) ].find( ( t ) => t.getBoundingClientRect().height > 0 );
		const fixed = [ consent && ! consent.hidden ? consent : null, tabs ].filter( Boolean ).map( ( e ) => e.getBoundingClientRect().top );
		const top = fixed.length ? Math.min( ...fixed ) : window.innerHeight;
		return {
			under: Math.round( document.documentElement.scrollHeight - ( window.scrollY + footer.getBoundingClientRect().bottom ) ),
			bodyPad: Math.round( parseFloat( getComputedStyle( document.body ).paddingBottom ) ),
			clear: Math.round( top - foot.bottom ),
			bar: !! ( consent && ! consent.hidden ),
			tabs: tabs ? Math.round( tabs.getBoundingClientRect().height ) : 0,
		};
	};
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 390, 1440 ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const tag = `[${ engine } ${ lang } ${ width }]`;
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const phone = width < 600;
					const ctx = await browser.newContext( { viewport: { width, height: phone ? 844 : 900 }, hasTouch: phone, isMobile: phone } );
					const page = await ctx.newPage();
					await load( page, urls[ lang ], { reveal: false } );
					const bottom = async () => {
						// At once, and again once what the first scroll loaded has settled (the page grows as images arrive).
						for ( let i = 0; i < 2; i++ ) {
							await page.evaluate( () => window.scrollTo( { top: document.documentElement.scrollHeight, behavior: 'instant' } ) );
							await page.waitForTimeout( 600 );
						}
						return page.evaluate( end );
					};
					const before = await bottom();
					check( before.bar && 0 === before.under && 0 === before.bodyPad && before.clear >= 0, `K-1 ${ tag } with the cookie bar showing, the footer ends the page (nothing under it, ${ before.under }; the body keeps no room, ${ before.bodyPad }) and its last row is clear above the bar (${ before.clear })` );
					await page.locator( '#mizzey-consent .mizzey-consent__choice' ).last().click();
					await page.waitForTimeout( 500 );
					const after = await bottom();
					const drawn = phone ? 16 : 0;
					check( ! after.bar && 0 === after.under && 0 === after.bodyPad && after.clear >= 0 && Math.abs( after.clear - drawn ) <= 1, `K-1 ${ tag } after the choice, the bar's room is gone: the footer ends the page (${ after.under }), its last row ${ drawn } above ${ phone ? 'the tab bar' : "the window's foot" } as drawn (${ after.clear })` );
				} catch ( error ) {
					check( false, `K-1 ${ tag } no band under the footer: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
}

async function consentChecks( pw, urls, check ) {
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 390, 1440 ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const tag = `[${ engine } ${ lang } ${ width }]`;
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const phone = width < 600;
					const ctx = await browser.newContext( { viewport: { width, height: phone ? 844 : 900 }, hasTouch: phone, isMobile: phone } );
					const page = await ctx.newPage();
					await load( page, urls[ lang ], { reveal: false } );
					const bar = await page.evaluate( () => {
						const c = document.getElementById( 'mizzey-consent' );
						if ( ! c || c.hidden ) {
							return null;
						}
						const cs = getComputedStyle( c );
						const b = c.getBoundingClientRect();
						const buttons = [ ...c.querySelectorAll( '.mizzey-consent__choices button' ) ].map( ( x ) => x.getBoundingClientRect() );
						const link = c.querySelector( '.mizzey-consent__preferences' );
						return {
							pad: cs.padding, radius: cs.borderRadius, height: Math.round( b.height * 10 ) / 10, fromFoot: Math.round( window.innerHeight - b.bottom ),
							buttons: buttons.map( ( x ) => [ Math.round( x.width * 10 ) / 10, Math.round( x.height ) ] ),
							link: [ Math.round( link.getBoundingClientRect().height ), getComputedStyle( link ).fontWeight ],
						};
					} );
					if ( ! bar ) {
						check( false, `K-1 ${ tag } the consent bar shows on a first visit` );
						continue;
					}
					const even = bar.buttons.length === 2 && bar.buttons.every( ( x ) => x[ 1 ] === 44 ) && ( ! phone || Math.abs( bar.buttons[ 0 ][ 0 ] - bar.buttons[ 1 ][ 0 ] ) <= 1 );
					check( bar.pad === '16px' && bar.radius === '20px' && even && bar.link[ 0 ] === 44 && bar.link[ 1 ] === '500', `K-1 ${ tag } the consent bar as drawn: 16 inside, corners 20, choices 44 high${ phone ? ' in equal halves' : '' }, its link a 44 target in medium (${ JSON.stringify( bar ) })` );
					if ( phone ) {
						// Two lines of words over the two choices: 129.5 in English, 132 in Arabic (C1-Global B3).
						const tall = lang === 'ar' ? 132 : 129.5;
						check( Math.abs( bar.height - tall ) <= 1, `K-1 ${ tag } the bar is ${ tall } high, as drawn (${ bar.height })` );
					}
					if ( ! phone && lang === 'en' ) {
						check( bar.height === 76 && bar.fromFoot === 24, `K-1 ${ tag } the bar is 76 high, 24 from the window's foot (${ bar.height }, ${ bar.fromFoot })` );
					}
					await page.locator( '#mizzey-consent .mizzey-consent__preferences' ).click();
					await page.waitForTimeout( 700 );
					const prefs = await page.evaluate( () => {
						const panel = document.querySelector( '.mizzey-consent-preferences .mizzey-overlay__panel' );
						const r = ( e ) => ( e ? e.getBoundingClientRect() : { width: 0, height: 0 } );
						const handle = panel.querySelector( '.mizzey-consent-preferences__handle' );
						return {
							width: Math.round( r( panel ).width ), radius: getComputedStyle( panel ).borderRadius,
							handle: [ Math.round( r( handle ).width ), Math.round( r( handle ).height ) ],
							head: Math.round( r( panel.querySelector( '.mizzey-overlay__header' ) ).height ),
							buttons: [ ...panel.querySelectorAll( '.mizzey-overlay__footer .mizzey-button' ) ].map( ( b ) => Math.round( r( b ).height ) ),
						};
					} );
					const sheet = phone ? prefs.handle[ 0 ] === 40 && prefs.handle[ 1 ] === 4 && prefs.radius.startsWith( '28px 28px 0px 0px' ) : prefs.width === 560 && prefs.radius === '24px' && prefs.handle[ 0 ] === 0;
					check( sheet && prefs.head === 52 && prefs.buttons.length === 3 && prefs.buttons.every( ( h ) => h === 44 ), `K-1 ${ tag } the cookie preferences as drawn: ${ phone ? 'a bottom sheet with its handle' : 'a 560 dialog' }, a 52 head, three choices 44 high (${ JSON.stringify( prefs ) })` );
				} catch ( error ) {
					check( false, `K-1 ${ tag } consent: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
}

/**
 * The cookie preferences on short phone screens (the owner's iPhone with Safari's bars shows about 428 by 740): the
 * first tap on "Cookie preferences", in the bar and in the footer, opens them; the panel stays inside the screen; the
 * three choices show without scrolling; the body scrolls to its last switch. 390 by 664 and 428 by 740, with touch.
 */
async function preferencesOnPhones( pw, urls, check ) {
	const inView = () => {
		const panel = document.querySelector( '.mizzey-consent-preferences .mizzey-overlay__panel' );
		const r = panel.getBoundingClientRect();
		const hit = ( el ) => {
			const b = el.getBoundingClientRect();
			const at = document.elementFromPoint( b.left + b.width / 2, b.top + b.height / 2 );
			return b.top >= 0 && b.bottom <= window.innerHeight + 0.5 && !! at && ( at === el || el.contains( at ) );
		};
		const buttons = [ ...panel.querySelectorAll( '.mizzey-overlay__footer .mizzey-button' ) ];
		return { top: Math.round( r.top ), bottom: Math.round( r.bottom ), height: window.innerHeight, buttons: buttons.length, buttonsShown: buttons.filter( hit ).length };
	};
	const lastSwitch = async () => {
		const body = document.querySelector( '.mizzey-consent-preferences .mizzey-overlay__body' );
		body.scrollTop = body.scrollHeight;
		await new Promise( ( resolve ) => setTimeout( resolve, 150 ) );
		const toggles = [ ...body.querySelectorAll( '[data-mizzey-consent-category]' ) ];
		const last = toggles[ toggles.length - 1 ];
		const b = last.getBoundingClientRect();
		const box = body.getBoundingClientRect();
		const at = document.elementFromPoint( b.left + b.width / 2, b.top + b.height / 2 );
		return { toggles: toggles.length, reachable: b.top >= box.top - 0.5 && b.bottom <= box.bottom + 0.5 && !! at && ( at === last || last.contains( at ) ) };
	};
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const [ width, height ] of [ [ 390, 664 ], [ 428, 740 ] ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const tag = `[${ engine } ${ lang } ${ width }x${ height } touch]`;
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const ctx = await browser.newContext( { viewport: { width, height }, hasTouch: true, isMobile: true } );
					const page = await ctx.newPage();
					await load( page, urls[ lang ], { reveal: false } );
					const open = () => page.evaluate( () => !! document.getElementById( 'mizzey-consent-preferences' ) && document.getElementById( 'mizzey-consent-preferences' ).open );
					// From the bar: one tap.
					const bar = page.locator( '#mizzey-consent .mizzey-consent__preferences' );
					const bb = await bar.boundingBox();
					await page.touchscreen.tap( bb.x + bb.width / 2, bb.y + bb.height / 2 );
					await page.waitForTimeout( 600 );
					const fromBar = await open();
					const shown = fromBar ? await page.evaluate( inView ) : null;
					const scrolled = fromBar ? await page.evaluate( lastSwitch ) : null;
					check( fromBar, `MKT-10 ${ tag } the first tap on the bar's "Cookie preferences" opens the preferences` );
					check( !! shown && shown.top >= 0 && shown.bottom <= shown.height + 0.5 && shown.buttons === 3 && shown.buttonsShown === 3, `MKT-10 ${ tag } the preferences stay inside the screen and their three choices show without scrolling (${ JSON.stringify( shown ) })` );
					check( !! scrolled && scrolled.toggles >= 3 && scrolled.reachable, `MKT-10 ${ tag } the body scrolls to the last switch (${ JSON.stringify( scrolled ) })` );
					await page.keyboard.press( 'Escape' );
					await page.waitForTimeout( 400 );
					// From the footer: one tap.
					const opener = page.locator( '.mizzey-footer [data-mizzey-consent-open]:visible' ).first();
					await opener.scrollIntoViewIfNeeded();
					await page.waitForTimeout( 300 );
					const fb = await opener.boundingBox();
					await page.touchscreen.tap( fb.x + fb.width / 2, fb.y + fb.height / 2 );
					await page.waitForTimeout( 600 );
					check( await open(), `MKT-10 ${ tag } the first tap on the footer's cookie link opens the preferences` );
				} catch ( error ) {
					check( false, `MKT-10 ${ tag } preferences on a phone: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
				} finally {
					await browser.close();
				}
			}
		}
	}
}

/**
 * One header and one footer on every page (the owner's report, 26 November 2026; SHARED PARTS): on each page type,
 * whatever WooCommerce or the blocks load there, the logos, the strip, the footer (its height, its logo, its
 * watermark, its columns) are the home page's; the header differs only in the form its page board draws: the home
 * page and not-found with chips, product, cart and checkout compact (C1-Product-Phone, 52), every other page with the
 * search row and no chips (C1-Category-Phone, 128).
 */
async function sharedParts( pw, urls, check ) {
	const measure = () => {
		const r = ( e ) => ( e ? [ Math.round( e.getBoundingClientRect().width ), Math.round( e.getBoundingClientRect().height ) ] : null );
		const head = document.querySelector( '.mizzey-header' );
		const foot = document.querySelector( '.wp-block-template-part > .mizzey-footer' );
		const grid = foot && foot.querySelector( '.mizzey-footer__grid' );
		return {
			form: head ? ( [ ...head.classList ].find( ( c ) => c.startsWith( 'mizzey-header--form-' ) ) || '' ).replace( 'mizzey-header--form-', '' ) : null,
			bar: r( head && head.querySelector( '.mizzey-header__bar' ) ),
			logo: ( () => {
				const logo = head && head.querySelector( '.mizzey-header__logo img, .mizzey-header__logo-text' );
				const box = r( logo );
				// A text logo (no logo file) of a long store name is cut to the room its form leaves: then its height counts.
				return box && logo.scrollWidth > logo.clientWidth + 1 ? [ 'cut', box[ 1 ] ] : box;
			} )(),
			strip: r( document.querySelector( '.mizzey-announcement' ) ),
			// The footer less the room it keeps for what is fixed at the screen's foot (the tab bar is on some pages only).
			footer: ( () => {
				const box = r( foot );
				const tabs = [ ...document.querySelectorAll( '.mizzey-tabbar' ) ].find( ( t ) => t.getBoundingClientRect().height > 0 );
				const room = Math.max( tabs ? tabs.getBoundingClientRect().height : 0, parseFloat( getComputedStyle( document.body ).getPropertyValue( '--mizzey-consent-space' ) ) || 0 );
				return box && [ box[ 0 ], Math.round( box[ 1 ] - room ) ];
			} )(),
			footerLogo: r( foot && foot.querySelector( '.mizzey-footer__logo img, .mizzey-footer__logo-text' ) ),
			zz: r( foot && foot.querySelector( '.mizzey-footer__zz' ) ),
			columns: grid ? getComputedStyle( grid ).gridTemplateColumns : null,
			// The marks of where the page is (C1-Category-Phone, C1-Category-Desktop, C1-Product-Desktop).
			marked: [ ...document.querySelectorAll( '.mizzey-header__links .mizzey-header__link[aria-current]' ) ].map( ( a ) => {
				const cs = getComputedStyle( a );
				const box = a.getBoundingClientRect();
				const sliding = document.querySelector( '.mizzey-header__row.has-indicator .mizzey-header__indicator' );
				const bar = getComputedStyle( sliding || a, sliding ? null : '::after' );
				let place = bar.bottom;
				if ( sliding ) {
					const b = sliding.getBoundingClientRect();
					const under = Math.abs( b.left - box.left ) <= 1 && Math.abs( b.width - box.width ) <= 1 && '1' === bar.opacity;
					place = `${ Math.round( box.bottom - b.bottom ) }px${ under ? '' : ` (not under it: ${ Math.round( b.left ) } ${ Math.round( b.width ) } ${ bar.opacity })` }`;
				}
				return { path: new URL( a.href ).pathname.replace( /\/$/, '' ), shown: box.width > 0, look: `${ cs.fontWeight } ${ cs.color } bar ${ bar.blockSize } ${ bar.backgroundColor } ${ bar.borderRadius } ${ place }` };
			} ),
			tab: ( () => {
				const tab = document.querySelector( '.mizzey-tabbar__item[aria-current]' );
				if ( ! tab || ! tab.getBoundingClientRect().width ) {
					return null;
				}
				const dot = tab.querySelector( '.mizzey-tabbar__dot' );
				const on = tab.querySelector( '.mizzey-tabbar__on' );
				return `${ [ ...tab.classList ].find( ( c ) => c !== 'mizzey-tabbar__item' ) } ${ getComputedStyle( tab ).color } dot ${ dot ? getComputedStyle( dot ).visibility : '' } filled ${ on ? getComputedStyle( on ).display !== 'none' : true }`;
			} )(),
			path: location.pathname.replace( /\/$/, '' ),
		};
	};
	const formOf = ( type ) => ( [ 'home', '404' ].includes( type ) ? 'home' : [ 'product', 'cart', 'checkout' ].includes( type ) ? 'compact' : 'listing' );
	const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b ) || ( Array.isArray( a ) && Array.isArray( b ) && a.every( ( v, i ) => Math.abs( v - b[ i ] ) <= 1 ) );
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		for ( const width of [ 390, 1440 ] ) {
			for ( const lang of [ 'en', 'ar' ] ) {
				const browser = engine === 'webkit' ? await pw.pw.webkit.launch( { headless: true } ) : await pw.pw.chromium.launch( pw.launch );
				try {
					const phone = width < 600;
					const ctx = await browser.newContext( phone ? { viewport: { width, height: 844 }, hasTouch: true, isMobile: true } : { viewport: { width, height: 900 } } );
					if ( urls.consent ) {
						await ctx.addCookies( [ { name: urls.consent.name, value: urls.consent.value, url: new URL( urls[ lang ] ).origin } ] );
					}
					const page = await ctx.newPage();
					await load( page, urls[ lang ], { reveal: false } );
					const home = await page.evaluate( measure );
					for ( const [ type, url ] of Object.entries( urls.pages[ lang ] || {} ) ) {
						const tag = `[${ engine } ${ lang } ${ width }] ${ type }`;
						try {
							await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
							await page.waitForFunction( () => ! document.querySelector( '.mizzey-header__row' ) || document.querySelector( '.mizzey-header__row.has-indicator' ), null, { timeout: 30000 } ).catch( () => {} );
							await page.waitForTimeout( 500 );
							const got = await page.evaluate( measure );
							const form = formOf( type );
							const bar = phone ? { home: home.bar[ 1 ], compact: 52, listing: 128 }[ form ] : 72;
							const diffs = [ 'logo', 'strip', 'footer', 'footerLogo', 'zz', 'columns' ].filter( ( k ) => ! same( home[ k ], got[ k ] ) ).map( ( k ) => `${ k } ${ JSON.stringify( home[ k ] ) } -> ${ JSON.stringify( got[ k ] ) }` );
							check( got.form === form && Math.abs( got.bar[ 1 ] - bar ) <= 1 && diffs.length === 0, `SSC-07 SSC-08 ${ tag }: the shared header (${ got.form }, ${ got.bar[ 1 ] } against the board's ${ form } ${ bar }) and footer, logos and watermark as on the home page${ diffs.length ? ': ' + diffs.join( '; ' ) : '' }` );
							// Desktop: the row marks the section the page sits under, bold Royal Indigo over a 3 bar 4 above the
							// link's foot, on a category (its own link) and a product (its category's); nowhere else.
							if ( ! phone ) {
								const want = [ 'category', 'product' ].includes( type ) ? 1 : 0;
								const look = `700 ${ INDIGO } bar 3px ${ INDIGO } 3px 4px`;
								const right = got.marked.length === want && got.marked.every( ( m ) => m.look === look ) && ( type !== 'category' || got.marked[ 0 ].path === got.path );
								check( right, `K-1 ${ tag }: the row marks ${ want ? 'the section the page sits under' : 'nothing' } (C1-Category-Desktop, C1-Product-Desktop): ${ JSON.stringify( got.marked ) }` );
							} else {
								// Phone: the Categories tab is the place shown on a category (C1-Category-Phone), Royal Indigo, filled,
								// its i-dot under it; on every other inner page no tab is marked (C1-Search-Phone, C1-404-Phone).
								const want = 'category' === type ? `mizzey-tabbar__categories ${ INDIGO } dot visible filled true` : null;
								check( got.tab === want, `K-1 ${ tag }: the tab bar marks ${ want ? 'Categories' : 'no tab' }: ${ got.tab }` );
							}
						} catch ( error ) {
							check( false, `SSC-07 ${ tag }: stopped, ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
						}
					}
				} finally {
					await browser.close();
				}
			}
		}
	}
}

/* ---- The header's states and motion -------------------------------------------------------------------------- */

/**
 * The row's underline (the owner's design addition, 26 November 2026): it follows the pointer from item to item on
 * the soft spring (450 ms), lies 4 above the item's foot as wide as the item, follows keyboard focus too, leaves with
 * the pointer (the home page has no item of its own), and does not slide under reduced motion.
 */
async function underlineSlides( page, { tag, check } ) {
	const links = page.locator( '.mizzey-header__links > .mizzey-header__item > .mizzey-header__link' );
	const bar = () => page.evaluate( () => {
		const el = document.querySelector( '.mizzey-header__indicator' );
		const b = el.getBoundingClientRect();
		const cs = getComputedStyle( el );
		return { x: Math.round( b.left ), w: Math.round( b.width ), bottom: Math.round( b.bottom ), opacity: cs.opacity, easing: cs.transitionTimingFunction, duration: cs.transitionDuration };
	} );
	const item = ( i ) => links.nth( i ).evaluate( ( a ) => {
		const r = a.getBoundingClientRect();
		return { x: Math.round( r.left ), w: Math.round( r.width ), bottom: Math.round( r.bottom ) };
	} );
	const on = ( u, b ) => '1' === u.opacity && Math.abs( u.x - b.x ) <= 1 && Math.abs( u.w - b.w ) <= 1 && Math.abs( b.bottom - u.bottom - 4 ) <= 1;
	await page.mouse.move( 5, 880 );
	await links.nth( 1 ).hover();
	await page.waitForTimeout( 700 );
	const first = await bar();
	const b1 = await item( 1 );
	await links.nth( 2 ).hover();
	await page.waitForTimeout( 90 );
	const moving = await bar();
	await page.waitForTimeout( 700 );
	const second = await bar();
	const b2 = await item( 2 );
	check( on( first, b1 ) && on( second, b2 ) && moving.x !== second.x && /linear\(/.test( second.easing ) && second.duration.startsWith( '0.45s' ), `K-1 ${ tag } the row's underline slides after the pointer on the soft spring, 4 above the item's foot and as wide as it (${ JSON.stringify( { first, b1, moving, second, b2 } ) })` );
	await page.mouse.move( 5, 880 );
	await page.waitForTimeout( 500 );
	const gone = await bar();
	check( '0' === gone.opacity, `K-1 ${ tag } with no item of its own (the home page), the underline leaves with the pointer (${ gone.opacity })` );
	await links.nth( 3 ).focus();
	await page.waitForTimeout( 700 );
	const focused = await bar();
	const b3 = await item( 3 );
	check( on( focused, b3 ), `K-1 ${ tag } keyboard focus moves the underline too (${ JSON.stringify( { focused, b3 } ) })` );
	await page.evaluate( () => document.activeElement && document.activeElement.blur() );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	const still = await bar();
	await page.emulateMedia( { reducedMotion: 'no-preference' } );
	check( /^0s/.test( still.duration ), `K-1 ${ tag } under reduced motion the underline moves without sliding (${ still.duration })` );
}

async function headerMore( page, { width, tag, check, engine } ) {
	const got = await page.evaluate( measureHeader, width );
	check( got[ 'search ring' ].includes( RING ), `K-1 ${ tag } the search field's ring is Indigo 300 (#7B84A0): ${ got[ 'search ring' ] }` );
	if ( width > 1000 ) {
		check( got[ 'tooltip above its control' ], `K-1 ${ tag } the header's tooltips sit above their controls` );
		check( got[ 'row shows every link' ], `K-1 ${ tag } the category row shows every link of the menu, no "More"` );
		// The mega menu: rows 48, 4 apart; it drops 8 px and fades in 320 ms; its columns follow 30 ms apart.
		await page.locator( '.mizzey-header__all' ).click();
		await page.waitForTimeout( 40 );
		const mega = await page.evaluate( () => {
			const root = document.getElementById( 'mizzey-mega' );
			const rows = [ ...root.querySelectorAll( '.mizzey-mega__row' ) ];
			const anim = root.getAnimations()[ 0 ];
			const frames = anim ? anim.effect.getKeyframes() : [];
			const delays = [ ...root.querySelectorAll( '.mizzey-mega__panel:not([hidden]) > *' ) ].map( ( el ) => ( el.getAnimations()[ 0 ] ? el.getAnimations()[ 0 ].effect.getTiming().delay : null ) );
			const inner = getComputedStyle( root.querySelector( '.mizzey-mega__inner' ) );
			return {
				height: rows[ 0 ] ? rows[ 0 ].getBoundingClientRect().height : null,
				pitch: rows[ 1 ] ? rows[ 1 ].getBoundingClientRect().top - rows[ 0 ].getBoundingClientRect().top : 52,
				top: parseFloat( inner.paddingTop ),
				duration: anim ? anim.effect.getTiming().duration : null,
				from: frames[ 0 ] ? `${ frames[ 0 ].opacity } ${ frames[ 0 ].transform }` : '',
				delays,
			};
		} );
		check( Math.abs( mega.height - 48 ) <= 1 && Math.abs( mega.pitch - 52 ) <= 1 && Math.abs( mega.top - 24 ) <= 1, `K-1 ${ tag } mega menu rows ${ mega.height } high, ${ mega.pitch } apart, ${ mega.top } under its top (board 48, 52, 24)` );
		check( mega.duration === 320 && /0 .*translateY\(-8px\)|0 .*matrix\(1, 0, 0, 1, 0, -8\)/.test( mega.from ), `K-1 ${ tag } the mega menu opens with an 8 px drop and a fade in 320 ms: ${ mega.duration } ${ mega.from }` );
		check( mega.delays.length > 0 && mega.delays[ 0 ] === 30 && ( mega.delays.length < 2 || mega.delays[ 1 ] === 60 ), `K-1 ${ tag } the mega menu's columns follow 30 ms apart: ${ JSON.stringify( mega.delays ) }` );
		await page.keyboard.press( 'Escape' );
		await underlineSlides( page, { tag, check } );
	} else {
		check( got[ 'tab shadow' ].includes( '-2px 6px 2px' ) && got[ 'tab shadow' ].includes( '-1px 2px' ), `K-1 ${ tag } the tab bar casts level 2 upward: ${ got[ 'tab shadow' ] }` );
		check( got[ 'tab mark shown' ], `K-1 ${ tag } the active tab carries the orange i-dot` );
		// A press shows on pointerdown, within 100 ms, before the finger lifts.
		const pressed = await page.evaluate( async () => {
			const scale = ( el ) => {
				const t = getComputedStyle( el ).transform;
				return t === 'none' ? 1 : Number( new DOMMatrix( t ).a.toFixed( 3 ) );
			};
			const out = {};
			for ( const [ name, sel ] of [ [ 'chip', '.mizzey-header__chips .mizzey-chip' ], [ 'tab', '.mizzey-tabbar__item' ] ] ) {
				const el = document.querySelector( sel );
				if ( ! el ) {
					out[ name ] = null;
					continue;
				}
				const box = el.getBoundingClientRect();
				el.dispatchEvent( new PointerEvent( 'pointerdown', { bubbles: true, cancelable: true, pointerType: 'touch', isPrimary: true, clientX: box.x + 4, clientY: box.y + 4 } ) );
				await new Promise( ( resolve ) => setTimeout( resolve, 100 ) );
				out[ name ] = scale( el );
				el.dispatchEvent( new PointerEvent( 'pointerup', { bubbles: true, pointerType: 'touch', isPrimary: true } ) );
			}
			return out;
		} );
		// The Categories tab (C1-Menus-Phone, 3): right under the header, whose chips step aside; tiles on the tint with
		// no shadow, three across, 116.7 wide; a row's tiles share its height and the rows are 4 apart.
		await page.locator( '.mizzey-tabbar__categories' ).click();
		await page.waitForTimeout( 300 );
		const cats = await page.evaluate( () => {
			const panel = document.getElementById( 'mizzey-categories' );
			const head = document.querySelector( '.mizzey-header' ).getBoundingClientRect();
			const items = [ ...panel.querySelectorAll( '.mizzey-categories__item' ) ].map( ( e ) => e.getBoundingClientRect() );
			const tile = panel.querySelector( '.mizzey-categories__tile' );
			const chips = document.querySelector( '.mizzey-header__chips' );
			return {
				under: Math.round( panel.getBoundingClientRect().top - head.bottom ), chips: chips ? getComputedStyle( chips ).display : 'none',
				tile: [ getComputedStyle( tile ).backgroundColor, getComputedStyle( tile ).boxShadow ], width: items[ 0 ] ? Math.round( items[ 0 ].width * 10 ) / 10 : 0,
				pitch: items[ 3 ] ? Math.round( items[ 3 ].top - items[ 0 ].bottom ) : 4, same: items.length < 3 || Math.abs( items[ 0 ].height - items[ 1 ].height ) < 1,
			};
		} );
		check( cats.under === 0 && cats.chips === 'none' && cats.tile[ 0 ] === 'rgb(238, 241, 250)' && cats.tile[ 1 ] === 'none' && Math.abs( cats.width - 116.7 ) <= 1 && cats.pitch === 4 && cats.same, `K-1 ${ tag } the Categories tab as drawn (${ JSON.stringify( cats ) })` );
		await page.locator( '.mizzey-tabbar__categories' ).click();
		await page.waitForTimeout( 200 );
		check( pressed.chip !== null && pressed.chip < 1 && pressed.tab < 1, `K-1 ${ tag } a press shows within 100 ms of the finger going down (chip ${ pressed.chip }, tab ${ pressed.tab })` );
	}
	// The sticky header: past 200 px going down the strip (and the chips) slide away, the header row stays; going up
	// they return.
	// A long page (a sticky header stays inside its parent, the page's blocks).
	await page.evaluate( () => {
		( document.querySelector( '.wp-site-blocks' ) || document.body ).style.minHeight = '4000px';
	} );
	await page.evaluate( () => window.scrollTo( 0, 300 ) );
	await page.waitForTimeout( 100 );
	await page.evaluate( () => window.scrollTo( 0, 900 ) );
	await page.waitForTimeout( 500 );
	const down = await page.evaluate( () => {
		const bar = document.querySelector( '.mizzey-header__bar' ).getBoundingClientRect();
		const strip = document.querySelector( '.mizzey-announcement' );
		const chips = document.querySelector( '.mizzey-header__chips' );
		return { bar: Math.round( bar.top ), strip: strip ? Math.round( strip.getBoundingClientRect().bottom ) : 0, chips: chips ? getComputedStyle( chips ).visibility : 'none' };
	} );
	check( down.bar === 0 && down.strip <= 0 && down.chips !== 'visible', `K-1 ${ tag } scrolled down, the header row stays at the top and the strip and chips are away: ${ JSON.stringify( down ) }` );
	await page.evaluate( () => window.scrollTo( 0, 700 ) );
	await page.waitForTimeout( 500 );
	const up = await page.evaluate( () => {
		const strip = document.querySelector( '.mizzey-announcement' );
		return { strip: strip ? Math.round( strip.getBoundingClientRect().top ) : 0, part: Math.round( document.querySelector( '.mizzey-header' ).closest( '.wp-block-template-part' ).getBoundingClientRect().top ) };
	} );
	check( up.strip === 0 && up.part === 0, `K-1 ${ tag } scrolling up, the strip comes back above the header row: ${ JSON.stringify( up ) }` );
	// The page change: a cross-document view transition (Chromium; WebKit where it supports one).
	if ( width > 1000 ) {
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		const reveal = await page.context().newPage();
		await reveal.addInitScript( () => {
			window.addEventListener( 'pagereveal', ( event ) => {
				window.__mizzeyTransition = !! event.viewTransition;
			} );
		} );
		await load( reveal, page.url() );
		await reveal.locator( '.mizzey-header__logo' ).click();
		await reveal.waitForLoadState( 'load' );
		await reveal.waitForTimeout( 600 );
		const moved = await reveal.evaluate( () => window.__mizzeyTransition );
		check( moved === true || ( engine === 'webkit' && moved === undefined ), `K-1 ${ tag } changing pages runs the view transition (${ moved === undefined ? 'this engine has no pagereveal event' : moved })` );
		await reveal.close();
	}
}

/* ---- The home page's states and motion ----------------------------------------------------------------------- */

function homeMore( got, { width, tag, check } ) {
	check( /^25% 700$/.test( String( got[ 'deals figure bold' ] ) ), `K-1 ${ tag } the deals line carries "25%" in bold: ${ got[ 'deals figure bold' ] }` );
	check( got[ 'deals corner tags' ] === 0, `K-1 ${ tag } the deals band's cards carry no corner tag: ${ got[ 'deals corner tags' ] }` );
	check( got[ 'collection chevron colour' ] === INDIGO, `K-1 ${ tag } the collection tile's chevron is Royal Indigo: ${ got[ 'collection chevron colour' ] }` );
	check( got[ 'zz still once the photo is in' ], `K-1 ${ tag } the hero's ZZ stops breathing once its photo is in` );
	check( got[ 'page glows' ] === 2, `K-1 ${ tag } the page carries its two soft glows: ${ got[ 'page glows' ] }` );
	if ( width > 1000 ) {
		check( got[ 'trust line link' ] === `${ INDIGO } underline`, `K-1 ${ tag } the trust strip's second lines are underlined Royal Indigo links: ${ got[ 'trust line link' ] }` );
	}
}

async function heroMotion( page, { width, tag, check } ) {
	if ( ! await page.locator( '.mizzey-hero--slider' ).count() ) {
		check( false, `K-1 ${ tag } a hero slider to watch` );
		return;
	}
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	if ( width > 1000 ) {
		await page.mouse.move( 2, 890 );
		await page.locator( '.mizzey-hero__arrow' ).last().click();
		await page.mouse.move( 2, 890 );
	} else {
		await page.waitForFunction( () => document.querySelector( '.mizzey-hero' ).classList.contains( 'is-arriving' ), null, { timeout: 9000 } );
	}
	await page.waitForTimeout( 30 );
	const seen = await page.evaluate( () => {
		const hero = document.querySelector( '.mizzey-hero' );
		const ghost = hero.querySelector( '.mizzey-hero__ghost' );
		const active = hero.querySelector( '.mizzey-hero__slide.is-active' );
		const image = active.querySelector( '.mizzey-hero__image' );
		const words = [ ...active.querySelectorAll( '.mizzey-hero__panel > *' ) ];
		const track = hero.querySelector( '.mizzey-hero__track' );
		const timing = ( el ) => ( el && el.getAnimations()[ 0 ] ? el.getAnimations()[ 0 ].effect.getTiming() : null );
		const frames = ( el ) => ( el && el.getAnimations()[ 0 ] ? el.getAnimations()[ 0 ].effect.getKeyframes() : [] );
		return {
			ghost: ghost ? timing( ghost ).duration : null,
			ghostTo: ghost ? String( frames( ghost ).pop().opacity ) : null,
			image: timing( image ) ? timing( image ).duration : null,
			imageFrom: frames( image )[ 0 ] ? frames( image )[ 0 ].transform : '',
			delays: words.map( ( w ) => ( timing( w ) ? timing( w ).delay : null ) ),
			atSlide: Math.round( Math.abs( track.scrollLeft ) / track.clientWidth * 100 ) / 100,
		};
	} );
	check( seen.ghost === 320 && seen.ghostTo === '0', `K-1 ${ tag } the hero cross-fades: the outgoing slide fades out over 320 ms (${ seen.ghost }, to ${ seen.ghostTo })` );
	check( seen.image === 450 && /24px|-24px|matrix\(1, 0, 0, 1, -?24, 0\)/.test( seen.imageFrom ), `K-1 ${ tag } the incoming image moves 24 px to rest in 450 ms: ${ seen.image } ${ seen.imageFrom }` );
	check( seen.delays.includes( 60 ) && seen.delays.includes( 120 ), `K-1 ${ tag } the slide's words arrive 60 ms apart: ${ JSON.stringify( seen.delays ) }` );
	check( Number.isInteger( seen.atSlide ), `K-1 ${ tag } the row stands at the incoming slide at once, nothing travels: ${ seen.atSlide }` );
}

module.exports = { board, BOARD };

// Run by a scenario: `node _board.cjs <plan.json>` with { part, urls }; prints one JSON line, { checks }.
if ( require.main === module ) {
	const plan = JSON.parse( require( 'node:fs' ).readFileSync( process.argv[ 2 ], 'utf8' ) );
	board( plan.part, plan.urls )
		.then( ( checks ) => console.log( JSON.stringify( { checks } ) ) )
		.catch( ( error ) => console.log( JSON.stringify( { error: String( error && error.stack ) } ) ) );
}
