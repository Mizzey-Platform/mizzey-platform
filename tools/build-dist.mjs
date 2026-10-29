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
 *   node tools/build-dist.mjs               build and verify
 *   node tools/build-dist.mjs --dry-run     plan only, write nothing
 *
 * Output lands in <pinned CoreX>/dist, which is git-ignored there and is the only thing a deploy
 * receives. It contains no .git, node_modules, tests, .env or wp-config.php.
 *
 * The builder installs the artifact's own production packages into `wp-content/vendor/` with
 * Composer, and never reads or changes the checkout's `vendor/`, so the local runtime keeps its dev
 * tree and the artifact never carries one. It needs `composer` on the PATH, and the network the first
 * time.
 */

import { existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const COREX = resolve(REPO, '..', 'app', 'corex');
const CLIENT = 'mizzey';

const DRY_RUN = process.argv.includes('--dry-run');

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

run('build-shared-host-dist.mjs', [`--client=${CLIENT}`, ...(DRY_RUN ? ['--dry-run'] : [])]);

if (!DRY_RUN) {
	run('verify-shared-host-dist.mjs', []);
	console.log(`\nArtifact: ${join(COREX, 'dist')}`);
}
