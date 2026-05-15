<?php
/**
 * [smartrecur] shortcode — renders the calendar natively on the front end.
 *
 * No SPA: the same server-rendered month grid used in wp-admin is output
 * directly into the page. Logged-in users with the `smartrecur_view`
 * capability see the calendar; everyone else gets a sign-in prompt.
 *
 * Attributes:
 *   view      — calendar (default). Reserved for future view types.
 *   client_id — optional; restrict the calendar to one client's appointments.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Shortcode {

	/**
	 * Register the shortcode and its assets.
	 */
	public static function register() {
		add_shortcode( 'smartrecur', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) the front-end stylesheet.
	 */
	public static function register_assets() {
		wp_register_style(
			'smartrecur-admin',
			SMARTRECUR_PLUGIN_URL . 'assets/css/smartrecur-admin.css',
			array(),
			SMARTRECUR_VERSION
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'      => 'calendar',
				'client_id' => '',
			),
			$atts,
			'smartrecur'
		);

		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink() );
			return '<div class="smartrecur-frontend smartrecur-login-required">'
				. esc_html__( 'You must be signed in to view this calendar.', 'smartrecur' )
				. ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in', 'smartrecur' ) . '</a>'
				. '</div>';
		}

		if ( ! current_user_can( 'smartrecur_view' ) ) {
			return '<div class="smartrecur-frontend smartrecur-login-required">'
				. esc_html__( 'Your account does not have access to this calendar.', 'smartrecur' )
				. '</div>';
		}

		wp_enqueue_style( 'smartrecur-admin' );
		nocache_headers();

		$base_url = get_permalink();
		$context  = SmartRecur_Calendar_View::month_context(
			$base_url,
			current_user_can( 'smartrecur_book' )
				? admin_url( 'admin.php?page=smartrecur-appointments&action=edit' )
				: '#'
		);

		ob_start();
		?>
		<div class="smartrecur-frontend smartrecur-admin">
			<div class="smartrecur-calendar-nav">
				<a class="button" href="<?php echo esc_url( $context['prev_url'] ); ?>">&laquo; <?php esc_html_e( 'Prev', 'smartrecur' ); ?></a>
				<a class="button" href="<?php echo esc_url( $context['today_url'] ); ?>"><?php esc_html_e( 'Today', 'smartrecur' ); ?></a>
				<a class="button" href="<?php echo esc_url( $context['next_url'] ); ?>"><?php esc_html_e( 'Next', 'smartrecur' ); ?> &raquo;</a>
				<span class="smartrecur-calendar-label"><?php echo esc_html( $context['label'] ); ?></span>
			</div>
			<table class="smartrecur-calendar-grid">
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
										<div class="smartrecur-cal-daynum"><?php echo esc_html( $cell['day'] ); ?></div>
										<div class="smartrecur-cal-events">
											<?php foreach ( $cell['events'] as $event ) : ?>
												<span class="smartrecur-cal-event smartrecur-status-<?php echo esc_attr( strtolower( $event['status'] ) ); ?>"
													style="border-left-color:<?php echo esc_attr( $event['color'] ); ?>"
													title="<?php echo esc_attr( $event['title'] . ( $event['client'] ? ' — ' . $event['client'] : '' ) ); ?>">
													<?php if ( $event['time'] ) : ?>
														<span class="smartrecur-cal-time"><?php echo esc_html( $event['time'] ); ?></span>
													<?php endif; ?>
													<span class="smartrecur-cal-title"><?php echo esc_html( $event['title'] ); ?></span>
												</span>
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
		<?php
		return (string) ob_get_clean();
	}
}
