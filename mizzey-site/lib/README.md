# Libraries bundled in mizzey-site

Our own code's third-party libraries, committed so the release pack carries them. Not WordPress plugins (D-24).

| Library | Version | Why | Licence |
|---|---|---|---|
| mpdf/mpdf | 8.3.1 | Renders the invoice PDF (specs/023-invoice), shaping Arabic correctly | GPL-2.0-only |
| setasign/fpdi, psr/log, psr/http-message, myclabs/deep-copy, paragonie/random_compat, mpdf/psr-* | as in `../composer.lock` | mPDF's own dependencies | MIT |

## Rebuilding

From `mizzey-site/`, with Composer 2:

1. `composer install --no-dev --prefer-dist` (the versions are pinned by `composer.lock`).
2. Keep only DejaVu Sans in `lib/mpdf/mpdf/ttfonts/`: delete every file there except `DejaVuSans.ttf` and
   `DejaVuSans-Bold.ttf`.
3. Delete the libraries' markdown files and mPDF's `tmp/`, `.github/`, `phpstan*`, `phpunit.xml` and `ruleset.xml`;
   keep every licence file.
4. Run `t64-invoice`.

An upgrade goes through the regression path in `docs/stack-and-upgrades.md`.
