<?php
/**
 * Calendar month-grid screen.
 *
 * Expects: $context (array from SmartRecur_Calendar_View::month_context()).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $context */
$add_url = admin_url( 'admin.php?page=smartrecur-appointments&action=edit' );
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Calendar', 'smartrecur' ); ?></h1>
	<?php if ( $context['can_book'] ) : ?>
		<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add Appointment', 'smartrecur' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<div class="smartrecur-calendar-nav">
		<a class="button" href="<?php echo esc_url( $context['prev_url'] ); ?>">&laquo; <?php esc_html_e( 'Prev', 'smartrecur' ); ?></a>
		<a class="button" href="<?php echo esc_url( $context['today_url'] ); ?>"><?php esc_html_e( 'Today', 'smartrecur' ); ?></a>
		<a class="button" href="<?php echo esc_url( $context['next_url'] ); ?>"><?php esc_html_e( 'Next', 'smartrecur' ); ?> &raquo;</a>
		<span class="smartrecur-calendar-label"><?php echo esc_html( $context['label'] ); ?></span>
	</div>

	<table class="smartrecur-calendar-grid widefat">
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
						<?php if ( null === $cell ) : ?>
							<td class="smartrecur-cal-empty"></td>
						<?php else : ?>
							<td class="smartrecur-cal-day<?php echo $cell['today'] ? ' is-today' : ''; ?>">
								<div class="smartrecur-cal-daynum">
									<?php if ( $context['can_book'] ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'date', $cell['date'], $add_url ) ); ?>"
											title="<?php esc_attr_e( 'Add appointment on this day', 'smartrecur' ); ?>">
											<?php echo esc_html( $cell['day'] ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $cell['day'] ); ?>
									<?php endif; ?>
								</div>
								<div class="smartrecur-cal-events">
									<?php foreach ( $cell['events'] as $event ) : ?>
										<a class="smartrecur-cal-event smartrecur-status-<?php echo esc_attr( strtolower( $event['status'] ) ); ?>"
											href="<?php echo esc_url( $event['edit_url'] ); ?>"
											style="border-left-color:<?php echo esc_attr( $event['color'] ); ?>"
											title="<?php echo esc_attr( $event['title'] . ( $event['client'] ? ' — ' . $event['client'] : '' ) ); ?>">
											<?php if ( $event['time'] ) : ?>
												<span class="smartrecur-cal-time"><?php echo esc_html( $event['time'] ); ?></span>
											<?php endif; ?>
											<span class="smartrecur-cal-title"><?php echo esc_html( $event['title'] ); ?></span>
										</a>
									<?php endforeach; ?>
								</div>
							</td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
