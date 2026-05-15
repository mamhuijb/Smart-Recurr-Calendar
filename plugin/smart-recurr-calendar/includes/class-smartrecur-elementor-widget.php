<?php
/**
 * Elementor V3 widget. Reuses the shortcode renderer for output.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

final class SmartRecur_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'smartrecur_calendar';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'SmartRecur Calendar', 'smartrecur' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'smartrecur', 'general' );
	}

	/**
	 * Keywords for the Elementor widget search box.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'calendar', 'appointment', 'booking', 'smartrecur', 'recurring' );
	}

	/**
	 * Tell Elementor to enqueue our stylesheet when the widget is on the page.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'smartrecur-admin' );
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Calendar', 'smartrecur' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'view',
			array(
				'label'   => __( 'View', 'smartrecur' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'calendar',
				'options' => array(
					'calendar'  => __( 'Calendar', 'smartrecur' ),
					'booking'   => __( 'Booking form', 'smartrecur' ),
					'dashboard' => __( 'Dashboard', 'smartrecur' ),
					'admin'     => __( 'Admin', 'smartrecur' ),
				),
			)
		);

		$this->add_control(
			'initial_view',
			array(
				'label'   => __( 'Initial calendar view', 'smartrecur' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'month',
				'options' => array(
					'month' => __( 'Month', 'smartrecur' ),
					'week'  => __( 'Week', 'smartrecur' ),
					'day'   => __( 'Day', 'smartrecur' ),
				),
			)
		);

		$this->add_control(
			'client_id',
			array(
				'label' => __( 'Client ID (optional)', 'smartrecur' ),
				'type'  => \Elementor\Controls_Manager::TEXT,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the front end. Delegates to the shortcode handler so
	 * the markup, asset registration, and localized data stay in sync.
	 */
	protected function render() {
		$settings  = $this->get_settings_for_display();
		$shortcode = sprintf(
			'[smartrecur view="%s" client_id="%s"]',
			esc_attr( $settings['view'] ?? 'calendar' ),
			esc_attr( $settings['client_id'] ?? '' )
		);

		// In the editor preview, just show a placeholder — the React app isn't fully usable there.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<div class="smartrecur-wrap smartrecur-editor-preview" style="border:1px dashed #ccc;padding:24px;text-align:center;">';
			echo '<strong>SmartRecur Calendar</strong><br>';
			echo esc_html( sprintf( __( 'View: %s | Initial: %s', 'smartrecur' ), $settings['view'] ?? 'calendar', $settings['initial_view'] ?? 'month' ) );
			echo '</div>';
			return;
		}

		echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — shortcode handler escapes.
	}
}
