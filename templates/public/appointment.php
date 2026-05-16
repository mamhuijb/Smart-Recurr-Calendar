<?php
/**
 * Public, read-only appointment view (token-accessed, no login).
 *
 * Expects $ctx from SmartRecur_Public::context().
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $ctx */
$header = $ctx['header_color'] ?? '#4f46e5';
$accent = $ctx['accent_color'] ?? '#6366f1';
$status_labels = array(
	'SCHEDULED' => __( 'Scheduled', 'smartrecur' ),
	'COMPLETED' => __( 'Completed', 'smartrecur' ),
	'MISSED'    => __( 'Missed', 'smartrecur' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $ctx['title'] . ' — ' . $ctx['company'] ); ?></title>
	<style>
		body { margin:0; background:#0a0e1a; color:#e7ecf3; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
		.sr-pub-wrap { max-width:560px; margin:40px auto; padding:0 16px; }
		.sr-pub-card { background:#111726; border:1px solid #232c44; border-radius:16px; overflow:hidden; }
		.sr-pub-head { background:<?php echo esc_attr( $header ); ?>; color:#fff; padding:24px 28px; }
		.sr-pub-head h1 { margin:0; font-size:19px; font-weight:700; }
		.sr-pub-head .sr-pub-co { font-size:13px; opacity:.85; margin-top:3px; }
		.sr-pub-body { padding:24px 28px; }
		.sr-pub-row { display:flex; justify-content:space-between; gap:16px; padding:11px 0; border-bottom:1px solid #1b2235; font-size:14px; }
		.sr-pub-row:last-child { border-bottom:0; }
		.sr-pub-key { color:#9aa6bd; }
		.sr-pub-val { font-weight:600; text-align:right; }
		.sr-pub-status { display:inline-block; padding:3px 11px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(99,102,241,.18); color:#a5b4fc; }
		.sr-pub-desc { margin-top:18px; padding:14px 16px; background:#0e1422; border-radius:10px; font-size:13px; line-height:1.6; color:#c3cbdc; }
		.sr-pub-foot { padding:16px 28px; border-top:1px solid #1b2235; color:#5d6880; font-size:12px; }
		.sr-pub-dates { margin-top:8px; font-size:12px; color:#9aa6bd; }
	</style>
</head>
<body>
	<div class="sr-pub-wrap">
		<div class="sr-pub-card">
			<div class="sr-pub-head">
				<h1><?php echo esc_html( $ctx['title'] ); ?></h1>
				<div class="sr-pub-co"><?php echo esc_html( $ctx['company'] ); ?></div>
			</div>
			<div class="sr-pub-body">
				<?php if ( $ctx['next_date'] ) : ?>
					<div class="sr-pub-row">
						<span class="sr-pub-key"><?php esc_html_e( 'Next appointment', 'smartrecur' ); ?></span>
						<span class="sr-pub-val"><?php echo esc_html( date_i18n( 'l j F Y', strtotime( $ctx['next_date'] ) ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $ctx['start_time'] ) : ?>
					<div class="sr-pub-row">
						<span class="sr-pub-key"><?php esc_html_e( 'Time', 'smartrecur' ); ?></span>
						<span class="sr-pub-val"><?php echo esc_html( $ctx['start_time'] . ( $ctx['end_time'] ? ' – ' . $ctx['end_time'] : '' ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $ctx['service'] ) : ?>
					<div class="sr-pub-row">
						<span class="sr-pub-key"><?php esc_html_e( 'Service', 'smartrecur' ); ?></span>
						<span class="sr-pub-val"><?php echo esc_html( $ctx['service'] ); ?></span>
					</div>
				<?php endif; ?>
				<div class="sr-pub-row">
					<span class="sr-pub-key"><?php esc_html_e( 'Technician', 'smartrecur' ); ?></span>
					<span class="sr-pub-val"><?php echo esc_html( $ctx['technician'] ); ?></span>
				</div>
				<div class="sr-pub-row">
					<span class="sr-pub-key"><?php esc_html_e( 'Location', 'smartrecur' ); ?></span>
					<span class="sr-pub-val"><?php echo esc_html( $ctx['location'] ); ?></span>
				</div>
				<?php if ( $ctx['client'] ) : ?>
					<div class="sr-pub-row">
						<span class="sr-pub-key"><?php esc_html_e( 'For', 'smartrecur' ); ?></span>
						<span class="sr-pub-val"><?php echo esc_html( $ctx['client'] ); ?></span>
					</div>
				<?php endif; ?>
				<div class="sr-pub-row">
					<span class="sr-pub-key"><?php esc_html_e( 'Status', 'smartrecur' ); ?></span>
					<span class="sr-pub-val"><span class="sr-pub-status"><?php echo esc_html( $status_labels[ $ctx['status'] ] ?? $ctx['status'] ); ?></span></span>
				</div>

				<?php if ( $ctx['description'] ) : ?>
					<div class="sr-pub-desc"><?php echo nl2br( esc_html( $ctx['description'] ) ); ?></div>
				<?php endif; ?>

				<?php if ( count( $ctx['all_dates'] ) > 1 ) : ?>
					<div class="sr-pub-dates">
						<?php esc_html_e( 'Recurring on:', 'smartrecur' ); ?>
						<?php echo esc_html( implode( ', ', array_slice( $ctx['all_dates'], 0, 8 ) ) ); ?>
						<?php echo count( $ctx['all_dates'] ) > 8 ? ' …' : ''; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="sr-pub-foot">
				<?php echo esc_html( sprintf( /* translators: %s company */ __( 'This is a private appointment link from %s.', 'smartrecur' ), $ctx['company'] ) ); ?>
			</div>
		</div>
	</div>
</body>
</html>
