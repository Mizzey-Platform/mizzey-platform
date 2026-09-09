#!/usr/bin/env node
/**
 * Wire the Mizzey runtime against the pinned CoreX release.
 *
 * The client source in this repository never lives inside the CoreX repository, and CoreX never
 * contains a line of Mizzey. The two are joined only here, on disk, inside a runtime directory that
 * is not under version control and can be deleted and rebuilt at any time.
 *
 *   <repo>/                     mizzey-platform, the committed source
 *     corex.lock                the CoreX release this site is built against
 *     mizzey-site/              the client plugin
 *     mizzey-theme/             the client theme
 *   <repo>/../app/              the runtime, disposable, not committed
 *     corex/                    a CoreX checkout pinned to corex.lock
 *     wp/                       WordPress, with wp-content/ junctioned back to both sources
 *
 * Usage:
 *   node tools/corex-sync.mjs            wire the runtime to corex.lock
 *   node tools/corex-sync.mjs --check    report drift, change nothing, exit 1 if wrong
 *
 * Windows note: directory links are created as junctions, which need no elevation. On Linux and
 * macOS the same call produces an ordinary symlink.
 */

import { existsSync, mkdirSync, lstatSync, rmSync, symlinkSync, readdirSync, writeFileSync, readFileSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const APP = resolve(REPO, '..', 'app');
const COREX = join(APP, 'corex');
const WP = join(APP, 'wp');
const CONTENT = join(WP, 'wp-content');

const CHECK_ONLY = process.argv.includes('--check');

const problems = [];
const actions = [];

function fail(message) {
	problems.push(message);
}

function git(args, cwd) {
	return execFileSync('git', args, { cwd, encoding: 'utf8' }).trim();
}

/** Read and validate corex.lock. */
function readLock() {
	const path = join(REPO, 'corex.lock');
	if (!existsSync(path)) {
		fail('corex.lock is missing. It names the CoreX release this site is built against.');
		return null;
	}
	const lock = JSON.parse(readFileSync(path, 'utf8'));
	for (const key of ['repo', 'version', 'commit']) {
		if (!lock[key]) fail(`corex.lock has no "${key}".`);
	}
	return lock;
}

/**
 * Make sure the pinned CoreX checkout exists and sits on the pinned commit.
 * A checkout on the wrong commit is a problem to report, never a thing to silently move: an
 * upgrade is a deliberate edit to corex.lock, not a side effect of running this script.
 */
function ensureCorex(lock) {
	if (!existsSync(COREX)) {
		if (CHECK_ONLY) {
			fail(`No CoreX checkout at ${COREX}. Run without --check to create it.`);
			return false;
		}
		mkdirSync(APP, { recursive: true });
		actions.push(`clone ${lock.repo} at ${lock.version}`);
		execFileSync('git', ['clone', '--no-hardlinks', '--branch', lock.version, lock.repo, COREX], {
			stdio: 'inherit',
		});
	}

	const head = git(['rev-parse', 'HEAD'], COREX);
	if (head !== lock.commit) {
		if (CHECK_ONLY) {
			fail(`CoreX checkout is at ${head.slice(0, 8)}, corex.lock says ${lock.commit.slice(0, 8)}.`);
			return false;
		}
		actions.push(`check out ${lock.version} (${lock.commit.slice(0, 8)})`);
		git(['fetch', 'origin', '--tags'], COREX);
		git(['checkout', '--detach', lock.commit], COREX);
	}

	// Dev dependencies are installed deliberately, not by oversight. CoreX v0.42.0 builds its
	// DocsCommand eagerly in CliServiceProvider::register(), and that command needs
	// PhpParser\ParserFactory, which reaches the tree only as a transitive dependency of
	// pestphp/pest (require-dev) and is declared nowhere in composer.json. Under --no-dev the
	// construction throws and every command registered after it is lost, silently: migrate,
	// doctor, reset and version all disappear. `wp corex migrate` is step three of our upgrade
	// path, so a --no-dev runtime cannot be upgraded. See COREX-WORKAROUNDS.md.
	if (!existsSync(join(COREX, 'vendor', 'autoload.php'))) {
		if (CHECK_ONLY) {
			fail(`CoreX has no vendor/. Run without --check to install it.`);
			return false;
		}
		actions.push('composer install (with dev, see COREX-WORKAROUNDS.md)');
		execFileSync('composer', ['install', '--no-interaction'], { cwd: COREX, stdio: 'inherit', shell: true });
	}

	if (!existsSync(join(COREX, 'vendor', 'nikic', 'php-parser'))) {
		fail(
			'nikic/php-parser is missing, so wp corex migrate / doctor / reset / version will not ' +
				'register. Run composer install (without --no-dev) in ' + COREX,
		);
		return false;
	}
	return true;
}

/** Replace whatever is at `linkPath` with a link to `target`. */
function link(linkPath, target, label) {
	if (!existsSync(target)) {
		fail(`${label}: target does not exist: ${target}`);
		return;
	}

	if (existsSync(linkPath)) {
		const stat = lstatSync(linkPath);
		if (stat.isSymbolicLink()) return; // already wired
		if (CHECK_ONLY) {
			fail(`${label}: ${linkPath} is a real directory, not a link. It shadows the source.`);
			return;
		}
		// A real directory here is the failure mode that silently shadows the source, so it goes.
		actions.push(`replace stray directory ${label}`);
		rmSync(linkPath, { recursive: true, force: true });
	}

	if (CHECK_ONLY) {
		fail(`${label}: missing link.`);
		return;
	}
	mkdirSync(dirname(linkPath), { recursive: true });
	symlinkSync(target, linkPath, 'junction');
	actions.push(`link ${label}`);
}

/** The framework plugins, add-ons and parent theme, plus this repository's own client source. */
function wire() {
	if (!CHECK_ONLY) {
		for (const dir of ['plugins', 'themes', 'mu-plugins', 'uploads', 'languages']) {
			mkdirSync(join(CONTENT, dir), { recursive: true });
		}
		// WordPress ships these guards; a hand-built wp-content needs them too.
		for (const dir of [CONTENT, join(CONTENT, 'plugins'), join(CONTENT, 'themes')]) {
			const guard = join(dir, 'index.php');
			if (!existsSync(guard)) writeFileSync(guard, '<?php\n// Silence is golden.\n');
		}
	}

	for (const source of ['plugins', 'addons']) {
		const dir = join(COREX, source);
		if (!existsSync(dir)) continue;
		for (const name of readdirSync(dir)) {
			if (!name.startsWith('corex-')) continue;
			if (!lstatSync(join(dir, name)).isDirectory()) continue;
			link(join(CONTENT, 'plugins', name), join(dir, name), `plugins/${name}`);
		}
	}

	link(join(CONTENT, 'themes', 'corex'), join(COREX, 'theme'), 'themes/corex');

	// The CoreX dist builder packages a client from `sites/<slug>/`, taking any directory there whose
	// name ends in -site or -theme. This repository root already has exactly those two names, so one
	// link makes `npm run build:dist -- --client=mizzey` work with no change to CoreX at all.
	//
	// The link is created inside the pinned checkout, which is disposable and outside version control,
	// so no client path reaches the CoreX repository. It is excluded from that clone's index too, so a
	// `git status` there stays clean.
	if (!CHECK_ONLY) {
		mkdirSync(join(COREX, 'sites'), { recursive: true });
		const exclude = join(COREX, '.git', 'info', 'exclude');
		if (existsSync(exclude) && !readFileSync(exclude, 'utf8').includes('/sites/')) {
			writeFileSync(exclude, `${readFileSync(exclude, 'utf8')}\n# Client source is linked in here by tools/corex-sync.mjs. Never CoreX's to track.\n/sites/\n`);
		}
	}
	link(join(COREX, 'sites', 'mizzey'), REPO, 'sites/mizzey (dist builder input)');

	// The builder takes WordPress core from <corex>/wp, which in this layout lives at ../wp instead.
	// It copies everything there except wp-content and wp-config.php, and debug.log is refused
	// globally, so linking the dev install in ships core without shipping local state.
	link(join(COREX, 'wp'), WP, 'wp (dist builder core input)');

	// The client source. Absent until `wp corex make:site` has run, which is not an error.
	for (const [name, target] of [
		['mizzey-site', join(REPO, 'mizzey-site')],
		['mizzey-theme', join(REPO, 'mizzey-theme')],
	]) {
		const kind = name.endsWith('-theme') ? 'themes' : 'plugins';
		if (!existsSync(target)) {
			console.log(`  skip ${kind}/${name} (not scaffolded yet)`);
			continue;
		}
		link(join(CONTENT, kind, name), target, `${kind}/${name}`);
	}
}

const lock = readLock();
if (lock && ensureCorex(lock)) wire();

console.log(`\nCoreX ${lock?.version ?? '?'}  ->  ${COREX}`);
console.log(`Runtime            ${WP}`);
for (const action of actions) console.log(`  + ${action}`);

if (problems.length) {
	console.log(`\n${problems.length} problem(s):`);
	for (const problem of problems) console.log(`  FAIL  ${problem}`);
	process.exit(1);
}
console.log(actions.length ? '\nWired.' : '\nAlready wired, nothing to do.');
