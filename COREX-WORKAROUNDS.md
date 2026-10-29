# CoreX workarounds

Defects in the framework that this project works around rather than patches. CoreX is a separate
product and is never edited for one client: anything changed inside `app/corex/` is destroyed by the
next `node tools/corex-sync.mjs`.

Each entry names the CoreX issue, the workaround, and the exact condition for deleting it. When a fix
ships in a tag, bump `corex.lock`, delete the workaround, and delete the row.

| CoreX issue | Since | Delete when |
|---|---|---|

None open. [corex#201](https://github.com/MustafaShaaban/corex/issues/201), the CLI that lost seven commands under
`composer install --no-dev`, was fixed in CoreX v0.42.1 and its workaround was removed with the v0.47.0 upgrade, after the reproduction passed on a `--no-dev` build.
