#!/usr/bin/env node
/**
 * The section library in a real browser, as an editor account (spec 066, slice 1; AC-4, G11). Driven by t106, which
 * prepares the runtime and passes a JSON plan as the only argument; prints one JSON object: { checks: [{ ok, what }] }.
 *
 * - The inserter on the front page lists only the library's sections, in English and in Arabic; on another page it
 *   still lists the ordinary blocks.
 * - The trust signals section is added twice in English and once in Arabic through the inserter, its words typed in
 *   place and a link set in the sidebar; each language's storefront shows its own words.
 * - The second instance is moved up with the block toolbar, previewed (the preview shows the new order while the live
 *   page keeps the old), then saved, and the live page follows.
 * - One instance is hidden with "Show on the site": the storefront no longer shows it; reopened, the editor still has
 *   its words.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site: PLAYWRIGHT_DIR, or the copy
 * `npx playwright` has cached; when it has no Chromium of its own, the installed Chrome is used.
 */

const fs = require( 'node:fs' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const T = plan.tag;
const SECTION = 'mizzey/trust-signals';
const TIMEOUT = 120000;

/** Open a page in the editor and wait until the editor and its canvas are ready. */
async function openEditor( page, url ) {
	await page.goto( url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT } );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() && wp.data.select( 'core/block-editor' ).getSettings().allowedBlockTypes !== undefined, null, { timeout: TIMEOUT } );
	await page.locator( 'iframe[name="editor-canvas"]' ).waitFor( { timeout: TIMEOUT } );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await canvas.locator( '.is-root-container' ).waitFor( { timeout: TIMEOUT } );
	// A guide or a pattern chooser that still opens is closed, as an editor would.
	for ( const name of [ 'Close', 'Skip' ] ) {
		const dialog = page.locator( '.components-modal__frame' ).getByRole( 'button', { name } );
		if ( await dialog.count() ) {
			await dialog.first().click().catch( () => {} );
		}
	}
	return canvas;
}

/** The inserter's block list, as a person sees it: the items' names, read from the open panel. */
async function inserterItems( page ) {
	const toggle = page.locator( 'button.editor-document-tools__inserter-toggle' );
	await toggle.click();
	const panel = page.locator( '.block-editor-inserter__block-list, .block-editor-inserter__panel-content' ).first();
	await panel.waitFor( { timeout: TIMEOUT } );
	await page.waitForTimeout( 500 );
	const items = await page.evaluate( () => {
		const names = new Set();
		document.querySelectorAll( '.block-editor-block-types-list__item' ).forEach( ( el ) => {
			const cls = [ ...el.classList ].find( ( c ) => c.startsWith( 'editor-block-list-item-' ) );
			names.add( cls ? cls.slice( 'editor-block-list-item-'.length ) : el.textContent.trim() );
		} );
		return [ ...names ];
	} );
	const data = await page.evaluate( () => wp.data.select( 'core/block-editor' ).getInserterItems().map( ( i ) => i.name || i.id ) );
	await toggle.click();
	return { items, data };
}

/** Add a section through the inserter's search, as a person does; resolves to the new block's client id. */
async function insertSection( page ) {
	const before = await page.evaluate( ( n ) => wp.data.select( 'core/block-editor' ).getBlocks().filter( ( b ) => b.name === n ).map( ( b ) => b.clientId ), SECTION );
	const toggle = page.locator( 'button.editor-document-tools__inserter-toggle' );
	await toggle.click();
	const search = page.locator( '.block-editor-inserter__search input, input[type="search"]' ).first();
	await search.fill( 'Trust' );
	await page.locator( '.block-editor-block-types-list__item.editor-block-list-item-mizzey-trust-signals' ).first().click();
	await page.waitForFunction( ( [ n, count ] ) => wp.data.select( 'core/block-editor' ).getBlocks().filter( ( b ) => b.name === n ).length > count, [ SECTION, before.length ], { timeout: TIMEOUT } );
	if ( await page.locator( '.block-editor-inserter__search' ).count() ) {
		await toggle.click().catch( () => {} );
	}
	return page.evaluate( ( [ n, old ] ) => wp.data.select( 'core/block-editor' ).getBlocks().find( ( b ) => b.name === n && ! old.includes( b.clientId ) ).clientId, [ SECTION, before ] );
}

/** Type an item's words in place, in the canvas. */
async function typeInto( canvas, page, clientId, label, text ) {
	const field = canvas.locator( `[data-block="${ clientId }"]` ).getByRole( 'textbox', { name: label, exact: true } );
	await field.click();
	// Select-all only inside a field that has words: in an empty field the editor's Ctrl+A selects whole blocks.
	if ( ( await field.innerText() ).trim() !== '' ) {
		await page.keyboard.press( 'Control+A' );
	}
	await page.keyboard.type( text );
}

async function openSidebar( page ) {
	const settings = page.locator( 'button[aria-label="Settings"][aria-expanded="false"]' );
	if ( await settings.count() ) {
		await settings.first().click();
	}
}

async function selectBlock( canvas, page, clientId ) {
	await page.evaluate( ( id ) => wp.data.dispatch( 'core/block-editor' ).selectBlock( id ), clientId );
	await canvas.locator( `[data-block="${ clientId }"]` ).scrollIntoViewIfNeeded();
	await openSidebar( page );
}

/** Save as a person does, and wait for the save to finish. */
async function save( page ) {
	await page.locator( '.editor-post-publish-button, .editor-post-publish-button__button' ).first().click();
	await page.waitForFunction( () => {
		const e = wp.data.select( 'core/editor' );
		return ! e.isSavingPost() && ! e.isEditedPostDirty() && ! e.isAutosavingPost();
	}, null, { timeout: TIMEOUT } );
	await page.waitForTimeout( 500 );
}

/** The trust signals sections a page shows, in order: each one's titles and its line links. */
async function sectionsOn( context, url ) {
	const page = await context.newPage();
	await page.goto( url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT } );
	const out = await page.evaluate( () => ( {
		dir: document.documentElement.dir || getComputedStyle( document.documentElement ).direction,
		lang: document.documentElement.lang,
		sections: [ ...document.querySelectorAll( '.mizzey-trust' ) ].map( ( s ) => ( {
			titles: [ ...s.querySelectorAll( '.mizzey-trust__title' ) ].map( ( t ) => t.textContent.trim() ),
			links: [ ...s.querySelectorAll( 'a.mizzey-trust__line' ) ].map( ( a ) => a.getAttribute( 'href' ) ),
		} ) ),
	} ) );
	await page.close();
	return out;
}

const firstTitles = ( view ) => view.sections.map( ( s ) => s.titles[ 0 ] );

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		process.exit( 2 );
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	const base = new URL( plan.base );
	const editor = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await editor.addCookies( plan.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: base.hostname, path: '/' } ) ) );
	const visitor = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	const page = await editor.newPage();
	page.setDefaultTimeout( TIMEOUT );
	const errors = [];
	// Errors from the library's own scripts. The editor of this runtime already logs errors of its own (an
	// "import statement outside a module" from code this slice does not touch), counted apart.
	let otherErrors = 0;
	page.on( 'pageerror', ( e ) => ( /mizzey|Blocks\/|trust-signals/i.test( `${ e.message } ${ e.stack || '' }` ) ? errors.push( e.message ) : otherErrors++ ) );
	let step = 'start';
	try {
		// ---- The inserter: library sections only on the front page, in both languages ----------------------------
		step = 'English inserter';
		let canvas = await openEditor( page, plan.en.edit );
		const library = plan.library.map( ( n ) => n.replace( '/', '-' ) ).sort();
		let seen = await inserterItems( page );
		check( JSON.stringify( seen.items.sort() ) === JSON.stringify( library ), `FR-003 [en] the front page's inserter lists only the library's sections: ${ seen.items.join( ', ' ) }` );
		check( JSON.stringify( seen.data.sort() ) === JSON.stringify( [ ...plan.library ].sort() ), `FR-003 [en] the editor offers no other block to insert: ${ seen.data.join( ', ' ) }` );

		// ---- Added and edited in English, twice ------------------------------------------------------------------
		step = 'English sections';
		const a = await insertSection( page );
		for ( let i = 1; i <= 3; i++ ) {
			await typeInto( canvas, page, a, `Item ${ i } title`, `${ T } A${ i }` );
		}
		await typeInto( canvas, page, a, 'Item 1 line', `${ T } A line` );
		await selectBlock( canvas, page, a );
		await page.getByRole( 'textbox', { name: 'Item 1 link', exact: true } ).fill( plan.link );
		const b = await insertSection( page );
		for ( let i = 1; i <= 3; i++ ) {
			await typeInto( canvas, page, b, `Item ${ i } title`, `${ T } B${ i }` );
		}
		check( await canvas.locator( `[data-block="${ a }"] .mizzey-trust__item` ).count() === 3, 'G11 the section shows its three items in the editor, as on the storefront' );
		const typed = await page.evaluate( ( ids ) => ids.map( ( id ) => ( wp.data.select( 'core/block-editor' ).getBlockAttributes( id ).items || [] ).map( ( i ) => i.title ) ), [ a, b ] );
		check( JSON.stringify( typed ) === JSON.stringify( [ [ `${ T } A1`, `${ T } A2`, `${ T } A3` ], [ `${ T } B1`, `${ T } B2`, `${ T } B3` ] ] ), `SSC-01 the words typed in place are each section's own: ${ JSON.stringify( typed ) }` );
		await save( page );
		let live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( firstTitles( live ) ) === JSON.stringify( [ `${ T } A1`, `${ T } B1` ] ), `SSC-02 [en] the storefront shows both sections in their order: ${ JSON.stringify( firstTitles( live ) ) }` );
		check( JSON.stringify( live.sections[ 0 ] && live.sections[ 0 ].titles ) === JSON.stringify( [ `${ T } A1`, `${ T } A2`, `${ T } A3` ] ), 'SSC-01 [en] each item shows the words typed for it' );
		check( live.sections[ 0 ] && live.sections[ 0 ].links[ 0 ] === plan.link, `SSC-02 [en] the item's line links where it was set: ${ live.sections[ 0 ] && live.sections[ 0 ].links[ 0 ] }` );

		// ---- Reordered against the second instance, previewed, then published ----------------------------------
		step = 'reorder and preview';
		await selectBlock( canvas, page, b );
		await canvas.locator( `[data-block="${ b }"]` ).getByRole( 'textbox', { name: 'Item 1 title', exact: true } ).click();
		await page.locator( 'button[aria-label="Move up"]' ).first().click();
		await page.waitForFunction( ( id ) => wp.data.select( 'core/block-editor' ).getBlockOrder()[ 0 ] === id, b, { timeout: TIMEOUT } );
		check( true, 'HOME-11 the second section moves above the first with the block toolbar' );
		let preview = null;
		const toggle = page.locator( '.editor-preview-dropdown__toggle' );
		if ( await toggle.count() ) {
			await toggle.first().click();
			const [ popup ] = await Promise.all( [ editor.waitForEvent( 'page', { timeout: TIMEOUT } ), page.getByRole( 'menuitem', { name: /Preview in new tab/ } ).click() ] );
			// WordPress opens the tab first and sends it to the preview once the autosave is written.
			await popup.waitForURL( /[?&]preview(?:_id)?=/, { timeout: TIMEOUT } );
			await popup.waitForLoadState( 'domcontentloaded', { timeout: TIMEOUT } );
			preview = popup.url();
			await popup.close();
		}
		check( !! preview && /preview/.test( preview ), `SSC-13 the editor's Preview opens a preview (${ preview })` );
		const previewView = preview ? await sectionsOn( editor, preview ) : { sections: [] };
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( firstTitles( previewView ) ) === JSON.stringify( [ `${ T } B1`, `${ T } A1` ] ), `SSC-13 the preview shows the new order before it is published: ${ JSON.stringify( firstTitles( previewView ) ) }` );
		check( JSON.stringify( firstTitles( live ) ) === JSON.stringify( [ `${ T } A1`, `${ T } B1` ] ), `SSC-13 the live page keeps the old order until then: ${ JSON.stringify( firstTitles( live ) ) }` );
		await save( page );
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( firstTitles( live ) ) === JSON.stringify( [ `${ T } B1`, `${ T } A1` ] ), `HOME-11 published, the storefront shows the new order: ${ JSON.stringify( firstTitles( live ) ) }` );

		// ---- Hidden, its content kept -----------------------------------------------------------------------------
		step = 'hide';
		await selectBlock( canvas, page, a );
		const show = page.getByRole( 'checkbox', { name: 'Show on the site', exact: true } );
		check( await show.isChecked(), 'SSC-03 a section is shown on the site by default' );
		await show.click();
		check( ! ( await show.isChecked() ), 'SSC-03 "Show on the site" switches off' );
		check( await canvas.locator( `[data-block="${ a }"].is-mizzey-hidden` ).count() === 1, 'SSC-03 the editor marks the hidden section as hidden' );
		await save( page );
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( firstTitles( live ) ) === JSON.stringify( [ `${ T } B1` ] ), `SSC-03 the storefront no longer shows the hidden section: ${ JSON.stringify( firstTitles( live ) ) }` );
		canvas = await openEditor( page, plan.en.edit );
		const kept = await page.evaluate( ( [ n, tag ] ) => wp.data.select( 'core/block-editor' ).getBlocks().filter( ( x ) => x.name === n ).map( ( x ) => ( { visible: x.attributes.visible, title: x.attributes.items[ 0 ].title } ) ), [ SECTION, T ] );
		check( kept.length === 2 && kept[ 1 ].visible === false && kept[ 1 ].title === `${ T } A1`, `SSC-03 reopened, the editor still holds the hidden section's content: ${ JSON.stringify( kept ) }` );
		check( await canvas.getByText( `${ T } A2`, { exact: true } ).count() === 1, 'SSC-03 its words are still there to edit' );

		// ---- The Arabic front page: its own inserter and its own words ------------------------------------------
		step = 'Arabic';
		canvas = await openEditor( page, plan.ar.edit );
		seen = await inserterItems( page );
		check( JSON.stringify( seen.data.sort() ) === JSON.stringify( [ ...plan.library ].sort() ), `FR-003 [ar] the Arabic front page's inserter lists only the library's sections: ${ seen.data.join( ', ' ) }` );
		const c = await insertSection( page );
		const arabic = [ 'أصلي', 'توصيل', 'إرجاع' ];
		for ( let i = 1; i <= 3; i++ ) {
			await typeInto( canvas, page, c, `Item ${ i } title`, `${ T } ${ arabic[ i - 1 ] }` );
		}
		await typeInto( canvas, page, c, 'Item 1 line', `${ T } ضمان الأصالة` );
		await save( page );
		const ar = await sectionsOn( visitor, plan.ar.view );
		check( ar.dir === 'rtl' && JSON.stringify( ar.sections.map( ( s ) => s.titles ) ) === JSON.stringify( [ arabic.map( ( w ) => `${ T } ${ w }` ) ] ), `SSC-12 [ar] the Arabic storefront shows its own words, right to left: ${ ar.dir } ${ JSON.stringify( ar.sections.map( ( s ) => s.titles ) ) }` );
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( firstTitles( live ) ) === JSON.stringify( [ `${ T } B1` ] ), 'SSC-12 [en] the English storefront is unchanged by the Arabic edit' );

		// ---- Any other page keeps the ordinary inserter --------------------------------------------------------
		step = 'other page';
		await openEditor( page, plan.other.edit );
		seen = await inserterItems( page );
		check( seen.data.includes( 'core/paragraph' ) && seen.data.includes( 'core/columns' ) && seen.data.length > plan.library.length, `FR-003 another page's inserter is unchanged (${ seen.data.length } blocks, core/paragraph and core/columns among them)` );
		check( errors.length === 0, `no script error from the library's scripts${ errors.length ? ': ' + errors.slice( 0, 3 ).join( ' | ' ) : '' } (other script errors in this editor: ${ otherErrors })` );
	} catch ( error ) {
		check( false, `stopped at "${ step }": ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
		await page.screenshot( { path: `${ plan.shots }/t106-failure.png` } ).catch( () => {} );
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks } ) );
} )();
