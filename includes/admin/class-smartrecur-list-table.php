<?php
/**
 * Generic WP_List_Table for SmartRecur admin entities.
 *
 * Configured per entity rather than subclassed, so clients / services /
 * technicians / appointments all reuse the same table renderer. The host
 * page supplies the columns, the row data, and a callback that turns a row
 * into the cell values + the edit URL.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class SmartRecur_List_Table extends WP_List_Table {

	/**
	 * Column definitions: slug => label.
	 *
	 * @var array
	 */
	private $columns_def;

	/**
	 * All data rows (already fetched).
	 *
	 * @var array
	 */
	private $rows;

	/**
	 * Row → cells callback. Receives a raw row, returns an associative array
	 * of column slug => already-escaped HTML cell content.
	 *
	 * @var callable
	 */
	private $render_row;

	/**
	 * Admin page slug used for delete links.
	 *
	 * @var string
	 */
	private $page_slug;

	/**
	 * Entity name used for nonce + bulk-action plurals.
	 *
	 * @var string
	 */
	private $entity;

	/**
	 * Number of rows per page.
	 *
	 * @var int
	 */
	private $per_page = 25;

	/**
	 * Constructor.
	 *
	 * @param array $args {
	 *     @type string   $entity      Entity slug (e.g. "client").
	 *     @type string   $page_slug   Admin page slug.
	 *     @type array    $columns     Column slug => label.
	 *     @type array    $rows        Data rows.
	 *     @type callable $render_row  Row renderer.
	 * }
	 */
	public function __construct( array $args ) {
		$this->entity      = $args['entity'];
		$this->page_slug   = $args['page_slug'];
		$this->columns_def = $args['columns'];
		$this->rows        = $args['rows'];
		$this->render_row  = $args['render_row'];

		parent::__construct(
			array(
				'singular' => $this->entity,
				'plural'   => $this->entity . 's',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column headers.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array_merge(
			array( 'cb' => '<input type="checkbox" />' ),
			$this->columns_def
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'smartrecur' ) );
	}

	/**
	 * Default cell renderer — delegates to the configured callback.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$cells = call_user_func( $this->render_row, $item );
		return isset( $cells[ $column_name ] ) ? $cells[ $column_name ] : '';
	}

	/**
	 * Prepare items: search filter + pagination.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$rows   = $this->rows;
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$rows   = array_filter(
				$rows,
				static function ( $row ) use ( $needle ) {
					foreach ( $row as $value ) {
						if ( is_scalar( $value ) && false !== strpos( strtolower( (string) $value ), $needle ) ) {
							return true;
						}
					}
					return false;
				}
			);
		}

		$total        = count( $rows );
		$current_page = $this->get_pagenum();
		$rows         = array_slice( $rows, ( $current_page - 1 ) * $this->per_page, $this->per_page );

		$this->items = $rows;
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $this->per_page,
				'total_pages' => (int) ceil( $total / $this->per_page ),
			)
		);
	}

	/**
	 * Empty-state message.
	 */
	public function no_items() {
		esc_html_e( 'Nothing here yet.', 'smartrecur' );
	}
}
