#!/bin/sh
# Reset the DISPOSABLE local runtime to a clean, repeatable baseline for the product-cost scenarios.
#
#   sh mizzey-site/tests/integration/baseline/reset-runtime.sh ../app/wp
#
# DESTRUCTIVE for the runtime database: every table is dropped and WordPress is reinstalled. Take a backup first
# (wp db export). The script refuses to run unless the path is the disposable runtime built by tools/corex-sync.mjs,
# the database host is localhost, and MIZZEY_CONFIRM_RESET=yes is set. Never point it at staging or production.
set -eu
WP_PATH="${1:?usage: reset-runtime.sh <path to the disposable runtime wp directory>}"
HERE="$(cd "$(dirname "$0")" && pwd)"
case "$(cd "$WP_PATH" && pwd)" in
  */mizzey/app/wp) ;;
  *) echo "refusing: $WP_PATH is not the disposable runtime (…/mizzey/app/wp)" >&2; exit 2 ;;
esac
[ "${MIZZEY_CONFIRM_RESET:-}" = "yes" ] || { echo "refusing: set MIZZEY_CONFIRM_RESET=yes" >&2; exit 2; }
WP="wp --path=$WP_PATH --skip-themes"
HOST="$($WP eval 'echo DB_HOST;')"
[ "$HOST" = "localhost" ] || [ "$HOST" = "127.0.0.1" ] || { echo "refusing: database host is $HOST" >&2; exit 2; }

$WP db reset --yes
$WP core install --url=http://mizzey.local --title=Mizzey --admin_user=pilot_admin \
  --admin_email=pilot-admin@example.invalid --admin_password="$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')" --skip-email
for p in woocommerce sitepress-multilingual-cms wpml-string-translation woocommerce-multilingual \
         corex-core corex-config corex-blocks corex-forms corex-guides corex-email corex-media corex-ui \
         bosta-woocommerce mizzey-site; do
  $WP plugin activate "$p" >/dev/null
done
$WP theme activate mizzey-theme >/dev/null
# Pretty permalinks and the rewrite rules, before setup.php: WPML negotiates language by directory, so the
# language segment has to sit at the root of the path and `/ar/` cannot resolve without them.
#
# The .htaccess is written here rather than by `wp rewrite flush --hard`. WordPress will not write it in this
# runtime: PHP runs as CGI, so apache_get_modules() is unavailable, got_mod_rewrite() returns false, and the hard
# flush reports success while writing nothing. That produces a baseline that looks configured and is not.
# Set the structure from PHP, not as a command argument. A leading-slash argument is rewritten into a Windows
# path by Git Bash's MSYS path conversion, which stored `/C:/Program Files/Git/%postname%/` the first time this
# was tried. Inside `wp eval` the value is PHP source, so no shell touches it, and this works the same on any
# platform. The read-back below is kept even so: it is what caught the mangling.
$WP eval 'update_option("permalink_structure", "/%postname%/");' >/dev/null
STRUCTURE="$($WP eval 'echo get_option("permalink_structure");')"
[ "$STRUCTURE" = '/%postname%/' ] || { echo "refusing: permalink_structure is $STRUCTURE" >&2; exit 2; }
cat > "$WP_PATH/.htaccess" <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
$WP rewrite flush >/dev/null

$WP eval-file "$HERE/setup.php"
$WP eval-file "$HERE/admin-visit.php"
