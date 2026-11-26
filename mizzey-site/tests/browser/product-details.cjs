#!/usr/bin/env node
/**
 * The lower product page in a real browser (spec 066, slice 10; AC-12, and AC-11 for PDP-12 and PDP-13): the jump-to
 * bar, Details, Specifications, Reviews with their photos (D-23), "You may also like" and "Recently viewed".
 * Driven by t116, which makes the fixture and passes a JSON plan as the only argument; prints one JSON object:
 * { checks: [{ ok, what }], shots }.
 *
 * Measured against C1-Product-Desktop (the main frame, E8, E9, E10), C1-Product-Phone (frame 1, E8, E9, E10, A15) and
 * C1-Photo-Reviews, in numbers: every gap between the parts, the bar, link and row heights, the columns, type sizes,
 * radii, the photo tiles. The open states too: the stuck bar, "Read more" opened, the photo viewer, "Write a review",
 * the "all reviews" view, and the empty states. Chromium at 1440 and 390 in English and Arabic; WebKit at 390 with touch.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

process.on( 'unhandledRejection', () => {} );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
let step = 'start';
const check = ( ok, what ) => {
	checks.push( { ok: !! ok, what } );
	step = what.slice( 0, 80 );
};
setTimeout( () => {
	checks.push( { ok: false, what: `the browser run timed out after: ${ step }` } );
	console.log( JSON.stringify( { checks, shots } ) );
	process.exit( 0 );
}, 12 * 60000 ).unref();
const TIMEOUT = 60000;
const near = ( a, b, tolerance = 1 ) => typeof a === 'number' && Math.abs( a - b ) <= tolerance;
const shot = async ( page, name, options = {} ) => {
	const file = path.join( plan.shots, `t116-${ name }.png` );
	if ( options.fullPage ) {
		// A whole-page picture shows the photos further down too.
		await page.evaluate( () => Promise.all( [ ...document.images ].map( ( img ) => {
			img.loading = 'eager';
			return img.complete ? null : new Promise( ( done ) => { img.onload = img.onerror = done; setTimeout( done, 3000 ); } );
		} ) ) ).catch( () => {} );
	}
	await page.screenshot( { path: file, ...options } ).catch( () => {} );
	shots.push( file );
};

/** Every box and style the checks read, in one pass, relative to the document. A missing part reads as null. */
function measure( page ) {
	return page.evaluate( () => {
		const shown = ( el ) => el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
		const box = ( el ) => {
			if ( ! shown( el ) ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			const s = getComputedStyle( el );
			return {
				x: r.left, y: r.top + scrollY, w: r.width, h: r.height, r: r.right, b: r.bottom + scrollY,
				font: `${ Math.round( parseFloat( s.fontSize ) ) }/${ Math.round( parseFloat( s.lineHeight ) ) } ${ s.fontWeight }`,
				radius: s.borderStartStartRadius, pad: s.paddingBlockStart + ' ' + s.paddingInlineStart, color: s.color,
				text: el.textContent.replace( /\s+/g, ' ' ).trim(),
			};
		};
		const one = ( sel ) => box( document.querySelector( sel ) );
		const all = ( sel ) => [ ...document.querySelectorAll( sel ) ].filter( shown ).map( box );
		const main = document.querySelector( '.mizzey-buy' );
		return {
			dir: getComputedStyle( document.documentElement ).direction,
			html: document.documentElement.outerHTML,
			buy: box( main ),
			features: one( '.mizzey-buy__features' ),
			nav: one( '.mizzey-details__jump' ),
			inner: one( '.mizzey-details__jump-inner' ),
			links: all( '.mizzey-details__jump-link' ),
			current: [ ...document.querySelectorAll( '.mizzey-details__jump-link' ) ].findIndex( ( a ) => a.getAttribute( 'aria-current' ) === 'true' ),
			bar: one( '.mizzey-details__jump-bar > span' ),
			about: one( '.mizzey-details__about' ),
			text: one( '.mizzey-details__text' ),
			titles: all( '.mizzey-details__title' ),
			body: one( '.mizzey-details__body' ),
			more: one( '.mizzey-details__more' ),
			specs: one( '.mizzey-details__specs' ),
			rows: all( '.mizzey-details__row' ),
			dts: all( '.mizzey-details__row dt' ),
			reviews: one( '.mizzey-reviews' ),
			side: one( '.mizzey-reviews__side' ),
			mainCol: one( '.mizzey-reviews__main' ),
			summary: one( '.mizzey-reviews__summary' ),
			score: one( '.mizzey-reviews__score' ),
			number: one( '.mizzey-reviews__number' ),
			count: one( '.mizzey-reviews__count' ),
			bars: all( '.mizzey-reviews__bar' ),
			fills: all( '.mizzey-reviews__bar-track > span' ),
			tracks: all( '.mizzey-reviews__bar-track' ),
			tools: one( '.mizzey-reviews__tools' ),
			chip: one( '.mizzey-reviews__tools .mizzey-chip' ),
			select: one( '.mizzey-reviews__sort .mizzey-select__button' ),
			chevron: one( '.mizzey-reviews__sort .mizzey-select__chevron' ),
			cards: all( '.mizzey-review' ),
			names: [ ...document.querySelectorAll( '.mizzey-review__name' ) ].map( ( n ) => n.textContent.trim() ),
			top: { average: ( document.querySelector( '.mizzey-buy__average' ) || {} ).textContent, count: ( document.querySelector( '.mizzey-buy__reviews' ) || {} ).textContent },
			ratings: [ ...document.querySelectorAll( '.mizzey-review__stars' ) ].map( ( n ) => n.getAttribute( 'aria-label' ) ),
			photos: [ ...document.querySelectorAll( '.mizzey-review' ) ].map( ( c ) => c.querySelectorAll( '.mizzey-reviews__photo' ).length ),
			tiles: all( '.mizzey-reviews__photo' ),
			plus: one( '.mizzey-reviews__photo--more' ),
			showAll: one( '.mizzey-reviews__more' ),
			write: one( '.mizzey-reviews__write-button' ),
			who: one( '.mizzey-reviews__who' ),
			none: one( '.mizzey-reviews__none' ),
			entry: one( '.mizzey-review-entry' ),
			rails: all( '.mizzey-details__rail' ),
			railHeads: all( '.mizzey-details__rail .mizzey-products__heading' ),
			railHeadRows: all( '.mizzey-details__rail .mizzey-products__head' ),
			railCards: [ ...document.querySelectorAll( '.mizzey-details__rail' ) ].map( ( r ) => [ ...r.querySelectorAll( '.mizzey-card' ) ].map( ( c ) => c.textContent.replace( /\s+/g, ' ' ) ) ),
			allTitle: one( '.mizzey-details__all-title' ),
			filters: all( '.mizzey-details__filters .mizzey-chip' ),
			footer: one( '.mizzey-footer' ),
		};
	} );
}

/** After a tap: nothing matches :focus-visible (owner rule, 26 November 2026). */
const ringAfterTap = ( page ) => page.evaluate( () => {
	const el = document.querySelector( ':focus-visible' );
	return el ? ( el.className || el.tagName ).toString().slice( 0, 60 ) : '';
} );

async function open( page, url ) {
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForSelector( '.mizzey-details', { timeout: TIMEOUT } );
	await page.evaluate( () => document.fonts.ready );
	await page.waitForTimeout( 300 );
	return measure( page );
}

async function consent( context, url ) {
	const host = new URL( url ).hostname;
	await context.addCookies( [ { name: 'mizzey_consent', value: 'denied', domain: host, path: '/' } ] ).catch( () => {} );
}

/** The checks on the full product at one width and language. */
async function full( page, label, wide, rtl ) {
	const m = await open( page, rtl ? plan.urls.full_ar : plan.urls.full );
	const tag = `[${ label }]`;
	check( !! m.nav && !! m.reviews, `${ tag } the lower page renders under the buying area: the jump-to bar and the reviews` );
	check( m.links.length === 3, `${ tag } the jump-to bar has a link for each part: Details, Specifications, Reviews (${ m.links.map( ( l ) => l && l.text ).join( ', ' ) })` );
	check( rtl ? m.dir === 'rtl' : m.dir === 'ltr', `${ tag } the page reads ${ rtl ? 'right to left' : 'left to right' }` );
	if ( wide ) {
		check( m.nav && m.buy && near( m.nav.y - m.buy.b, 72 ), `${ tag } the bar sits 72 under the buying area (${ m.nav && m.buy ? Math.round( m.nav.y - m.buy.b ) : 'none' })` );
		check( m.nav && near( m.nav.h, 57 ) && m.links.every( ( l ) => near( l.h, 56 ) ) && m.inner && near( m.inner.w, 520 ), `${ tag } the bar is 57 high with 56 links, 520 wide (${ m.nav && m.nav.h }, ${ m.links.map( ( l ) => l.h ) }, ${ m.inner && m.inner.w })` );
		check( m.about && m.nav && near( m.about.y - m.nav.b, 32 ), `${ tag } Details and Specifications start 32 under the bar (${ m.about && m.nav ? Math.round( m.about.y - m.nav.b ) : 'none' })` );
		check( m.text && m.specs && near( m.text.w, 732 ) && near( m.specs.w, 516 ) && near( Math.abs( m.specs.x - m.text.x ), rtl ? 540 : 756 ), `${ tag } Details 732 and Specifications 516 side by side, 24 apart (${ m.text && m.text.w }, ${ m.specs && m.specs.w })` );
		check( m.titles.length > 0 && m.titles.every( ( t ) => t.font === '20/28 900' ), `${ tag } the part titles are 20 on 28 Black (${ m.titles.map( ( t ) => t.font ) })` );
		check( m.dts.length > 0 && m.dts.every( ( d ) => near( d.w, 196 ) ), `${ tag } a specification's name is 196 of 516 (${ m.dts.map( ( d ) => Math.round( d.w ) ) })` );
		check( ! m.more, `${ tag } on a desktop the description is whole, with no "Read more"` );
		check( m.reviews && m.about && near( m.reviews.y - m.about.b, 72 ), `${ tag } Reviews start 72 under the details (${ m.reviews && m.about ? Math.round( m.reviews.y - m.about.b ) : 'none' })` );
		check( m.side && m.mainCol && near( m.side.w, 408 ) && near( m.mainCol.w, 840 ), `${ tag } the summary column 408 and the reviews 840 (${ m.side && m.side.w }, ${ m.mainCol && m.mainCol.w })` );
		check( m.number && m.number.font === '39/47 900', `${ tag } the average is 39 on 47 Black (${ m.number && m.number.font })` );
		check( m.select && near( m.select.h, 48 ) && m.select.radius === '16px', `${ tag } the order box is 48 high, radius 16 (${ m.select && m.select.h }, ${ m.select && m.select.radius })` );
		check( m.select && m.select.font === '16/24 400' && near( m.select.w, 146 ) && m.chevron && near( m.chevron.y - m.select.y, 12 ), `${ tag } the order box reads 16 on 24 Regular, 146 wide for "Most recent", the chevron 12 from its top (${ m.select && [ m.select.font, m.select.w ] })` );
		check( m.footer && m.rails[ 1 ] && near( m.footer.y - m.rails[ 1 ].b, 72 ), `${ tag } the footer is 72 under the last rail (${ m.footer && m.rails[ 1 ] ? Math.round( m.footer.y - m.rails[ 1 ].b ) : 'none' })` );
		check( m.showAll && near( m.showAll.h, 52 ) && m.showAll.w < 300, `${ tag } "Show all" is a 52 pill at its own width (${ m.showAll && m.showAll.w })` );
		check( m.rails[ 0 ] && m.reviews && near( m.rails[ 0 ].y - m.reviews.b, 72 ), `${ tag } "You may also like" is 72 under the reviews (${ m.rails[ 0 ] && m.reviews ? Math.round( m.rails[ 0 ].y - m.reviews.b ) : 'none' })` );
		check( m.rails.length === 2 && near( m.rails[ 1 ].y - m.rails[ 0 ].b, 72 ), `${ tag } the two rails are 72 apart (${ m.rails.length === 2 ? Math.round( m.rails[ 1 ].y - m.rails[ 0 ].b ) : m.rails.length })` );
	} else {
		check( m.nav && m.features && near( m.nav.y - m.features.b, 32 ), `${ tag } the bar sits 32 under the key features (${ m.nav && m.features ? Math.round( m.nav.y - m.features.b ) : 'none' })` );
		check( m.nav && near( m.nav.h, 49 ) && near( m.nav.w, 390 ) && m.links.every( ( l ) => near( l.h, 48 ) ), `${ tag } the bar is 49 high across the screen with 48 links (${ m.nav && m.nav.h }, ${ m.nav && m.nav.w })` );
		check( m.about && m.nav && near( m.about.y - m.nav.b, 24 ), `${ tag } Details start 24 under the bar (${ m.about && m.nav ? Math.round( m.about.y - m.nav.b ) : 'none' })` );
		check( m.titles.length > 0 && m.titles.every( ( t ) => near( parseFloat( t.font ), rtl ? 19 : 19, rtl ? 2 : 0 ) ), `${ tag } the part titles are 19 on 25 Black (${ m.titles.map( ( t ) => t.font ) })` );
		check( m.body && near( m.body.h, 144 ) && m.more && near( m.more.h, 44 ), `${ tag } the description shows six lines (144) and "Read more" 44 high (${ m.body && m.body.h }, ${ m.more && m.more.h })` );
		check( m.specs && m.text && near( m.specs.y - m.text.b, 40 ), `${ tag } Specifications 40 under Details (${ m.specs && m.text ? Math.round( m.specs.y - m.text.b ) : 'none' })` );
		check( m.dts.length > 0 && m.dts.every( ( d ) => near( d.w, 136 ) ), `${ tag } a specification's name is 136 of 358 (${ m.dts.map( ( d ) => Math.round( d.w ) ) })` );
		check( m.reviews && m.about && near( m.reviews.y - m.about.b, 40 ), `${ tag } Reviews 40 under the specifications (${ m.reviews && m.about ? Math.round( m.reviews.y - m.about.b ) : 'none' })` );
		check( m.number && near( parseFloat( m.number.font ), 28 ), `${ tag } the average is 28 on 34 Black (${ m.number && m.number.font })` );
		check( m.tools && m.summary && near( m.tools.y - m.summary.b, 16 ) && near( m.tools.h, 48 ), `${ tag } the toolbar is 48 high, 16 under the summary (${ m.tools && m.summary ? Math.round( m.tools.y - m.summary.b ) : 'none' })` );
		check( m.showAll && near( m.showAll.w, 358 ) && m.write && near( m.write.w, 358 ) && m.write.y > m.showAll.b, `${ tag } "Show all" and then "Write a review" span the 358 column (${ m.showAll && m.showAll.w }, ${ m.write && m.write.w })` );
		check( m.rails[ 0 ] && m.reviews && near( m.rails[ 0 ].y - m.reviews.b, 40 ), `${ tag } "You may also like" is 40 under the reviews (${ m.rails[ 0 ] && m.reviews ? Math.round( m.rails[ 0 ].y - m.reviews.b ) : 'none' })` );
		check( m.rails.length === 2 && near( m.rails[ 1 ].y - m.rails[ 0 ].b, 32 ), `${ tag } the two rails are 32 apart (${ m.rails.length === 2 ? Math.round( m.rails[ 1 ].y - m.rails[ 0 ].b ) : m.rails.length })` );
		check( m.railHeadRows.every( ( h ) => near( h.h, 48 ) ) && m.railHeads.every( ( h ) => h.font.startsWith( '19/25' ) || rtl ), `${ tag } each rail's heading is 19 on 25 in a 48 row (${ m.railHeads.map( ( h ) => h.font ) })` );
	}
	// The same on every width.
	check( m.specs && plan.specs.every( ( name ) => m.specs.text.includes( name ) || rtl ) && ! m.html.includes( plan.hidden ), `${ tag } PDP-13 the specifications list the brand, the visible attributes, weight and SKU, and not the hidden attribute` );
	check( m.rows.length === plan.specs.length && m.rows.every( ( r ) => ( rtl ? r.h >= 49 && r.h <= 56 : near( r.h, 49 ) ) ), `${ tag } each specification row is 49 high (12 above and below the line; Arabic lines are taller) (${ m.rows.map( ( r ) => r.h ) })` );
	check( m.body && m.body.text.includes( plan.tag ), `${ tag } PDP-12 the full description is shown` );
	check( m.number && m.number.text === ( rtl ? m.number.text : plan.reviews.average ) && m.count && m.count.text.includes( rtl ? String( plan.reviews.count ) : `${ plan.reviews.count } reviews` ), `${ tag } REV-04 the average ${ m.number && m.number.text } and the count "${ m.count && m.count.text }"` );
	check( m.top.average === plan.reviews.average && m.top.count && m.top.count.includes( String( plan.reviews.count ) ) && m.number && m.number.text === plan.reviews.average, `${ tag } REV-04 the rating under the name and the reviews' summary read the same figure in every language, both languages' reviews together (${ m.top.average }, ${ m.top.count })` );
	check( m.bars.length === 5 && m.fills.length === 5 && near( m.fills[ 0 ].w, m.tracks[ 0 ].w * plan.reviews.five / plan.reviews.count, 1.5 ), `${ tag } five bars, each its star's share of every review (5 stars: ${ m.fills[ 0 ] && Math.round( m.fills[ 0 ].w ) } of ${ m.tracks[ 0 ] && Math.round( m.tracks[ 0 ].w ) })` );
	check( m.cards.length === 3 && m.names[ 0 ] === 'Mariam H.' && m.cards.every( ( c ) => c.radius === '20px' && c.pad === '16px 16px' ), `${ tag } PDP-17 three reviews on the page, most recent first, the name as drawn, radius 20 and 16 inside (${ m.names.join( ', ' ) })` );
	check( m.photos[ 0 ] === 4 && m.plus && m.plus.text.includes( '2' ) && m.tiles.every( ( t ) => near( t.w, 72 ) && near( t.h, 72 ) && t.radius === '12px' ), `${ tag } D-23 five photos show as three and "+2", 72 tiles with radius 12 (${ m.photos.join( ',' ) })` );
	check( m.html.includes( plan.reviews.arabic ) && ( m.cards[ 2 ] && m.cards[ 2 ].text.includes( plan.reviews.long ) ), `${ tag } an Arabic review and a very long one are shown whole` );
	check( ! m.html.includes( plan.held.text ) && ! m.html.includes( plan.spam ) && ! m.html.includes( `mizzey-review-photo=${ plan.held.id }-` ), `${ tag } only approved reviews: the held review, its photo and the spam review are not in the page` );
	check( m.chip && m.chip.text.includes( String( plan.reviews.photos ) ) && near( m.chip.h, 36 ), `${ tag } "With photos ${ plan.reviews.photos }" is a 36 chip (${ m.chip && m.chip.text })` );
	check( m.who && m.write && near( m.write.h, 52 ), `${ tag } "Write a review" 52 high with who may write one` );
	check( m.rails.length === 2 && plan.related.every( ( name ) => m.railCards[ 0 ].some( ( c ) => c.includes( name.slice( 0, 30 ) ) || rtl ) ), `${ tag } PDP-18 "You may also like" carries the staff's related products (${ m.railCards[ 0 ] ? m.railCards[ 0 ].length : 0 })` );
	check( m.rails.length === 2 && m.railCards[ 1 ].some( ( c ) => c.includes( plan.bare.slice( 0, 20 ) ) || rtl ) && ! m.railCards[ 1 ].some( ( c ) => c.includes( 'Collagen Peptides' ) ), `${ tag } PDP-19 "Recently viewed" holds what this browser viewed, not this product` );
	check( rtl ? m.score && m.bars[ 0 ] && m.score.x > m.bars[ 0 ].x : m.score && m.bars[ 0 ] && m.score.x < m.bars[ 0 ].x, `${ tag } the score sits at the start of the summary` );
	return m;
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { checks: [ { ok: false, what: 'Playwright was not found' } ], shots } ) );
		return;
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	try {
		// ---- 1440, English -------------------------------------------------------------------------------------
		const desk = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
		await consent( desk, plan.urls.full );
		const page = await desk.newPage();

		// The product with nothing below its description: first, in a fresh browser, so nothing was viewed before.
		let b = await open( page, plan.urls.bare );
		check( b.links.map( ( l ) => l.text ).join( '|' ) === 'Details|Reviews', `[en 1440] E9 no specifications: the bar has two links (${ b.links.map( ( l ) => l.text ).join( ', ' ) })` );
		check( ! b.specs && b.text && near( b.text.w, 732 ), `[en 1440] E9 no specifications: the part is left out and Details keeps its width (${ b.text && b.text.w })` );
		check( b.none && near( b.none.h, 96 ) && near( b.none.w, 840 ) && b.none.text.includes( 'No reviews yet' ) && ! b.number && b.bars.length === 0, `[en 1440] E8 no reviews: empty stars, one line and "Write a review" in a 96 panel, no score, no bars (${ b.none && b.none.h })` );
		check( b.rails.length === 0, `[en 1440] E10 no related products and nothing viewed before: neither rail is drawn (${ b.rails.length })` );
		await shot( page, 'en-1440-empty', { fullPage: true } );

		let m = await full( page, 'en 1440', true, false );
		await page.evaluate( () => window.scrollTo( 0, document.querySelector( '.mizzey-details__jump' ).getBoundingClientRect().top + scrollY - 200 ) );
		await shot( page, 'en-1440', { fullPage: true } );

		// The held review's photo is refused even when asked for directly.
		const held = await page.request.get( plan.held.photo, { failOnStatusCode: false } );
		check( held.status() === 404, `[en 1440] D-23 the held review's photo is not served (${ held.status() })` );

		// The bar sticks and follows: a click on Reviews lands the reviews under it, underlined.
		await page.click( '.mizzey-details__jump-link[href="#reviews"]' );
		await page.waitForTimeout( 1200 );
		const stuck = await page.evaluate( () => {
			const nav = document.querySelector( '.mizzey-details__jump' );
			const r = nav.getBoundingClientRect();
			const reviews = document.getElementById( 'reviews' ).getBoundingClientRect();
			const current = [ ...nav.querySelectorAll( 'a' ) ].findIndex( ( a ) => a.getAttribute( 'aria-current' ) === 'true' );
			return { top: r.top, stuck: nav.classList.contains( 'is-stuck' ), current, gap: reviews.top - r.bottom, shadow: getComputedStyle( nav, '::before' ).opacity };
		} );
		check( near( stuck.top, 0 ) && stuck.stuck && stuck.current === 2 && stuck.gap >= 0 && stuck.gap < 60 && stuck.shadow === '1', `[en 1440] the bar sticks to the top, white with its shadow, and "Reviews" is underlined with the reviews just under it (${ JSON.stringify( stuck ) })` );
		await shot( page, 'en-1440-stuck' );

		// The photo viewer, from "+2": the fourth of five, by its reviewer; the arrows step; Escape closes and returns focus.
		await page.click( '.mizzey-reviews__photo--more' );
		await page.waitForSelector( '.mizzey-photo-viewer[open]', { timeout: 5000 } ).catch( () => {} );
		const viewer = await page.evaluate( () => ( { open: document.querySelector( '.mizzey-photo-viewer' ).open, label: document.querySelector( '.mizzey-photo-viewer__label' ).textContent, src: document.querySelector( '.mizzey-photo-viewer__img' ).getAttribute( 'src' ) } ) );
		check( viewer.open && viewer.label === '4 / 5, by Mariam H.' && viewer.src.includes( 'mizzey-review-photo=' ), `[en 1440] D-23 "+2" opens the viewer at the fourth photo, by its reviewer (${ viewer.label })` );
		await page.waitForTimeout( 300 );
		await shot( page, 'en-1440-viewer' );
		await page.keyboard.press( 'ArrowRight' );
		const next = await page.evaluate( () => document.querySelector( '.mizzey-photo-viewer__label' ).textContent );
		await page.keyboard.press( 'Escape' );
		const closed = await page.evaluate( () => ( { open: document.querySelector( '.mizzey-photo-viewer' ).open, focus: document.activeElement.classList.contains( 'mizzey-reviews__photo--more' ) } ) );
		check( next === '5 / 5, by Mariam H.' && ! closed.open && closed.focus, `[en 1440] the arrow key steps to the next photo, Escape closes and focus returns to "+2" (${ next }, ${ JSON.stringify( closed ) })` );

		// Write a review, as a visitor who has not bought it: it opens in place and says who may write one.
		await page.click( '.mizzey-reviews__write-button' );
		const entry = await page.evaluate( () => ( { hidden: document.querySelector( '.mizzey-review-entry' ).hidden, text: document.querySelector( '.mizzey-review-entry' ).textContent, expanded: document.querySelector( '.mizzey-reviews__write-button' ).getAttribute( 'aria-expanded' ) } ) );
		check( ! entry.hidden && entry.expanded === 'true' && entry.text.includes( 'Only customers' ) && ! entry.text.includes( 'Post review' ), `[en 1440] "Write a review" opens in place; a visitor who has not bought it is told who may write one (${ entry.text.trim().slice( 0, 80 ) })` );
		await shot( page, 'en-1440-write' );

		// The order: lowest first.
		await page.click( '#mizzey-review-sort' );
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.click( '.mizzey-reviews__sort .mizzey-select__option[data-value="low"]' ) ] );
		m = await measure( page );
		check( m.ratings[ 0 ] === 'Rated 1 out of 5' && new URL( page.url() ).searchParams.get( 'review_sort' ) === 'low', `[en 1440] "Lowest rating" puts the one-star review first (${ m.ratings[ 0 ] })` );

		// With photos, in place.
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.click( '.mizzey-reviews__tools .mizzey-chip' ) ] );
		m = await measure( page );
		check( m.cards.length === 2 && m.photos.every( ( n ) => n > 0 ) && m.chip && m.chip.text.includes( 'With photos' ), `[en 1440] "With photos" keeps the two reviews with photos (${ m.photos.join( ',' ) })` );

		// Show all: the "all reviews" view, then Load more and one star.
		await page.goto( plan.urls.full, { waitUntil: 'load' } );
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.click( '.mizzey-reviews__more' ) ] );
		m = await measure( page );
		check( m.allTitle && m.allTitle.text === `All ${ plan.reviews.count } reviews` && m.cards.length === 10 && ! m.buy && m.filters.length === 7, `[en 1440] E8 "Show all ${ plan.reviews.count } reviews" opens all of them: the title, the filters (All, With photos, 5 to 1 stars), ten reviews, the buying area stepped aside (${ m.allTitle && m.allTitle.text }, ${ m.cards.length }, ${ m.filters.length })` );
		check( m.html.includes( '/ <span class="breadcrumb_last" aria-current="page">Reviews' ) || m.html.includes( '>Reviews</' ), `[en 1440] the breadcrumb ends with the product, then "Reviews"` );
		await shot( page, 'en-1440-all', { fullPage: true } );
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.click( '.mizzey-reviews__more' ) ] );
		m = await measure( page );
		check( m.cards.length === plan.reviews.count && ! m.showAll, `[en 1440] "Load more reviews" brings the rest, and then is gone (${ m.cards.length })` );
		await Promise.all( [ page.waitForNavigation( { timeout: TIMEOUT } ), page.click( '.mizzey-details__filters .mizzey-chip:last-child' ) ] );
		m = await measure( page );
		check( m.cards.length === 1 && m.ratings[ 0 ] === 'Rated 1 out of 5', `[en 1440] "1 star" keeps the one-star review (${ m.cards.length })` );
		await desk.close();

		// ---- 390, English and Arabic, Chromium; 390 English, WebKit --------------------------------------------
		for ( const [ engine, label, rtl ] of [ [ playwright.pw.chromium, 'en 390', false ], [ playwright.pw.chromium, 'ar 390', true ], [ playwright.pw.webkit, 'en 390 webkit', false ] ] ) {
			const own = engine === playwright.pw.chromium ? browser : await engine.launch( { headless: true } );
			const ctx = await own.newContext( { viewport: { width: 390, height: 844 }, isMobile: engine === playwright.pw.chromium, hasTouch: true, deviceScaleFactor: 2 } );
			await consent( ctx, plan.urls.full );
			const phone = await ctx.newPage();
			b = await open( phone, plan.urls.bare );
			check( b.none && b.none.pad === '20px 20px' && near( b.none.w, 358 ) && b.rails.length === 0 && ! b.specs, `[${ label }] E8 to E10: no reviews in the quiet panel, 20 inside; no specifications; no rails (${ b.none && b.none.pad })` );
			if ( label === 'en 390' ) {
				await shot( phone, 'en-390-empty', { fullPage: true } );
			}
			m = await full( phone, label, false, rtl );
			await shot( phone, `${ label.replace( / /g, '-' ) }`, { fullPage: true } );
			// "Read more", one tap.
			await phone.tap( '.mizzey-details__more' );
			await phone.waitForTimeout( 200 );
			const opened = await phone.evaluate( () => ( { h: document.querySelector( '.mizzey-details__body' ).getBoundingClientRect().height, label: document.querySelector( '.mizzey-details__more' ).textContent, expanded: document.querySelector( '.mizzey-details__more' ).getAttribute( 'aria-expanded' ) } ) );
			check( opened.h > 144 && opened.expanded === 'true' && ( rtl || opened.label === 'Read less' ), `[${ label }] A15 one tap on "Read more" opens the whole text and it reads "Read less" (${ JSON.stringify( opened ) })` );
			let ring = await ringAfterTap( phone );
			check( ring === '', `[${ label }] no focus ring after tapping "Read more" (${ ring })` );
			// The bar, one tap on Reviews.
			await phone.tap( '.mizzey-details__jump-link[href="#reviews"]' );
			await phone.waitForTimeout( 1200 );
			const bar = await phone.evaluate( () => {
				const nav = document.querySelector( '.mizzey-details__jump' ).getBoundingClientRect();
				return { top: nav.top, reviews: document.getElementById( 'reviews' ).getBoundingClientRect().top - nav.bottom, current: [ ...document.querySelectorAll( '.mizzey-details__jump-link' ) ].findIndex( ( a ) => a.getAttribute( 'aria-current' ) === 'true' ) };
			} );
			check( near( bar.top, 0 ) && bar.current === 2 && bar.reviews >= 0 && bar.reviews < 60, `[${ label }] one tap on "Reviews" lands the reviews under the stuck bar (${ JSON.stringify( bar ) })` );
			ring = await ringAfterTap( phone );
			check( ring === '', `[${ label }] no focus ring after tapping a jump-to link (${ ring })` );
			await shot( phone, `${ label.replace( / /g, '-' ) }-stuck` );
			// The photo viewer, one tap.
			await phone.evaluate( ( sel ) => document.querySelector( sel ).scrollIntoView( { block: 'center' } ), '.mizzey-reviews__photo' );
			await phone.tap( '.mizzey-reviews__photo' );
			await phone.waitForTimeout( 400 );
			const v = await phone.evaluate( () => {
				const d = document.querySelector( '.mizzey-photo-viewer' );
				const r = d.getBoundingClientRect();
				return { open: d.open, inside: r.left >= 0 && r.right <= innerWidth, label: document.querySelector( '.mizzey-photo-viewer__label' ).textContent };
			} );
			check( v.open && v.inside && v.label.startsWith( '1 / 5' ), `[${ label }] one tap on a photo opens the viewer inside the screen (${ JSON.stringify( v ) })` );
			ring = await ringAfterTap( phone );
			check( ring === '', `[${ label }] no focus ring in the viewer opened by a tap (${ ring })` );
			await shot( phone, `${ label.replace( / /g, '-' ) }-viewer` );
			await phone.tap( '.mizzey-photo-viewer__close' );
			await phone.waitForTimeout( 200 );
			ring = await ringAfterTap( phone );
			check( ring === '' && ! ( await phone.evaluate( () => document.querySelector( '.mizzey-photo-viewer' ).open ) ), `[${ label }] one tap on the close button closes the viewer, with no ring left on the photo (${ ring })` );
			await phone.evaluate( ( sel ) => document.querySelector( sel ).scrollIntoView( { block: 'center' } ), '.mizzey-reviews__write-button' );
			await phone.tap( '.mizzey-reviews__write-button' );
			await phone.waitForTimeout( 200 );
			const w = await phone.evaluate( () => ! document.querySelector( '.mizzey-review-entry' ).hidden );
			ring = await ringAfterTap( phone );
			check( w && ring === '', `[${ label }] one tap on "Write a review" opens it in place, with no ring (${ ring })` );
			// The order box, one tap: its list opens, and no ring is left.
			await phone.evaluate( () => document.querySelector( '#mizzey-review-sort' ).scrollIntoView( { block: 'center' } ) );
			await phone.tap( '#mizzey-review-sort' );
			const list = await phone.evaluate( () => ! document.querySelector( '.mizzey-reviews__sort .mizzey-select__list' ).hidden );
			ring = await ringAfterTap( phone );
			check( list && ring === '', `[${ label }] one tap on the order box opens its list, with no ring (${ ring })` );
			await phone.tap( '#mizzey-review-sort' );
			// In the middle of the screen, as a person taps it, clear of the stuck bar.
			await phone.evaluate( () => document.querySelector( '.mizzey-reviews__tools .mizzey-chip' ).scrollIntoView( { block: 'center' } ) );
			await Promise.all( [ phone.waitForNavigation( { timeout: TIMEOUT } ), phone.tap( '.mizzey-reviews__tools .mizzey-chip' ) ] );
			ring = await ringAfterTap( phone );
			const chips = await measure( phone );
			check( chips.cards.length === 2 && ring === '', `[${ label }] one tap on "With photos" keeps the reviews with photos, with no ring (${ chips.cards.length }, ${ ring })` );
			await ctx.close();
			if ( own !== browser ) {
				await own.close();
			}
		}

		// ---- A buyer writes a review with two photos (E11, C1-Photo-Reviews), in WebKit at 390 with touch ------------
		{
			const wk = await playwright.pw.webkit.launch( { headless: true } );
			const ctx = await wk.newContext( { viewport: { width: 390, height: 844 }, hasTouch: true, deviceScaleFactor: 2 } );
			const host = new URL( plan.urls.bare ).hostname;
			await ctx.addCookies( plan.buyer.map( ( c ) => ( { ...c, domain: host, path: '/' } ) ).concat( [ { name: 'mizzey_consent', value: 'denied', domain: host, path: '/' } ] ) );
			const bp = await ctx.newPage();
			await open( bp, plan.urls.bare );
			await bp.evaluate( () => document.querySelector( '.mizzey-reviews__write-button' ).scrollIntoView( { block: 'center' } ) );
			await bp.tap( '.mizzey-reviews__write-button' );
			const form = await bp.evaluate( () => {
				const f = document.querySelector( '.mizzey-review-entry__form' );
				const add = document.querySelector( '.mizzey-review-entry__add' );
				const r = add ? add.getBoundingClientRect() : null;
				return f ? { stars: f.querySelectorAll( 'input[name="rating"]' ).length, add: r && [ r.width, r.height ], label: f.querySelector( '.mizzey-review-entry__photos' ).textContent.trim(), ring: ( document.querySelector( ':focus-visible' ) || {} ).className || '' } : null;
			} );
			check( form && form.stars === 5 && form.add && near( form.add[ 0 ], 72 ) && near( form.add[ 1 ], 72 ) && /up to 5/.test( form.label ) && form.ring === '', `[en 390 webkit] a buyer's "Write a review" opens the form in place: stars, text, "Photos (optional, up to 5)" with a 72 "Add" tile, and no ring after the tap (${ JSON.stringify( form ) })` );
			await bp.tap( '.mizzey-review-entry__star:nth-of-type(4)' );
			await bp.fill( '#mizzey-review-text', 'Soft and not sticky. Two photos attached.' );
			const png = await bp.evaluate( () => {
				const c = document.createElement( 'canvas' );
				c.width = 64;
				c.height = 64;
				const g = c.getContext( '2d' );
				g.fillStyle = '#263b73';
				g.fillRect( 8, 8, 48, 48 );
				return c.toDataURL( 'image/png' ).split( ',' )[ 1 ];
			} );
			const buffer = Buffer.from( png, 'base64' );
			await bp.setInputFiles( '.mizzey-review-entry__add input', [ { name: 'a.png', mimeType: 'image/png', buffer }, { name: 'b.png', mimeType: 'image/png', buffer } ] );
			const thumbs = await bp.evaluate( () => document.querySelectorAll( '.mizzey-review-entry__chosen img' ).length );
			check( thumbs === 2, `[en 390 webkit] the two chosen photos show in the form before sending (${ thumbs })` );
			await shot( bp, 'en-390-write-photos' );
			// The answer is held back for a moment: the button waits with the loader, and nothing claims success.
			let release;
			const gate = new Promise( ( r ) => ( release = r ) );
			await bp.route( '**/wp/v2/comments**', async ( route ) => {
				await gate;
				await route.continue();
			} );
			await bp.evaluate( () => document.querySelector( '.mizzey-review-entry__send' ).scrollIntoView( { block: 'center' } ) );
			await bp.tap( '.mizzey-review-entry__send' );
			await bp.waitForTimeout( 300 );
			const busy = await bp.evaluate( () => {
				const b = document.querySelector( '.mizzey-review-entry__send' );
				const l = b.querySelector( '.mizzey-loader' );
				const r = b.getBoundingClientRect();
				const lr = l ? l.getBoundingClientRect() : null;
				return { busy: b.classList.contains( 'is-busy' ), disabled: b.disabled, inside: !! lr && lr.left >= r.left && lr.right <= r.right && lr.top >= r.top && lr.bottom <= r.bottom, thanks: !! document.querySelector( '.mizzey-review-entry__note' ) };
			} );
			check( busy.busy && busy.disabled && busy.inside && ! busy.thanks, `[en 390 webkit] while the server has not answered, "Send review" holds the i-dot loader inside it and no thank-you shows (${ JSON.stringify( busy ) })` );
			await shot( bp, 'en-390-write-busy' );
			release();
			await bp.waitForSelector( '.mizzey-review-entry__note', { timeout: TIMEOUT } ).catch( () => {} );
			const thanks = await bp.evaluate( () => ( document.querySelector( '.mizzey-review-entry__note' ) || {} ).textContent || '' );
			const error = await bp.evaluate( () => ( document.querySelector( '.mizzey-review-entry__error' ) || {} ).textContent || '' );
			check( thanks === 'Thanks, your review is waiting for approval.' && error === '', `[en 390 webkit] after the answer, the form gives way to "${ thanks }" (${ error })` );
			await shot( bp, 'en-390-write-sent' );
			await wk.close();
		}

		// ---- Reduced motion: the bar's underline moves at once ---------------------------------------------------
		const still = await browser.newContext( { viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' } );
		await consent( still, plan.urls.full );
		const sp = await still.newPage();
		await open( sp, plan.urls.full );
		const motion = await sp.evaluate( () => getComputedStyle( document.querySelector( '.mizzey-details__jump-bar' ) ).transitionDuration );
		check( motion === '0s', `[reduced motion] the underline moves without its slide (${ motion })` );
		await still.close();
	} catch ( error ) {
		checks.push( { ok: false, what: `the browser run stopped at "${ step }": ${ String( error && error.message ).slice( 0, 400 ) }` } );
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks, shots } ) );
} )();
