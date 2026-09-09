#!/usr/bin/env node
/**
 * Build the deployable artifact.
 *
 * The CoreX shared-host builder assembles a flat WordPress tree from source, never from the linked
 * runtime, and packages a client from `sites/<slug>/`. `tools/corex-sync.mjs` links this repository
 * in as `sites/mizzey`, so the stock builder works unmodified: nothing here patches CoreX, and no
 * Mizzey path is ever written into the CoreX repository.
 *
 * Usage:
 *   node tools/build-dist.mjs               build and verify, dev vendor as-is
 *   node tools/build-dist.mjs --production  swap to a --no-dev vendor first, then restore
 *   node tools/build-dist.mjs --dry-run     plan only, write nothing
 *
 * Output lands in <pinned CoreX>/dist, which is git-ignored there and is the only thing a deploy
 * receives. It contains no .git, node_modules, tests, .env or wp-config.php.
 *
 * On --production, and why it is not the default. The builder copies CoreX's `vendor/` verbatim.
 * Our vendor carries dev dependencies, because corex#201 means a --no-dev install silently loses
 * `wp corex migrate`, `doctor`, `reset` and four more. So the default build ships Pest, PHPUnit,
 * Mockery and PHPStan into the artifact, which is wrong for production but keeps the CLI whole.
 * --production trades the other way: a lean artifact whose CoreX CLI is missing those commands.
 * Neither is right. Both are honest. The choice disappears when corex#201 is fixed.
 * See COREX-WORKAROUNDS.md.
 */

import { existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const COREX = resolve(REPO, '..', 'app', 'corex');
const CLIENT = 'mizzey';

const DRY_RUN = process.argv.includes('--dry-run');
const PRODUCTION = process.argv.includes('--production');

function run(script, args) {
	execFileSync('node', [join(COREX, 'scripts', script), ...args], { cwd: COREX, stdio: 'inherit' });
}

// The builder reads the linked source, so a stale or missing link would quietly package the wrong
// thing. Checking first turns that into a refusal instead of a bad artifact.
try {
	execFileSync('node', [join(REPO, 'tools', 'corex-sync.mjs'), '--check'], { stdio: 'inherit' });
} catch {
	console.error('\nThe runtime is not wired. Run: node tools/corex-sync.mjs');
	process.exit(1);
}

if (!existsSync(join(COREX, 'sites', CLIENT))) {
	console.error(`\nsites/${CLIENT} is not linked inside ${COREX}. Run: node tools/corex-sync.mjs`);
	process.exit(1);
}

function composer(args) {
	execFileSync('composer', [...args, '--no-interaction'], { cwd: COREX, stdio: 'inherit', shell: true });
}

if (PRODUCTION && !DRY_RUN) {
	console.log('\nSwapping to a production vendor tree (no dev dependencies).');
	composer(['install', '--no-dev']);
}

try {
	run('build-shared-host-dist.mjs', [`--client=${CLIENT}`, ...(DRY_RUN ? ['--dry-run'] : [])]);

	if (!DRY_RUN) {
		run('verify-shared-host-dist.mjs', []);
	}
} finally {
	// Restore unconditionally. A failed build must not leave the local runtime with a crippled CLI.
	if (PRODUCTION && !DRY_RUN) {
		console.log('\nRestoring the development vendor tree.');
		composer(['install']);
	}
}

if (!DRY_RUN) {
	console.log(`\nArtifact: ${join(COREX, 'dist')}`);
	if (PRODUCTION) {
		console.log(
			'Built --production: lean vendor, but wp corex migrate / doctor / reset / readiness /\n' +
				'version / security reset-login / docs:generate are absent in this artifact until\n' +
				'corex#201 is fixed. Run migrations from a dev-vendor checkout, not from the deploy.',
		);
	} else {
		console.log(
			'Built with dev dependencies present, so the artifact carries the test toolchain.\n' +
				'Use --production for a deployable artifact, and read the caveat it prints.',
		);
	}
}
