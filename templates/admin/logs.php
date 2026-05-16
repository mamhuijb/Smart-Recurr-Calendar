<?php
/**
 * Email logs screen.
 *
 * Expects: $logs (array of rows from SmartRecur_Mailer::recent_logs()).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $logs */
$logs     = isset( $logs ) && is_array( $logs ) ? $logs : array();
$sr_theme = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $sr_theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-email-alt"></span></div>
			<div>
				<div class="sr-appbar-title"><?php esc_html_e( 'Email Logs', 'smartrecur' ); ?></div>
				<div class="sr-appbar-sub">SmartRecur</div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=smartrecur-logs' ) ); ?>">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<div class="sr-card sr-card-pad-0" style="padding:16px 18px;">
		<?php if ( empty( $logs ) ) : ?>
			<div class="sr-empty">
				<div class="sr-empty-icon"><span class="dashicons dashicons-email"></span></div>
				<div class="sr-empty-title"><?php esc_html_e( 'No emails sent yet', 'smartrecur' ); ?></div>
				<div class="sr-empty-sub"><?php esc_html_e( 'Send a test email or a reminder to see logs here.', 'smartrecur' ); ?></div>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'smartrecur' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'smartrecur' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'smartrecur' ); ?></th>
						<th><?php esc_html_e( 'Status', 'smartrecur' ); ?></th>
						<th><?php esc_html_e( 'Detail', 'smartrecur' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $row ) : ?>
						<tr>
							<td><?php echo esc_html( ! empty( $row['created_at'] ) ? date_i18n( 'Y-m-d H:i', strtotime( $row['created_at'] ) ) : '—' ); ?></td>
							<td><?php echo esc_html( $row['recipient'] ); ?></td>
							<td><?php echo esc_html( $row['subject'] ); ?></td>
							<td>
								<?php $ok = ( 'sent' === $row['status'] ); ?>
								<span class="smartrecur-status smartrecur-status-<?php echo $ok ? 'completed' : 'missed'; ?>">
									<?php echo $ok ? esc_html__( 'Sent', 'smartrecur' ) : esc_html__( 'Failed', 'smartrecur' ); ?>
								</span>
							</td>
							<td style="color:var(--sr-text-muted);"><?php echo esc_html( $row['error'] ?: '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:10px;">
				<?php echo esc_html( sprintf( /* translators: %d count */ __( 'Showing the last %d emails.', 'smartrecur' ), count( $logs ) ) ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>
