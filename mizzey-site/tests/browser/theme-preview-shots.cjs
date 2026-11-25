#!/usr/bin/env node
/**
 * Screenshots of the component preview (spec 066, slice 1) for comparison with C1-Components (K-1, K-2).
 *
 *     node mizzey-site/tests/browser/theme-preview-shots.cjs <output folder>
 *
 * Writes preview-<ltr|rtl>-<390|1440>.png, full page. The hover, pressed and focus samples are forced with the
 * browser's own pseudo-class override, so the page shows each state as the board draws it; the open select and the
 * tooltips are opened the way a person would.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { serve, loadPlaywright } = require( './_theme-preview.cjs' );

const STATE = { hover: [ 'hover' ], active: [ 'hover', 'active' ], 'focus-visible': [ 'focus', 'focus-visible' ], focus: [ 'focus', 'focus-visible' ] };

async function forceStates( page ) {
	const cdp = await page.context().newCDPSession( page );
	await cdp.send( 'DOM.enable' );
	await cdp.send( 'CSS.enable' );
	const { root } = await cdp.send( 'DOM.getDocument', { depth: -1 } );
	for ( const [ state, classes ] of Object.entries( STATE ) ) {
		const { nodeIds } = await cdp.send( 'DOM.querySelectorAll', { nodeId: root.nodeId, selector: `[data-force="${ state }"]` } );
		for ( const cell of nodeIds ) {
			const { nodeId } = await cdp.send( 'DOM.querySelector', { nodeId: cell, selector: 'input, textarea, button, a' } );
			if ( nodeId ) {
				await cdp.send( 'CSS.forcePseudoState', { nodeId, forcedPseudoClasses: classes } );
			}
		}
	}
}

( async () => {
	const out = path.resolve( process.argv[ 2 ] || '.' );
	fs.mkdirSync( out, { recursive: true } );
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found.' } ) );
		process.exit( 2 );
	}
	const server = await serve();
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	const files = [];
	try {
		for ( const dir of [ 'ltr', 'rtl' ] ) {
			for ( const width of [ 390, 1440 ] ) {
				const page = await ( await browser.newContext( { viewport: { width, height: 900 } } ) ).newPage();
				await page.goto( `${ server.url }/tests/preview-${ dir }.html`, { waitUntil: 'networkidle' } );
				await page.evaluate( () => document.fonts.ready );
				await page.locator( '[data-force="open"] .mizzey-select__button' ).click();
				await page.evaluate( () => document.querySelectorAll( '[data-force="tips"] .mizzey-tooltip-host' ).forEach( ( h ) => h.classList.add( 'is-shown' ) ) );
				await forceStates( page );
				await page.waitForTimeout( 1500 );
				const file = path.join( out, `preview-${ dir }-${ width }.png` );
				await page.screenshot( { path: file, fullPage: true } );
				files.push( file );
				await page.context().close();
			}
		}
	} finally {
		await browser.close();
		server.close();
	}
	console.log( JSON.stringify( { files } ) );
} )();
