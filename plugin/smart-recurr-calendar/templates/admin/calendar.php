<?php
/**
 * Calendar dashboard screen — modern card layout.
 *
 * Expects: $context (array from SmartRecur_Calendar_View::month_context()).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $context */
$add_url   = admin_url( 'admin.php?page=smartrecur-appointments&action=edit' );
$theme     = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
$upcoming  = $context['upcoming'];
$total_app = 0;
foreach ( $context['weeks'] as $w ) {
	foreach ( $w as $c ) {
		if ( $c ) {
			$total_app += count( $c['events'] );
		}
	}
}
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div>
				<div class="sr-appbar-title">SmartRecur</div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Calendar', 'smartrecur' ); ?></div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<?php if ( $context['can_book'] ) : ?>
				<a class="sr-btn sr-btn-primary" href="<?php echo esc_url( $add_url ); ?>">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New appointment', 'smartrecur' ); ?>
				</a>
			<?php endif; ?>
			<a class="sr-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=smartrecur-settings' ) ); ?>">
				<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<div class="sr-dash">

		<!-- Calendar card -->
		<div class="sr-card sr-card-pad-0" style="padding:20px 22px;">
			<div class="sr-cal-head">
				<div class="sr-cal-head-left">
					<span class="sr-cal-month"><?php echo esc_html( $context['label'] ); ?></span>
					<span class="sr-pill"><span class="dashicons dashicons-clock"></span> <?php echo esc_html( $context['hours_label'] ); ?></span>
				</div>
				<div class="sr-cal-head-right">
					<a class="sr-btn sr-btn-icon" href="<?php echo esc_url( $context['prev_url'] ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'smartrecur' ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</a>
					<a class="sr-btn sr-btn-sm" href="<?php echo esc_url( $context['today_url'] ); ?>"><?php esc_html_e( 'Today', 'smartrecur' ); ?></a>
					<a class="sr-btn sr-btn-icon" href="<?php echo esc_url( $context['next_url'] ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'smartrecur' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				</div>
			</div>

			<table class="sr-cal-grid">
				<thead>
					<tr>
						<?php foreach ( $context['weekday_labels'] as $label ) : ?>
							<th scope="col"><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $context['weeks'] as $week ) : ?>
						<tr>
							<?php foreach ( $week as $cell ) : ?>
								<td>
									<?php if ( null === $cell ) : ?>
										<div class="sr-cal-cell is-empty"></div>
									<?php else : ?>
										<?php
										$classes = 'sr-cal-cell';
										if ( $cell['today'] ) {
											$classes .= ' is-today';
										}
										if ( $cell['closed'] ) {
											$classes .= ' is-closed';
										}
										$cell_tag  = ( $context['can_book'] && ! $cell['closed'] ) ? 'a' : 'div';
										$cell_href = ( 'a' === $cell_tag ) ? ' href="' . esc_url( add_query_arg( 'date', $cell['date'], $add_url ) ) . '"' : '';
										?>
										<<?php echo esc_html( $cell_tag ) . $cell_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="<?php echo esc_attr( $classes ); ?>">
											<div class="sr-cal-daynum">
												<span class="sr-cal-daynum-badge"><?php echo esc_html( $cell['day'] ); ?></span>
												<?php if ( $cell['closed'] ) : ?>
													<span class="sr-cal-lock dashicons dashicons-lock"></span>
												<?php endif; ?>
											</div>
											<div class="sr-cal-events">
												<?php foreach ( array_slice( $cell['events'], 0, 4 ) as $event ) : ?>
													<span class="sr-cal-event is-<?php echo esc_attr( strtolower( $event['status'] ) ); ?>"
														style="border-left-color:<?php echo esc_attr( $event['color'] ); ?>"
														title="<?php echo esc_attr( $event['title'] . ( $event['client'] ? ' — ' . $event['client'] : '' ) ); ?>">
														<?php if ( $event['time'] ) : ?>
															<span class="sr-cal-event-time"><?php echo esc_html( $event['time'] ); ?></span>
														<?php endif; ?>
														<?php echo esc_html( $event['title'] ); ?>
													</span>
												<?php endforeach; ?>
												<?php if ( count( $cell['events'] ) > 4 ) : ?>
													<span class="sr-cal-event" style="border-left-color:transparent;color:var(--sr-text-muted);">
														+<?php echo (int) ( count( $cell['events'] ) - 4 ); ?> <?php esc_html_e( 'more', 'smartrecur' ); ?>
													</span>
												<?php endif; ?>
											</div>
										</<?php echo esc_html( $cell_tag ); ?>>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Upcoming card -->
		<div class="sr-card">
			<div class="sr-card-head">
				<span class="sr-card-title">
					<span class="dashicons dashicons-portfolio"></span>
					<?php esc_html_e( 'Upcoming', 'smartrecur' ); ?>
					<span class="sr-badge sr-badge-count"><?php echo (int) count( $upcoming ); ?></span>
				</span>
				<span class="sr-help"><?php esc_html_e( '90 days', 'smartrecur' ); ?></span>
			</div>
			<?php if ( empty( $upcoming ) ) : ?>
				<div class="sr-empty">
					<div class="sr-empty-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
					<div class="sr-empty-title"><?php esc_html_e( 'No upcoming appointments', 'smartrecur' ); ?></div>
					<div class="sr-empty-sub"><?php esc_html_e( 'Click a day on the calendar to schedule one.', 'smartrecur' ); ?></div>
				</div>
			<?php else : ?>
				<div class="sr-upcoming-list">
					<?php foreach ( array_slice( $upcoming, 0, 12 ) as $item ) : ?>
						<a class="sr-upcoming-item" href="<?php echo esc_url( $item['edit_url'] ); ?>">
							<span class="sr-upcoming-dot" style="background:<?php echo esc_attr( $item['color'] ); ?>"></span>
							<span class="sr-upcoming-body">
								<span class="sr-upcoming-title"><?php echo esc_html( $item['title'] ); ?></span>
								<span class="sr-upcoming-meta">
									<?php
									echo esc_html( date_i18n( 'D j M', strtotime( $item['date'] ) ) );
									if ( $item['time'] ) {
										echo ' · ' . esc_html( $item['time'] );
									}
									if ( $item['client'] ) {
										echo ' · ' . esc_html( $item['client'] );
									}
									?>
								</span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

	</div>
</div>
