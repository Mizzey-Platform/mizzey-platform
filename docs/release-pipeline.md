# The release pipeline

How a tagged commit of `main` becomes the archive the staging server runs. Decided in D-16. The server's side
of a deploy is the server's own configuration and is not in this repository.

**This repository, its releases and its workflow artifacts are public. Nothing paid is ever put in any of
them.** While that holds, a release does not contain the translation plugin family.

## Making a release

```bash
git switch main && git pull
git tag v0.1.0
git push origin v0.1.0
```

`.github/workflows/release.yml` runs on a tag of the form `vX.Y.Z` and on nothing else. It refuses a tag whose
commit is not on `main`. A tag is an engineering build number: it is not the contract's first or second release
and it claims no acceptance, so the release is published as a pre-release titled "Staging build".

A release is never replaced. If a build is wrong, fix `main` and tag the next number.

## What the workflow does

| Step | With |
|---|---|
| Runs the tooling tests, the repository checks and the PHP lint | The same commands a pull request runs |
| Downloads WordPress core at the version `stack.lock.json` records as tested, and checks it against the checksum wordpress.org publishes | `python tools/release_pack.py fetch-core` |
| Checks out CoreX at the commit `corex.lock` pins and installs its dependencies | `node tools/corex-sync.mjs` |
| Builds the flat WordPress tree | `node tools/build-dist.mjs` |
| Adds WooCommerce and the carrier plugin from wordpress.org at the versions `stack.lock.json` records, the Arabic language packs, and the manifest, and writes the archive and its checksum | `python tools/release_pack.py pack --tag <tag>` |
| Checks the checksum, and that the archive holds no paid plugin and no file the server owns | `sha256sum -c`, and the archive's own list of names |
| Publishes the two files as a release of this repository | `gh release create`, with the workflow's own token |

It uploads no workflow artifact and keeps no cache.

## The release

| | |
|---|---|
| Assets | `mizzey-<tag>.zip` and `mizzey-<tag>.zip.sha256` |
| Checksum file | One line, `<sha256>  mizzey-<tag>.zip`, which `sha256sum -c` reads |
| Layout | The WordPress root is the archive root, with no folder around it: `index.php`, `wp-admin/`, `wp-includes/`, `wp-content/`, `vendor/`, `corex-release.json`, `mizzey-release.json` |
| Entries | Files only, each `0644`, in path order, stamped with the commit's time |

Under `wp-content/`:

| Path | From |
|---|---|
| `plugins/corex-*` | CoreX at the pinned commit, every plugin and add-on its builder packages |
| `plugins/mizzey-site`, `themes/mizzey-theme`, `themes/corex` | This repository at the tag, without `tests/`, and the CoreX parent theme |
| `plugins/woocommerce`, `plugins/bosta-woocommerce` | wordpress.org |
| `languages/`, `languages/plugins/` | The Arabic language packs of WordPress and of WooCommerce, from wordpress.org |

**Not in the archive, because the server owns it:**

| Path | Why |
|---|---|
| `wp-config.php` | The server supplies it |
| `wp-content/uploads` | Written at runtime |
| `wp-content/languages/wpml` | Written at runtime: the translation plugin generates its translation files there. Without a writable folder at this path the Arabic account and checkout address words (#242) do not load |
| `wp-content/mu-plugins` | Nothing is shipped there. The staging plugin is not part of a release |
| The five translation plugins | Paid. See below |

`mizzey-release.json` names the two runtime paths under `runtime_writable`, for the deploy to link to a writable
place.

## The translation plugin family

`mizzey-release.json` lists the five plugins under `plugins.server_supplied`, each with its folder, the version
`stack.lock.json` records, and the file that carries its header:

```json
{"slug": "sitepress-multilingual-cms", "version": "4.9.7", "main_file": "sitepress-multilingual-cms/sitepress.php"}
```

The server adds them at deploy time from a folder of its own and checks each `Version:` header against that
list. The packer refuses a built tree that already holds one, and the workflow checks the finished archive
again before it publishes.

When the owner makes the repository private, moving them into the release is its own pull request. Until then
no change here may fetch, cache or publish them.

## Trying the build on a developer's machine

```bash
node tools/build-dist.mjs
python tools/release_pack.py pack --tag v0.0.0 --out ../app/release
```

This writes the two files and publishes nothing.

## Limits, stated plainly

- **The CoreX vendor tree is the development one**, with the test toolchain in it, because CoreX v0.42.0 loses
  seven CLI commands without it (corex#201, `COREX-WORKAROUNDS.md`). The manifest records it as
  `corex.vendor`. The server refuses every request under `/vendor/`.
- **A language pack is whatever wordpress.org serves for that version on the day of the build.** Translators
  update packs after a release, so two builds of the same tag on different days can differ there, and the
  manifest records each pack's checksum.
- **The server should refuse `/mizzey-release.json`** as it refuses `/corex-release.json`.
