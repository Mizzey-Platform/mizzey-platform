/**
 * Serves the theme's component preview (mizzey-theme/tests/preview.php) for the browser checks of spec 066, slice 1,
 * without WordPress: the page is rendered by the PHP command line in English (ltr) or Arabic (rtl), the theme's
 * stylesheets and script are served from mizzey-theme/, and the Interactivity API module WordPress ships is served
 * at /wp-interactivity.js from the local runtime (read only). Shared by theme-components.cjs and
 * theme-preview-shots.cjs.
 *
 * The runtime is found from MIZZEY_WP_PATH, or as app/wp in a folder above the repository (the developer's layout).
 * Playwright is test tooling on the developer's machine, not a dependency of the site: PLAYWRIGHT_DIR, or the copy
 * `npx playwright` has cached; when it has no Chromium of its own, the installed Chrome is used.
 */

const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const http = require( 'node:http' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const REPO = path.resolve( __dirname, '..', '..', '..' );
const THEME = path.join( REPO, 'mizzey-theme' );
const MODULE = path.join( 'wp-includes', 'js', 'dist', 'script-modules', 'interactivity', 'index.min.js' );
const TYPES = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.json': 'application/json', '.svg': 'image/svg+xml', '.html': 'text/html; charset=utf-8' };

function findInteractivity() {
	const candidates = [];
	if ( process.env.MIZZEY_WP_PATH ) {
		candidates.push( path.join( process.env.MIZZEY_WP_PATH, MODULE ) );
	}
	for ( let dir = REPO; dir !== path.dirname( dir ); dir = path.dirname( dir ) ) {
		candidates.push( path.join( dir, 'app', 'wp', MODULE ) );
	}
	return candidates.find( ( file ) => fs.existsSync( file ) ) || null;
}

function render( dir ) {
	return execFileSync( 'php', [ path.join( THEME, 'tests', 'preview.php' ), dir ], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 } );
}

/** Start the server; resolves to { url, close }. */
function serve() {
	const module = findInteractivity();
	if ( ! module ) {
		throw new Error( 'The Interactivity API module was not found. Set MIZZEY_WP_PATH to a WordPress install.' );
	}
	const pages = { ltr: render( 'ltr' ), rtl: render( 'rtl' ) };
	const server = http.createServer( ( req, res ) => {
		const url = new URL( req.url, 'http://127.0.0.1' );
		const send = ( status, type, body ) => {
			res.writeHead( status, { 'Content-Type': type, 'Cache-Control': 'no-store' } );
			res.end( body );
		};
		const page = url.pathname.match( /^\/tests\/preview-(ltr|rtl)\.html$/ );
		if ( page ) {
			return send( 200, TYPES[ '.html' ], pages[ page[ 1 ] ] );
		}
		if ( url.pathname === '/favicon.ico' ) {
			return send( 204, 'image/x-icon', '' );
		}
		if ( url.pathname === '/wp-interactivity.js' ) {
			return send( 200, TYPES[ '.js' ], fs.readFileSync( module ) );
		}
		const file = path.normalize( path.join( THEME, decodeURIComponent( url.pathname ) ) );
		if ( ! file.startsWith( THEME + path.sep ) || ! fs.existsSync( file ) || fs.statSync( file ).isDirectory() ) {
			return send( 404, 'text/plain', 'not found' );
		}
		return send( 200, TYPES[ path.extname( file ) ] || 'application/octet-stream', fs.readFileSync( file ) );
	} );
	return new Promise( ( resolve ) => {
		server.listen( 0, '127.0.0.1', () => {
			resolve( { url: `http://127.0.0.1:${ server.address().port }`, close: () => server.close() } );
		} );
	} );
}

/** Playwright and the options to launch a Chromium it can drive. */
function loadPlaywright() {
	const candidates = [ process.env.PLAYWRIGHT_DIR, 'playwright' ].filter( Boolean );
	for ( const root of [ path.join( os.homedir(), 'AppData', 'Local', 'npm-cache', '_npx' ), path.join( os.homedir(), '.npm', '_npx' ) ] ) {
		if ( fs.existsSync( root ) ) {
			for ( const dir of fs.readdirSync( root ) ) {
				candidates.push( path.join( root, dir, 'node_modules', 'playwright' ) );
			}
		}
	}
	const chrome = [
		process.env.CHROME_PATH,
		'C:/Program Files/Google/Chrome/Application/chrome.exe',
		'/usr/bin/google-chrome',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	].filter( ( file ) => file && fs.existsSync( file ) )[ 0 ];
	for ( const candidate of candidates ) {
		try {
			const pw = require( candidate );
			if ( fs.existsSync( pw.chromium.executablePath() ) ) {
				return { pw, launch: { headless: true } };
			}
			if ( chrome ) {
				return { pw, launch: { headless: true, executablePath: chrome } };
			}
		} catch ( error ) {
			// Try the next one.
		}
	}
	return null;
}

module.exports = { serve, loadPlaywright, REPO, THEME };
