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

# WPML's downloaded configuration. When a plugin is activated WPML asks its publisher's host for a configuration
# index, and a downloaded file replaces the plugin's bundled wpml-config.xml. That makes the publisher's host an
# input of this baseline, and a host that cannot reach it gets a different one. To build the baseline such a host
# gets, on purpose and repeatably:
#
#   MIZZEY_WPML_REMOTE_CONFIG=off MIZZEY_CONFIRM_RESET=yes sh .../reset-runtime.sh ../app/wp
#
# It sets WPML's own switch, ICL_REMOTE_WPML_CONFIG_DISABLED, in the runtime's wp-config.php, so no request is
# made by the command line or by the web server, and nothing is written under wp-content. A reset without the
# variable removes the switch again. The mode is read back at the end, and the reset fails if the runtime is not
# in the mode that was asked for.
REMOTE_CONFIG="${MIZZEY_WPML_REMOTE_CONFIG:-on}"
case "$REMOTE_CONFIG" in
  off) $WP config set ICL_REMOTE_WPML_CONFIG_DISABLED true --raw --type=constant >/dev/null ;;
  on) $WP config delete ICL_REMOTE_WPML_CONFIG_DISABLED --type=constant >/dev/null 2>&1 || true ;;
  *) echo "refusing: MIZZEY_WPML_REMOTE_CONFIG is '$REMOTE_CONFIG', not on or off" >&2; exit 2 ;;
esac

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
# After the admin visit, because WooCommerce only registers its endpoint slugs as translatable strings when it
# runs in an admin context. See the file for the measurement.
$WP eval-file "$HERE/ia-endpoints.php"

DOWNLOADED="$($WP eval 'echo is_object( get_option( "wpml_config_index" ) ) ? "downloaded" : "not downloaded";')"
echo "WPML remote configuration: $REMOTE_CONFIG ($DOWNLOADED)"
if [ "$REMOTE_CONFIG" = "off" ] && [ "$DOWNLOADED" != "not downloaded" ]; then
  echo "refusing: the baseline was asked for without WPML's downloaded configuration and holds one" >&2; exit 2
fi
