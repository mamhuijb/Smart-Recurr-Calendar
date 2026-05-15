<?php
/**
 * Native WordPress admin: menu, routing, and form handlers.
 *
 * Every screen is server-rendered PHP. List screens use WP_List_Table; edit
 * screens are standard WordPress forms posting back to themselves with nonce
 * verification. No SPA, no REST round-trips for the admin UI.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Admin_Pages {

	/**
	 * Wire admin hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_welcome_notice' ) );
	}

	/**
	 * Register the SmartRecur admin menu tree.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'SmartRecur', 'smartrecur' ),
			__( 'SmartRecur', 'smartrecur' ),
			'smartrecur_view',
			'smartrecur',
			array( __CLASS__, 'render_calendar' ),
			'dashicons-calendar-alt',
			28
		);

		$sub = array(
			'smartrecur'              => array( __( 'Calendar', 'smartrecur' ),     'smartrecur_view',           'render_calendar' ),
			'smartrecur-appointments' => array( __( 'Appointments', 'smartrecur' ), 'smartrecur_view',           'render_appointments' ),
			'smartrecur-clients'      => array( __( 'Clients', 'smartrecur' ),      'smartrecur_view',           'render_clients' ),
			'smartrecur-services'     => array( __( 'Services', 'smartrecur' ),     'smartrecur_manage',         'render_services' ),
			'smartrecur-technicians'  => array( __( 'Technicians', 'smartrecur' ),  'smartrecur_manage',         'render_technicians' ),
			'smartrecur-integrations' => array( __( 'Integrations', 'smartrecur' ), 'smartrecur_manage',         'render_integrations' ),
			'smartrecur-settings'     => array( __( 'Settings', 'smartrecur' ),     'smartrecur_manage',         'render_settings' ),
			'smartrecur-tools'        => array( __( 'Tools', 'smartrecur' ),        'smartrecur_manage',         'render_tools' ),
		);

		foreach ( $sub as $slug => $def ) {
			add_submenu_page( 'smartrecur', $def[0], $def[0], $def[1], $slug, array( __CLASS__, $def[2] ) );
		}
	}

	/**
	 * Enqueue the admin stylesheet + script on SmartRecur screens only.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue( $hook ) {
		if ( ! is_string( $hook ) || false === strpos( $hook, 'smartrecur' ) ) {
			return;
		}
		wp_enqueue_style(
			'smartrecur-admin',
			SMARTRECUR_PLUGIN_URL . 'assets/css/smartrecur-admin.css',
			array(),
			SMARTRECUR_VERSION
		);
		wp_enqueue_script(
			'smartrecur-admin',
			SMARTRECUR_PLUGIN_URL . 'assets/js/smartrecur-admin.js',
			array( 'jquery', 'wp-i18n' ),
			SMARTRECUR_VERSION,
			true
		);
		wp_localize_script(
			'smartrecur-admin',
			'smartrecurAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( rest_url( SMARTRECUR_REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * One-shot post-activation welcome notice.
	 */
	public static function maybe_welcome_notice() {
		if ( ! get_transient( 'smartrecur_just_activated' ) || ! current_user_can( 'smartrecur_manage' ) ) {
			return;
		}
		delete_transient( 'smartrecur_just_activated' );
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'SmartRecur Calendar is ready.', 'smartrecur' ) . '</strong> ';
		echo wp_kses(
			sprintf(
				/* translators: %s settings URL */
				__( 'Visit <a href="%s">Integrations</a> to connect Office 365, then add the [smartrecur] shortcode to a page.', 'smartrecur' ),
				esc_url( admin_url( 'admin.php?page=smartrecur-integrations' ) )
			),
			array( 'a' => array( 'href' => array() ) )
		);
		echo '</p></div>';
	}

	/* =====================================================================
	 * Helpers.
	 * =================================================================== */

	/**
	 * Current admin "action" query var (list | edit).
	 *
	 * @return string
	 */
	private static function action() {
		return isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Current "id" query var.
	 *
	 * @return string
	 */
	private static function id() {
		return isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * URL of a SmartRecur admin page with optional extra query args.
	 *
	 * @param string $page  Page slug.
	 * @param array  $args  Extra query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Render a flash notice from the `?smartrecur_msg=` query var.
	 */
	private static function flash() {
		if ( empty( $_GET['smartrecur_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$messages = array(
			'saved'   => __( 'Saved.', 'smartrecur' ),
			'deleted' => __( 'Deleted.', 'smartrecur' ),
			'error'   => __( 'Something went wrong. Check the form and try again.', 'smartrecur' ),
		);
		$key  = sanitize_key( wp_unslash( $_GET['smartrecur_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = 'error' === $key ? 'error' : 'success';
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}

	/**
	 * Redirect back to a page with a flash message and exit.
	 *
	 * @param string $page Page slug.
	 * @param string $msg  Message key.
	 * @param array  $args Extra args.
	 */
	private static function redirect( $page, $msg, array $args = array() ) {
		wp_safe_redirect( self::url( $page, array_merge( array( 'smartrecur_msg' => $msg ), $args ) ) );
		exit;
	}

	/* =====================================================================
	 * Calendar.
	 * =================================================================== */

	/**
	 * Render the calendar screen.
	 */
	public static function render_calendar() {
		if ( ! current_user_can( 'smartrecur_view' ) ) {
			wp_die( esc_html__( 'You cannot view the calendar.', 'smartrecur' ) );
		}
		self::flash();
		$context = SmartRecur_Calendar_View::month_context();
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/calendar.php';
	}

	/* =====================================================================
	 * Clients.
	 * =================================================================== */

	/**
	 * Render the clients screen (list or edit).
	 */
	public static function render_clients() {
		if ( ! current_user_can( 'smartrecur_view' ) ) {
			wp_die( esc_html__( 'You cannot view clients.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_save_client'] ) ) {
			check_admin_referer( 'smartrecur_save_client' );
			if ( ! current_user_can( 'smartrecur_manage_clients' ) ) {
				wp_die( esc_html__( 'You cannot edit clients.', 'smartrecur' ) );
			}
			$post = wp_unslash( $_POST );
			SmartRecur_Data::save_client(
				array(
					'id'       => isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '',
					'company'  => $post['company'] ?? '',
					'name'     => $post['name'] ?? '',
					'email'    => $post['email'] ?? '',
					'phone'    => $post['phone'] ?? '',
					'address'  => $post['address'] ?? '',
					'postcode' => $post['postcode'] ?? '',
				)
			);
			self::redirect( 'smartrecur-clients', 'saved' );
		}

		self::handle_delete( 'smartrecur-clients', 'smartrecur_manage_clients', array( 'SmartRecur_Data', 'delete_client' ) );

		self::flash();

		if ( 'edit' === self::action() ) {
			$client = self::id() ? SmartRecur_Data::get_client( self::id() ) : null;
			include SMARTRECUR_PLUGIN_DIR . 'templates/admin/client-edit.php';
			return;
		}

		$rows  = SmartRecur_Data::get_clients();
		$table = new SmartRecur_List_Table(
			array(
				'entity'     => 'client',
				'page_slug'  => 'smartrecur-clients',
				'columns'    => array(
					'company' => __( 'Company', 'smartrecur' ),
					'name'    => __( 'Contact', 'smartrecur' ),
					'email'   => __( 'Email', 'smartrecur' ),
					'phone'   => __( 'Phone', 'smartrecur' ),
				),
				'rows'       => $rows,
				'render_row' => array( __CLASS__, 'render_client_row' ),
			)
		);
		$table->prepare_items();
		$add_url = self::url( 'smartrecur-clients', array( 'action' => 'edit' ) );
		$title   = __( 'Clients', 'smartrecur' );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/entity-list.php';
	}

	/**
	 * Client row → cells.
	 *
	 * @param array $item Client row.
	 * @return array
	 */
	public static function render_client_row( $item ) {
		$edit   = self::url( 'smartrecur-clients', array( 'action' => 'edit', 'id' => $item['id'] ) );
		$delete = wp_nonce_url(
			self::url( 'smartrecur-clients', array( 'smartrecur_action' => 'delete', 'id' => $item['id'] ) ),
			'smartrecur_delete'
		);
		$company = $item['company'] ? $item['company'] : __( '(no company)', 'smartrecur' );
		$actions = sprintf(
			'<div class="row-actions"><span class="edit"><a href="%s">%s</a> | </span><span class="delete"><a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a></span></div>',
			esc_url( $edit ),
			esc_html__( 'Edit', 'smartrecur' ),
			esc_url( $delete ),
			esc_js( __( 'Delete this client?', 'smartrecur' ) ),
			esc_html__( 'Delete', 'smartrecur' )
		);
		return array(
			'company' => '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $company ) . '</a></strong>' . $actions,
			'name'    => esc_html( $item['name'] ),
			'email'   => $item['email'] ? '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>' : '—',
			'phone'   => esc_html( $item['phone'] ?: '—' ),
		);
	}

	/* =====================================================================
	 * Services.
	 * =================================================================== */

	/**
	 * Render the services screen.
	 */
	public static function render_services() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You cannot manage services.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_save_service'] ) ) {
			check_admin_referer( 'smartrecur_save_service' );
			$post = wp_unslash( $_POST );
			SmartRecur_Data::save_service(
				array(
					'id'                   => isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '',
					'name'                 => $post['name'] ?? '',
					'type'                 => $post['type'] ?? 'RECURRING',
					'default_duration_min' => $post['default_duration_min'] ?? 60,
					'default_location'     => $post['default_location'] ?? 'ON_SITE',
					'color'                => $post['color'] ?? '#4F46E5',
					'create_ticket'        => ! empty( $post['create_ticket'] ),
					'email_template_subject' => $post['email_template_subject'] ?? '',
					'email_template_body'    => $post['email_template_body'] ?? '',
					'reminder_days'        => isset( $post['reminder_days'] ) ? array_map( 'trim', explode( ',', (string) $post['reminder_days'] ) ) : array(),
				)
			);
			self::redirect( 'smartrecur-services', 'saved' );
		}

		self::handle_delete( 'smartrecur-services', 'smartrecur_manage', array( 'SmartRecur_Data', 'delete_service' ) );
		self::flash();

		if ( 'edit' === self::action() ) {
			$service = self::id() ? SmartRecur_Data::get_service( self::id() ) : null;
			include SMARTRECUR_PLUGIN_DIR . 'templates/admin/service-edit.php';
			return;
		}

		$rows  = SmartRecur_Data::get_services();
		$table = new SmartRecur_List_Table(
			array(
				'entity'     => 'service',
				'page_slug'  => 'smartrecur-services',
				'columns'    => array(
					'name'     => __( 'Name', 'smartrecur' ),
					'type'     => __( 'Type', 'smartrecur' ),
					'duration' => __( 'Duration', 'smartrecur' ),
					'location' => __( 'Location', 'smartrecur' ),
				),
				'rows'       => $rows,
				'render_row' => array( __CLASS__, 'render_service_row' ),
			)
		);
		$table->prepare_items();
		$add_url = self::url( 'smartrecur-services', array( 'action' => 'edit' ) );
		$title   = __( 'Services', 'smartrecur' );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/entity-list.php';
	}

	/**
	 * Service row → cells.
	 *
	 * @param array $item Service row.
	 * @return array
	 */
	public static function render_service_row( $item ) {
		$edit   = self::url( 'smartrecur-services', array( 'action' => 'edit', 'id' => $item['id'] ) );
		$delete = wp_nonce_url(
			self::url( 'smartrecur-services', array( 'smartrecur_action' => 'delete', 'id' => $item['id'] ) ),
			'smartrecur_delete'
		);
		$actions = sprintf(
			'<div class="row-actions"><span class="edit"><a href="%s">%s</a> | </span><span class="delete"><a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a></span></div>',
			esc_url( $edit ),
			esc_html__( 'Edit', 'smartrecur' ),
			esc_url( $delete ),
			esc_js( __( 'Delete this service?', 'smartrecur' ) ),
			esc_html__( 'Delete', 'smartrecur' )
		);
		$dot = '<span class="smartrecur-color-dot" style="background:' . esc_attr( $item['color'] ) . '"></span>';
		return array(
			'name'     => $dot . '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a></strong>' . $actions,
			'type'     => 'RECURRING' === $item['type'] ? esc_html__( 'Recurring', 'smartrecur' ) : esc_html__( 'One-time', 'smartrecur' ),
			'duration' => esc_html( (int) $item['default_duration_min'] . ' min' ),
			'location' => 'ON_SITE' === $item['default_location'] ? esc_html__( 'On site', 'smartrecur' ) : esc_html__( 'Remote', 'smartrecur' ),
		);
	}

	/* =====================================================================
	 * Technicians.
	 * =================================================================== */

	/**
	 * Render the technicians screen.
	 */
	public static function render_technicians() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You cannot manage technicians.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_save_technician'] ) ) {
			check_admin_referer( 'smartrecur_save_technician' );
			$post = wp_unslash( $_POST );
			SmartRecur_Data::save_technician(
				array(
					'id'     => isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '',
					'name'   => $post['name'] ?? '',
					'email'  => $post['email'] ?? '',
					'color'  => $post['color'] ?? '#10B981',
					'skills' => isset( $post['skills'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $post['skills'] ) ) ) : array(),
				)
			);
			self::redirect( 'smartrecur-technicians', 'saved' );
		}

		self::handle_delete( 'smartrecur-technicians', 'smartrecur_manage', array( 'SmartRecur_Data', 'delete_technician' ) );
		self::flash();

		if ( 'edit' === self::action() ) {
			$technician = self::id() ? SmartRecur_Data::get_technician( self::id() ) : null;
			include SMARTRECUR_PLUGIN_DIR . 'templates/admin/technician-edit.php';
			return;
		}

		$rows  = SmartRecur_Data::get_technicians();
		$table = new SmartRecur_List_Table(
			array(
				'entity'     => 'technician',
				'page_slug'  => 'smartrecur-technicians',
				'columns'    => array(
					'name'   => __( 'Name', 'smartrecur' ),
					'email'  => __( 'Email', 'smartrecur' ),
					'skills' => __( 'Skills', 'smartrecur' ),
				),
				'rows'       => $rows,
				'render_row' => array( __CLASS__, 'render_technician_row' ),
			)
		);
		$table->prepare_items();
		$add_url = self::url( 'smartrecur-technicians', array( 'action' => 'edit' ) );
		$title   = __( 'Technicians', 'smartrecur' );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/entity-list.php';
	}

	/**
	 * Technician row → cells.
	 *
	 * @param array $item Technician row.
	 * @return array
	 */
	public static function render_technician_row( $item ) {
		$edit   = self::url( 'smartrecur-technicians', array( 'action' => 'edit', 'id' => $item['id'] ) );
		$delete = wp_nonce_url(
			self::url( 'smartrecur-technicians', array( 'smartrecur_action' => 'delete', 'id' => $item['id'] ) ),
			'smartrecur_delete'
		);
		$actions = sprintf(
			'<div class="row-actions"><span class="edit"><a href="%s">%s</a> | </span><span class="delete"><a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a></span></div>',
			esc_url( $edit ),
			esc_html__( 'Edit', 'smartrecur' ),
			esc_url( $delete ),
			esc_js( __( 'Delete this technician?', 'smartrecur' ) ),
			esc_html__( 'Delete', 'smartrecur' )
		);
		$dot    = '<span class="smartrecur-color-dot" style="background:' . esc_attr( $item['color'] ) . '"></span>';
		$skills = json_decode( $item['skills'] ?? '[]', true );
		$skills = is_array( $skills ) ? implode( ', ', $skills ) : '';
		return array(
			'name'   => $dot . '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a></strong>' . $actions,
			'email'  => $item['email'] ? '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>' : '—',
			'skills' => esc_html( $skills ?: '—' ),
		);
	}

	/* =====================================================================
	 * Appointments.
	 * =================================================================== */

	/**
	 * Render the appointments screen.
	 */
	public static function render_appointments() {
		if ( ! current_user_can( 'smartrecur_view' ) ) {
			wp_die( esc_html__( 'You cannot view appointments.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_save_appointment'] ) ) {
			check_admin_referer( 'smartrecur_save_appointment' );
			if ( ! current_user_can( 'smartrecur_book' ) ) {
				wp_die( esc_html__( 'You cannot book appointments.', 'smartrecur' ) );
			}
			self::save_appointment_form( wp_unslash( $_POST ) );
			self::redirect( 'smartrecur-appointments', 'saved' );
		}

		self::handle_delete( 'smartrecur-appointments', 'smartrecur_book', array( 'SmartRecur_Data', 'delete_appointment' ) );
		self::flash();

		if ( 'edit' === self::action() ) {
			$appointment = self::id() ? SmartRecur_Data::get_appointment( self::id() ) : null;
			$clients     = SmartRecur_Data::get_clients();
			$services    = SmartRecur_Data::get_services();
			$technicians = SmartRecur_Data::get_technicians();
			$engine      = 'SmartRecur_Recurrence_Engine';
			include SMARTRECUR_PLUGIN_DIR . 'templates/admin/appointment-edit.php';
			return;
		}

		$clients_idx = SmartRecur_Data::index_by_id( SmartRecur_Data::get_clients(), 'company', 'name' );
		$service_idx = SmartRecur_Data::index_by_id( SmartRecur_Data::get_services(), 'name' );
		$tech_idx    = SmartRecur_Data::index_by_id( SmartRecur_Data::get_technicians(), 'name' );

		$rows  = SmartRecur_Data::get_appointments();
		$table = new SmartRecur_List_Table(
			array(
				'entity'     => 'appointment',
				'page_slug'  => 'smartrecur-appointments',
				'columns'    => array(
					'title'      => __( 'Title', 'smartrecur' ),
					'client'     => __( 'Client', 'smartrecur' ),
					'service'    => __( 'Service', 'smartrecur' ),
					'technician' => __( 'Technician', 'smartrecur' ),
					'next'       => __( 'Next date', 'smartrecur' ),
					'status'     => __( 'Status', 'smartrecur' ),
				),
				'rows'       => $rows,
				'render_row' => function ( $item ) use ( $clients_idx, $service_idx, $tech_idx ) {
					return self::render_appointment_row( $item, $clients_idx, $service_idx, $tech_idx );
				},
			)
		);
		$table->prepare_items();
		$add_url = self::url( 'smartrecur-appointments', array( 'action' => 'edit' ) );
		$title   = __( 'Appointments', 'smartrecur' );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/entity-list.php';
	}

	/**
	 * Appointment row → cells.
	 *
	 * @param array $item        Appointment row.
	 * @param array $clients_idx id → company map.
	 * @param array $service_idx id → name map.
	 * @param array $tech_idx    id → name map.
	 * @return array
	 */
	public static function render_appointment_row( $item, $clients_idx, $service_idx, $tech_idx ) {
		$edit   = self::url( 'smartrecur-appointments', array( 'action' => 'edit', 'id' => $item['id'] ) );
		$delete = wp_nonce_url(
			self::url( 'smartrecur-appointments', array( 'smartrecur_action' => 'delete', 'id' => $item['id'] ) ),
			'smartrecur_delete'
		);
		$actions = sprintf(
			'<div class="row-actions"><span class="edit"><a href="%s">%s</a> | </span><span class="delete"><a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a></span></div>',
			esc_url( $edit ),
			esc_html__( 'Edit', 'smartrecur' ),
			esc_url( $delete ),
			esc_js( __( 'Delete this appointment?', 'smartrecur' ) ),
			esc_html__( 'Delete', 'smartrecur' )
		);

		$dates = json_decode( $item['generated_dates'] ?? '[]', true );
		$dates = is_array( $dates ) ? $dates : array();
		sort( $dates );
		$today = current_time( 'Y-m-d' );
		$next  = '—';
		foreach ( $dates as $d ) {
			if ( $d >= $today ) {
				$next = $d;
				break;
			}
		}

		$status_labels = array(
			'SCHEDULED' => __( 'Scheduled', 'smartrecur' ),
			'COMPLETED' => __( 'Completed', 'smartrecur' ),
			'MISSED'    => __( 'Missed', 'smartrecur' ),
		);

		return array(
			'title'      => '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $item['title'] ) . '</a></strong>' . $actions,
			'client'     => esc_html( $clients_idx[ $item['client_id'] ] ?? '—' ),
			'service'    => esc_html( $service_idx[ $item['service_id'] ] ?? '—' ),
			'technician' => esc_html( $tech_idx[ $item['technician_id'] ] ?? '—' ),
			'next'       => esc_html( $next ),
			'status'     => '<span class="smartrecur-status smartrecur-status-' . esc_attr( strtolower( $item['status'] ) ) . '">' . esc_html( $status_labels[ $item['status'] ] ?? $item['status'] ) . '</span>',
		);
	}

	/**
	 * Persist the appointment edit form, regenerating recurrence dates.
	 *
	 * @param array $post Unslashed POST data.
	 */
	private static function save_appointment_form( array $post ) {
		$recurring = ! empty( $post['is_recurring'] );
		$dates     = array();

		if ( $recurring ) {
			$dates = SmartRecur_Recurrence_Engine::generate(
				array(
					'frequency'    => $post['frequency'] ?? 'MONTHLY',
					'pattern_type' => $post['pattern_type'] ?? 'ABSOLUTE',
					'day_of_month' => $post['day_of_month'] ?? 1,
					'ordinal'      => $post['ordinal'] ?? 1,
					'weekday'      => $post['weekday'] ?? 1,
					'start_month'  => $post['start_month'] ?? gmdate( 'n' ),
					'start_year'   => $post['start_year'] ?? gmdate( 'Y' ),
					'max_years'    => 5,
				)
			);
			$rule = SmartRecur_Recurrence_Engine::describe(
				array(
					'frequency'    => $post['frequency'] ?? 'MONTHLY',
					'pattern_type' => $post['pattern_type'] ?? 'ABSOLUTE',
					'day_of_month' => $post['day_of_month'] ?? 1,
					'ordinal'      => $post['ordinal'] ?? 1,
					'weekday'      => $post['weekday'] ?? 1,
					'start_month'  => $post['start_month'] ?? gmdate( 'n' ),
				)
			);
		} else {
			$single = isset( $post['single_date'] ) ? sanitize_text_field( $post['single_date'] ) : '';
			if ( $single ) {
				$dates = array( gmdate( 'Y-m-d', strtotime( $single ) ) );
			}
			$rule = __( 'One-time', 'smartrecur' );
		}

		SmartRecur_Data::save_appointment(
			array(
				'id'              => isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '',
				'title'           => $post['title'] ?? '',
				'client_id'       => $post['client_id'] ?? '',
				'service_id'      => $post['service_id'] ?? '',
				'technician_id'   => $post['technician_id'] ?? '',
				'location_type'   => $post['location_type'] ?? 'ON_SITE',
				'description'     => $post['description'] ?? '',
				'recurrence_rule' => $rule,
				'generated_dates' => $dates,
				'start_time'      => $post['start_time'] ?? '',
				'end_time'        => $post['end_time'] ?? '',
				'status'          => $post['status'] ?? 'SCHEDULED',
			)
		);
	}

	/* =====================================================================
	 * Integrations / Settings / Tools.
	 * =================================================================== */

	/**
	 * Render the integrations screen.
	 */
	public static function render_integrations() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You cannot manage integrations.', 'smartrecur' ) );
		}
		if ( ! empty( $_POST['smartrecur_integration_save'] ) ) {
			check_admin_referer( 'smartrecur_integrations' );
			self::save_integrations_form( wp_unslash( $_POST ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Integrations saved.', 'smartrecur' ) . '</p></div>';
		}
		$integrations = SmartRecur_Integration_Config::all();
		$o365_ready   = SmartRecur_Office365::is_configured();
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/integrations.php';
	}

	/**
	 * Persist the integrations form.
	 *
	 * @param array $post Unslashed POST.
	 */
	private static function save_integrations_form( array $post ) {
		$writable = array(
			'syncro'       => array( 'apiKey', 'subdomain' ),
			'invoiceninja' => array( 'apiKey', 'endpoint' ),
			'zoho'         => array( 'apiKey', 'apiSecret', 'endpoint' ),
		);
		foreach ( $writable as $type => $keys ) {
			if ( empty( $post[ $type ] ) || ! is_array( $post[ $type ] ) ) {
				continue;
			}
			$config = SmartRecur_Integration_Config::get( $type );
			foreach ( $keys as $key ) {
				if ( ! isset( $post[ $type ][ $key ] ) ) {
					continue;
				}
				$value = sanitize_text_field( (string) $post[ $type ][ $key ] );
				if ( '••••••••' === $value || '' === $value ) {
					continue;
				}
				$config[ $key ] = $value;
			}
			SmartRecur_Integration_Config::put( $type, $config );
		}
	}

	/**
	 * Render the settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You cannot manage settings.', 'smartrecur' ) );
		}
		if ( ! empty( $_POST['smartrecur_settings_save'] ) ) {
			check_admin_referer( 'smartrecur_settings' );
			SmartRecur_Settings::save_form( wp_unslash( $_POST ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'smartrecur' ) . '</p></div>';
		}
		$settings = (array) get_option( 'smartrecur_settings', array() );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Render the tools screen (migration + backup).
	 */
	public static function render_tools() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You cannot use the tools.', 'smartrecur' ) );
		}
		$result = null;
		if ( ! empty( $_POST['smartrecur_migration_run'] ) ) {
			check_admin_referer( 'smartrecur_migration' );
			$result = SmartRecur_Migrator::run_from_post( wp_unslash( $_POST ) );
		}
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/tools.php';
	}

	/* =====================================================================
	 * Shared delete handler.
	 * =================================================================== */

	/**
	 * Process a row delete (single or bulk) for a list screen.
	 *
	 * @param string   $page   Page slug.
	 * @param string   $cap    Capability required.
	 * @param callable $delete Delete callback receiving an ID.
	 */
	private static function handle_delete( $page, $cap, $delete ) {
		// Single-row delete via row-action link.
		if ( isset( $_GET['smartrecur_action'] ) && 'delete' === $_GET['smartrecur_action'] ) {
			check_admin_referer( 'smartrecur_delete' );
			if ( ! current_user_can( $cap ) ) {
				wp_die( esc_html__( 'You are not allowed to delete this.', 'smartrecur' ) );
			}
			$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
			if ( $id ) {
				call_user_func( $delete, $id );
			}
			self::redirect( $page, 'deleted' );
		}

		// Bulk delete from WP_List_Table. The nonce action is `bulk-{plural}`,
		// where {plural} is the page slug minus the `smartrecur-` prefix.
		if ( isset( $_REQUEST['action'] ) && 'delete' === $_REQUEST['action'] && ! empty( $_REQUEST['ids'] ) ) {
			$plural = str_replace( 'smartrecur-', '', $page );
			check_admin_referer( 'bulk-' . $plural );
			if ( ! current_user_can( $cap ) ) {
				wp_die( esc_html__( 'You are not allowed to delete these.', 'smartrecur' ) );
			}
			$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['ids'] ) );
			foreach ( $ids as $id ) {
				if ( $id ) {
					call_user_func( $delete, $id );
				}
			}
			self::redirect( $page, 'deleted' );
		}
	}
}
