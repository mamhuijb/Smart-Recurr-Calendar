<?php
/**
 * HTML reminder email — recoloured by the Appearance settings.
 *
 * Expects $smartrecur_email: subject, body, company, logo, header_color,
 * accent_color, link, link_label.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $smartrecur_email */
$e      = isset( $smartrecur_email ) && is_array( $smartrecur_email ) ? $smartrecur_email : array();
$header = $e['header_color'] ?? '#4f46e5';
$accent = $e['accent_color'] ?? '#6366f1';
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
	<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.10);">
		<div style="background:<?php echo esc_attr( $header ); ?>;padding:22px 26px;color:#ffffff;">
			<?php if ( ! empty( $e['logo'] ) ) : ?>
				<img src="<?php echo esc_url( $e['logo'] ); ?>" alt="" style="max-height:34px;display:block;">
			<?php else : ?>
				<div style="font-size:18px;font-weight:700;"><?php echo esc_html( $e['company'] ?? 'SmartRecur' ); ?></div>
			<?php endif; ?>
		</div>
		<div style="padding:26px;">
			<?php
			// The body is admin-authored plain text with {tokens} already resolved.
			$lines = explode( "\n", (string) ( $e['body'] ?? '' ) );
			foreach ( $lines as $line ) {
				echo '<p style="margin:0 0 12px;font-size:14px;line-height:1.6;">' . esc_html( $line ) . '</p>';
			}
			?>
			<?php if ( ! empty( $e['link'] ) ) : ?>
				<p style="margin:24px 0 8px;">
					<a href="<?php echo esc_url( $e['link'] ); ?>"
						style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#ffffff;padding:11px 20px;border-radius:9px;text-decoration:none;font-weight:600;font-size:14px;">
						<?php echo esc_html( $e['link_label'] ?? 'View appointment' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<div style="padding:16px 26px;border-top:1px solid #eef2f7;color:#94a3b8;font-size:12px;">
			<?php echo esc_html( $e['company'] ?? '' ); ?>
		</div>
	</div>
</body>
</html>
