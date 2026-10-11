<?php
/**
 * What `staging.py check` asks the staging copy on the server, printed as one JSON line.
 *
 * It shows three things in force rather than assuming them: the staging plugin is loaded, a message handed to
 * wp_mail() is captured and no mailer is built for it, and a request to an outside host is refused before it is
 * made. The message goes to an address under `.invalid`, which cannot exist, and the outside host is one reserved
 * for documentation.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING && 'staging' === wp_get_environment_type() || WP_CLI::error( 'staging only' );

$mail_dir = MIZZEY_STAGING_ROOT . '/mail';
$captured = static fn (): int => is_dir( $mail_dir ) ? count( (array) glob( $mail_dir . '/*.json' ) ) : 0;

$before  = $captured();
$mailers = did_action( 'phpmailer_init' );
$sent    = wp_mail( 'nobody@example.invalid', 'Staging check', 'Sent by staging.py check. It must be captured, not sent.' );

$answer = wp_remote_get( 'https://example.org/', array( 'timeout' => 5 ) );

echo wp_json_encode(
	array(
		'plugin_loaded'   => function_exists( 'mizzey_staging_banner' ),
		'mail_accepted'   => (bool) $sent,
		'mail_captured'   => $captured() === $before + 1,
		'mailer_built'    => did_action( 'phpmailer_init' ) > $mailers,
		'outside_request' => is_wp_error( $answer ) && 'http_request_not_executed' === $answer->get_error_code() ? 'refused' : 'made',
		'block_external'  => defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL,
		'mail_folder'     => $mail_dir,
	)
), "\n";
