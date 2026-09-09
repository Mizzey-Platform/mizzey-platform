# CoreX workarounds

Defects in the framework that this project works around rather than patches. CoreX is a separate
product and is never edited for one client: anything changed inside `app/corex/` is destroyed by the
next `node tools/corex-sync.mjs`.

Each entry names the CoreX issue, the workaround, and the exact condition for deleting it. When a fix
ships in a tag, bump `corex.lock`, delete the workaround, and delete the row.

| CoreX issue | Since | Delete when |
|---|---|---|
| [corex#201](https://github.com/MustafaShaaban/corex/issues/201) | v0.42.0 | php-parser is a declared runtime dependency, or DocsCommand is built lazily |

---

## The CLI loses half its commands under `composer install --no-dev`

**Found** 9 September 2026, setting up the Mizzey runtime. **Affects** CoreX v0.42.0 and main
(8c1467c); `git diff v0.42.0..main -- packages/cli composer.json` is empty. Reported as
[corex#201](https://github.com/MustafaShaaban/corex/issues/201).

`Corex\Cli\CliServiceProvider::register()` constructs `DocsCommand` eagerly, around line 363:

```php
$root = dirname(__DIR__, 3);
$docs = new DocsCommand(
    $this->container->make(DocsGenerator::class),
    ...
);
```

`DocsGenerator` takes a `ClassDocReader`, which reaches for `PhpParser\ParserFactory` in its
constructor. **`nikic/php-parser` appears nowhere in `composer.json`.** It arrives only as a
transitive dependency of `pestphp/pest`, which is `require-dev`.

So under `composer install --no-dev` the `make()` throws, and because the construction sits inline in
`register()` rather than behind a closure, **every command registered after that line is lost**:

```
docs:generate   reset   migrate   doctor   version   security:reset-login
```

They do not error. They are simply absent from `wp help corex`, which reads as though the release
never had them.

**Why this matters here.** `wp corex migrate` is step three of the CoreX upgrade path in
[README.md](./README.md). A production-shaped install is exactly a `--no-dev` install, and the CoreX
`dist` builder ships "vendor/ (production autoloader)". A site built that way cannot run its own
schema migration, and nothing announces it.

**Reproduce**

```bash
composer install --no-dev
wp help corex | grep migrate     # nothing
wp eval '\Corex\Boot::app()->container()->make(\Corex\Cli\Docs\DocsGenerator::class);'
# Class "PhpParser\ParserFactory" not found  at packages/cli/src/Docs/ClassDocReader.php:43
```

**Workaround.** `tools/corex-sync.mjs` installs CoreX dependencies **with** dev, and fails loudly if
`vendor/nikic/php-parser` is absent rather than letting the commands vanish quietly. No CoreX file is
touched.

**The real fix, for CoreX.** Either declare `nikic/php-parser` in `require`, or build `DocsCommand`
inside the command closure so a docs-only dependency cannot take the migration command down with it.
The second is better: no command should be able to unregister its neighbours.

### What it costs the deploy

The CoreX `dist` builder copies `vendor/` verbatim, so the defect reaches the artifact and there is no
good answer until it is fixed. Only a choice:

| Build | Vendor | CoreX CLI in the artifact |
|---|---|---|
| `node tools/build-dist.mjs` | dev included, 125 MB | whole |
| `node tools/build-dist.mjs --production` | lean, 108 MB | seven commands missing |

`--production` swaps to a `--no-dev` vendor, builds, and restores the dev tree in a `finally`, so a
failed build never leaves the local runtime with a crippled CLI. Both paths print which trade they
made. Neither is correct. The choice disappears when corex#201 lands.

**Operational consequence today:** run `wp corex migrate` from a dev-vendor checkout, never from a
`--production` deploy, because on that artifact the command does not exist and says nothing.
