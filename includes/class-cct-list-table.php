<?php
/**
 * Casino Tools → Casinos list: pagination, search, filters and bulk actions,
 * built on WordPress' own WP_List_Table.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CCT_List_Table extends WP_List_Table {

	const PER_PAGE_OPTION = 'cct_casinos_per_page';

	/** @var array counts for the status views */
	private $counts = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );

	public function __construct() {
		parent::__construct( array(
			'singular' => 'casino',
			'plural'   => 'casinos',
			'ajax'     => false,
		) );
	}

	/* ------------------------------------------------------------- columns */

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'casino'   => __( 'Casino', 'casinos-cc-tools' ),
			'review'   => __( 'Review page', 'casinos-cc-tools' ),
			'tools'    => __( 'Tools on the site', 'casinos-cc-tools' ),
			'checked'  => __( 'Facts checked', 'casinos-cc-tools' ),
			'bf'       => __( 'BrandFinder', 'casinos-cc-tools' ),
			'modified' => __( 'Updated', 'casinos-cc-tools' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'casino'   => array( 'title', false ),
			'modified' => array( 'modified', true ),
			'checked'  => array( 'checked', true ),
		);
	}

	protected function get_primary_column_name() {
		return 'casino';
	}

	protected function column_cb( $item ) {
		return '<label class="screen-reader-text" for="cct-cb-' . (int) $item['id'] . '">' . esc_html( sprintf( __( 'Select %s', 'casinos-cc-tools' ), $item['title'] ) ) . '</label>'
			. '<input type="checkbox" id="cct-cb-' . (int) $item['id'] . '" name="profile[]" value="' . (int) $item['id'] . '">';
	}

	protected function column_casino( $item ) {
		$id   = (int) $item['id'];
		$edit = admin_url( 'admin.php?page=cct-edit&profile=' . $id );
		$out  = '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item['title'] ) . '</a>';
		if ( 'draft' === $item['status'] ) {
			$out .= ' — <span class="cct-pill cct-pill--draft">' . esc_html__( 'Draft', 'casinos-cc-tools' ) . '</span>';
		}
		$out .= '</strong>';

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'casinos-cc-tools' ) . '</a>',
			'export' => '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cct_export_profile&profile=' . $id ), 'cct_export_' . $id ) ) . '">' . esc_html__( 'Export JSON', 'casinos-cc-tools' ) . '</a>',
			'trash'  => '<a class="cct-confirm" data-confirm="' . esc_attr__( 'Delete this tool profile? The tools disappear from its pages.', 'casinos-cc-tools' ) . '" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cct_delete_profile&profile=' . $id ), 'cct_delete_' . $id ) ) . '">' . esc_html__( 'Delete', 'casinos-cc-tools' ) . '</a>',
		);
		return $out . $this->row_actions( $actions );
	}

	protected function column_review( $item ) {
		$review = (int) $item['review'];
		if ( $review && get_post( $review ) ) {
			return '<a href="' . esc_url( get_permalink( $review ) ) . '" target="_blank">' . esc_html( get_the_title( $review ) ) . '</a>';
		}
		return '<span class="cct-muted">—</span>';
	}

	protected function column_tools( $item ) {
		if ( 'publish' !== $item['status'] ) {
			$tools = array();
			foreach ( array( 'wagering' => __( 'Wagering', 'casinos-cc-tools' ), 'withdrawal' => __( 'Withdrawal', 'casinos-cc-tools' ) ) as $k => $label ) {
				$tools[] = '<span class="cct-pill ' . ( $item[ $k ] ? 'cct-pill--on' : 'cct-pill--off' ) . '">' . esc_html( $label ) . '</span>';
			}
			return implode( ' ', $tools ) . '<div class="cct-muted">' . esc_html__( 'Publish the profile to put tools on the site', 'casinos-cc-tools' ) . '</div>';
		}
		return CCT_AutoInsert::controls( $item['id'], 'wagering', $item['data'] ) . CCT_AutoInsert::controls( $item['id'], 'withdrawal', $item['data'] );
	}

	protected function column_checked( $item ) {
		return $item['checked'] ? esc_html( $item['checked'] ) : '<span class="cct-muted">—</span>';
	}

	protected function column_bf( $item ) {
		return $item['bf'] ? '#' . (int) $item['bf'] : '<span class="cct-muted">—</span>';
	}

	protected function column_modified( $item ) {
		/* translators: %s: time difference */
		return esc_html( sprintf( __( '%s ago', 'casinos-cc-tools' ), human_time_diff( $item['modified'] ) ) );
	}

	public function no_items() {
		if ( $this->has_filters() ) {
			esc_html_e( 'No casinos match these filters.', 'casinos-cc-tools' );
		} else {
			esc_html_e( 'No casinos yet. Add one by hand, or import a JSON file.', 'casinos-cc-tools' );
		}
	}

	/* --------------------------------------------------------- views/filters */

	protected function get_views() {
		$current = $this->req( 'status' );
		$base    = admin_url( 'admin.php?page=' . CCT_Admin::SLUG );
		$labels  = array(
			'all'     => __( 'All', 'casinos-cc-tools' ),
			'publish' => __( 'Live', 'casinos-cc-tools' ),
			'draft'   => __( 'Draft', 'casinos-cc-tools' ),
		);
		$views = array();
		foreach ( $labels as $key => $label ) {
			$url   = 'all' === $key ? $base : add_query_arg( 'status', $key, $base );
			$class = ( $current === $key || ( 'all' === $key && ! $current ) ) ? ' class="current" aria-current="page"' : '';
			$views[ $key ] = '<a href="' . esc_url( $url ) . '"' . $class . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $this->counts[ $key ] . ')</span></a>';
		}
		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$selects = array(
			'wagering'   => array( __( 'Wagering calculator', 'casinos-cc-tools' ), array( 'on' => __( 'On', 'casinos-cc-tools' ), 'off' => __( 'Off', 'casinos-cc-tools' ) ) ),
			'withdrawal' => array( __( 'Withdrawal checker', 'casinos-cc-tools' ), array( 'on' => __( 'On', 'casinos-cc-tools' ), 'off' => __( 'Off', 'casinos-cc-tools' ) ) ),
			'placement'  => array( __( 'On the site', 'casinos-cc-tools' ), array(
				'none'    => __( 'No tool on a page', 'casinos-cc-tools' ),
				'preview' => __( 'Any tool in preview', 'casinos-cc-tools' ),
				'live'    => __( 'Any tool live', 'casinos-cc-tools' ),
			) ),
		);
		echo '<div class="alignleft actions">';
		foreach ( $selects as $key => $def ) {
			$current = $this->req( $key );
			echo '<label class="screen-reader-text" for="cct-filter-' . esc_attr( $key ) . '">' . esc_html( $def[0] ) . '</label>';
			echo '<select name="' . esc_attr( $key ) . '" id="cct-filter-' . esc_attr( $key ) . '">';
			/* translators: %s: filter name */
			echo '<option value="">' . esc_html( sprintf( __( '%s: any', 'casinos-cc-tools' ), $def[0] ) ) . '</option>';
			foreach ( $def[1] as $v => $l ) {
				echo '<option value="' . esc_attr( $v ) . '"' . selected( $current, $v, false ) . '>' . esc_html( $def[0] . ': ' . $l ) . '</option>';
			}
			echo '</select>';
		}
		submit_button( __( 'Filter', 'casinos-cc-tools' ), '', 'filter_action', false, array( 'id' => 'cct-filter-submit' ) );
		if ( $this->has_filters() ) {
			echo ' <a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=' . CCT_Admin::SLUG ) ) . '">' . esc_html__( 'Clear filters', 'casinos-cc-tools' ) . '</a>';
		}
		echo '</div>';
	}

	private function has_filters() {
		foreach ( array( 's', 'status', 'wagering', 'withdrawal', 'placement' ) as $k ) {
			if ( '' !== $this->req( $k ) ) {
				return true;
			}
		}
		return false;
	}

	/* --------------------------------------------------------- bulk actions */

	protected function get_bulk_actions() {
		return array(
			'publish'       => __( 'Publish (set to Live)', 'casinos-cc-tools' ),
			'draft'         => __( 'Set to Draft', 'casinos-cc-tools' ),
			'place_preview' => __( 'Insert tools & preview', 'casinos-cc-tools' ),
			'place_live'    => __( 'Put tools live on pages', 'casinos-cc-tools' ),
			'place_off'     => __( 'Remove tools from pages', 'casinos-cc-tools' ),
			'delete'        => __( 'Delete', 'casinos-cc-tools' ),
		);
	}

	/**
	 * Runs on load-{page}, before any output, so it can redirect.
	 *
	 * @return string|null Flash message, or null when there was nothing to do.
	 */
	public static function process_bulk() {
		$action = '';
		foreach ( array( 'action', 'action2' ) as $k ) {
			$v = isset( $_REQUEST[ $k ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $v && '-1' !== $v ) {
				$action = $v;
				break;
			}
		}
		$ids = isset( $_REQUEST['profile'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['profile'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = array_filter( $ids );
		if ( ! $action || ! $ids ) {
			return null;
		}
		check_admin_referer( 'bulk-casinos' );

		$done = 0;
		foreach ( $ids as $id ) {
			if ( CCT_Profiles::POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			switch ( $action ) {
				case 'publish':
				case 'draft':
					$status = 'publish' === $action ? 'publish' : 'draft';
					if ( get_post_status( $id ) !== $status ) {
						wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
					}
					$done++;
					break;

				case 'place_preview':
				case 'place_live':
				case 'place_off':
					$state = substr( $action, 6 );
					if ( 'publish' !== get_post_status( $id ) && 'off' !== $state ) {
						break; // drafts can't be on the site
					}
					$data    = CCT_Profiles::get_data( $id );
					$changed = false;
					foreach ( array_keys( CCT_AutoInsert::PAGES ) as $tool ) {
						if ( 'off' !== $state && ( empty( $data[ $tool ]['enabled'] ) || ! CCT_AutoInsert::page_for( $id, $tool ) ) ) {
							continue; // tool switched off, or no /bonuses/ or /withdrawal/ page
						}
						if ( CCT_AutoInsert::get_state( $id, $tool ) !== $state ) {
							CCT_AutoInsert::set_state( $id, $tool, $state );
						}
						$changed = true;
					}
					if ( $changed ) {
						$done++;
					}
					break;

				case 'delete':
					if ( current_user_can( 'delete_post', $id ) && wp_delete_post( $id, true ) ) {
						$done++;
					}
					break;
			}
		}

		$messages = array(
			/* translators: %d: number of casinos */
			'publish'       => _n( '%d casino published.', '%d casinos published.', $done, 'casinos-cc-tools' ),
			'draft'         => _n( '%d casino set to draft.', '%d casinos set to draft.', $done, 'casinos-cc-tools' ),
			'place_preview' => _n( 'Tools inserted in preview for %d casino.', 'Tools inserted in preview for %d casinos.', $done, 'casinos-cc-tools' ),
			'place_live'    => _n( 'Tools are live for %d casino.', 'Tools are live for %d casinos.', $done, 'casinos-cc-tools' ),
			'place_off'     => _n( 'Tools removed from the pages of %d casino.', 'Tools removed from the pages of %d casinos.', $done, 'casinos-cc-tools' ),
			'delete'        => _n( '%d casino deleted.', '%d casinos deleted.', $done, 'casinos-cc-tools' ),
		);
		$msg = isset( $messages[ $action ] ) ? sprintf( $messages[ $action ], $done ) : '';
		$skipped = count( $ids ) - $done;
		if ( $skipped > 0 && in_array( $action, array( 'place_preview', 'place_live' ), true ) ) {
			/* translators: %d: number of casinos */
			$msg .= ' ' . sprintf( _n( '%d skipped (draft, tools off, or no Bonuses/Withdrawal page).', '%d skipped (drafts, tools off, or no Bonuses/Withdrawal page).', $skipped, 'casinos-cc-tools' ), $skipped );
		}
		return $msg;
	}

	/* ------------------------------------------------------------- data */

	private function req( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->get_primary_column_name() );

		$posts = get_posts( array(
			'post_type'              => CCT_Profiles::POST_TYPE,
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_term_cache' => false,
		) );

		$search     = strtolower( $this->req( 's' ) );
		$status     = $this->req( 'status' );
		$f_wager    = $this->req( 'wagering' );
		$f_withdraw = $this->req( 'withdrawal' );
		$f_place    = $this->req( 'placement' );

		$items = array();
		foreach ( $posts as $p ) {
			$this->counts['all']++;
			$this->counts[ 'publish' === $p->post_status ? 'publish' : 'draft' ]++;

			$d = json_decode( $p->post_content, true );
			$d = is_array( $d ) ? $d : CCT_Schema::blank();
			$item = array(
				'id'         => $p->ID,
				'title'      => $p->post_title,
				'slug'       => $p->post_name,
				'status'     => $p->post_status,
				// drafts have no GMT modified date: fall back to the local one
				'modified'   => (int) ( '0000-00-00 00:00:00' !== $p->post_modified_gmt ? get_post_modified_time( 'U', true, $p ) : get_post_modified_time( 'U', false, $p ) ),
				'review'     => (int) ( $d['casino']['review_post_id'] ?? 0 ),
				'checked'    => (string) ( $d['casino']['verified_at'] ?? '' ),
				'bf'         => (int) ( $d['casino']['brandfinder_analysis_id'] ?? 0 ),
				'wagering'   => ! empty( $d['wagering']['enabled'] ),
				'withdrawal' => ! empty( $d['withdrawal']['enabled'] ),
				'data'       => $d,
			);

			if ( $status && $status !== $item['status'] ) {
				continue;
			}
			if ( $search && false === strpos( strtolower( $item['title'] . ' ' . $item['slug'] ), $search ) ) {
				continue;
			}
			if ( $f_wager && ( 'on' === $f_wager ) !== $item['wagering'] ) {
				continue;
			}
			if ( $f_withdraw && ( 'on' === $f_withdraw ) !== $item['withdrawal'] ) {
				continue;
			}
			if ( $f_place ) {
				$states = array( CCT_AutoInsert::get_state( $p->ID, 'wagering' ), CCT_AutoInsert::get_state( $p->ID, 'withdrawal' ) );
				if ( 'none' === $f_place && array( 'off', 'off' ) !== $states ) {
					continue;
				}
				if ( in_array( $f_place, array( 'preview', 'live' ), true ) && ! in_array( $f_place, $states, true ) ) {
					continue;
				}
			}
			$items[] = $item;
		}

		// sort
		$orderby = $this->req( 'orderby' ) ?: 'title';
		$order   = 'desc' === strtolower( $this->req( 'order' ) ) ? -1 : 1;
		usort( $items, function ( $a, $b ) use ( $orderby, $order ) {
			switch ( $orderby ) {
				case 'modified':
					$c = $a['modified'] <=> $b['modified'];
					break;
				case 'checked':
					$c = strcmp( $a['checked'], $b['checked'] );
					break;
				default:
					$c = strcasecmp( $a['title'], $b['title'] );
			}
			return $c * $order;
		} );

		// paginate
		$per_page = $this->get_items_per_page( self::PER_PAGE_OPTION, 20 );
		$total    = count( $items );
		$page     = max( 1, min( $this->get_pagenum(), (int) ceil( $total / $per_page ) ?: 1 ) );
		$this->items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );
	}
}
