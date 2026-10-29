# Two-factor sign-in: enrolment and recovery

How staff sign in with two factors, and what to do when a second factor is lost. Register row ADM-131; specification
`specs/008-admin-two-factor`. The component is WP 2FA 4.2.1, free tier. Its settings are the owner's, in the
administration under WP 2FA.

## What is required

- Administrators and shop managers must use two-factor sign-in: an authenticator app (for example Google Authenticator,
  Microsoft Authenticator or 1Password) and a set of backup codes. Email codes and passkeys are off.
- There is no grace period: a staff member without a second factor is sent to set it up at their next sign-in, and can
  do nothing else in the administration until they have.
- Customers are not asked for a second factor.
- The owner can change which roles must use it in WP 2FA's own settings. `wp mizzey security two-factor-baseline` puts
  back the baseline above; it is run by the Developer, never automatically.

## Enrolling

1. Sign in with your password. You are taken to the set-up screen.
2. Scan the QR code with your authenticator app, then type the six-digit code it shows.
3. Save the backup codes somewhere safe and offline, away from your phone. Each works once.

## A lost phone, with backup codes

Sign in with your password, choose to use a backup code, and enter one. Then set up the authenticator again on the new
phone, and generate new backup codes.

## A lost phone and lost backup codes

Another administrator resets your second factor: Users, your account, WP 2FA, remove two-factor. You then enrol again at
your next sign-in. A shop manager cannot reset an administrator.

## When someone leaves, or stops being staff

Remove their staff role or their account. If they keep a customer account, reset their second factor as above so they are
not challenged as a customer.

## The only administrator has lost everything

This needs server access and is done by the Developer, never by anyone who has only asked by email or phone. Confirm who
is asking through a second channel first.

1. Take a backup (`staging.py backup` on staging; the host's backup on production).
2. Remove the user's second factor with WP 2FA's own helper, and end their sessions:

   ```bash
   wp eval "\WP2FA\Admin\Helpers\User_Helper::remove_2fa_for_user( <user id> );"
   wp user session destroy <user id> --all
   ```

3. The user signs in with their password and enrols again at once.
4. Record what was done, when, and on whose confirmed request.

Do not delete WP 2FA's encryption key (`WP2FA_ENCRYPT_KEY`) to recover one account: every other enrolment depends on it.
Do not delete `wp_2fa_*` rows by hand: the helper removes the right ones.

## The emergency bypass

`define( 'DISABLE_2FA_LOGIN', true );` in the server configuration turns two-factor off for everyone. Use it only when the
helper above cannot run (the site does not load), for as short a time as possible, then remove it, end every staff session
(`wp user session destroy <user id> --all`) and confirm `wp mizzey security check` shows `two_factor_bypass pass`.

## The encryption key

Each installation has one `WP2FA_ENCRYPT_KEY`, set in the server configuration and never in the repository or the
database. Enrolments can only be read with the key they were made with: back it up with the server configuration, keep it
across deployments and restores, and restore it with any database restore. `wp mizzey security check` reports
`two_factor_key pass` only when the key comes from configuration and no copy is in the database.

## Known boundary

An administrator's application password reaches the REST API without a second factor: that is WordPress's own design,
and D-21 keeps application passwords for administrators. Integrations use WooCommerce API keys instead. Revoke an
application password you no longer need (Users, your account, Application Passwords).
