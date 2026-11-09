<?php
/**
 * Automatic placement of the tools on a casino's subpages.
 *
 * Each profile has a placement state per tool, stored in post meta (not in the JSON, so
 * imports never change it):
 *   off     – not inserted (default)
 *   preview – inserted only for logged-in editors, with a "Preview" banner
 *   live    – inserted for everyone
 *
 * Pages: the child posts of the profile's review post with these slugs:
 *   bonuses    → wagering calculator
 *   withdrawal → withdrawal checker
 *
 * Position: right after the first heading that follows the page title (<h1>). On casinos.cc
 * that is the first <h2> of the first ACF text module. The page content itself is never
 * edited: the tool is added to the finished HTML, so it works with ACF flexible content.
 * If the page already contains a tool (e.g. a shortcode), nothing is inserted.
 */

defined( 'ABSPATH' ) || exit;

class CCT_AutoInsert {

	const META   = '_cct_placement';
	const STATES = array( 'off', 'preview', 'live' );
	const PAGES  = array(
		'wagering'   => 'bonuses',
		'withdrawal' => 'withdrawal',
	);

	/** @var array|null [profile_id, tool, state, slug] for the current request */
	private static $current = null;

	/** @var string Widget HTML, rendered before the page is buffered. */
	private static $widget = '';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'admin_post_cct_placement', array( __CLASS__, 'handle_placement' ) );
		add_action( 'admin_post_cct_placement_bulk', array( __CLASS__, 'handle_bulk' ) );
	}

	/** All profile/tool pairs currently in preview. */
	public static function previews() {
		$out = array();
		foreach ( get_posts( array( 'post_type' => CCT_Profiles::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			foreach ( array_keys( self::PAGES ) as $tool ) {
				if ( 'preview' === self::get_state( $id, $tool ) ) {
					$out[] = array( $id, $tool );
				}
			}
		}
		return $out;
	}

	public static function handle_bulk() {
		check_admin_referer( 'cct_placement_bulk' );
		if ( ! current_user_can( CCT_Admin::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		$n = 0;
		foreach ( self::previews() as $p ) {
			if ( current_user_can( 'edit_post', $p[0] ) ) {
				self::set_state( $p[0], $p[1], 'live' );
				$n++;
			}
		}
		/* translators: %d: number of tools */
		CCT_Admin::flash( sprintf( _n( '%d tool is now live.', '%d tools are now live.', $n, 'casinos-cc-tools' ), $n ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . CCT_Admin::SLUG ) );
		exit;
	}

	/* ---------------------------------------------------------------- state */

	public static function get_state( $profile_id, $tool ) {
		$all = get_post_meta( $profile_id, self::META, true );
		$s   = is_array( $all ) && isset( $all[ $tool ] ) ? $all[ $tool ] : 'off';
		return in_array( $s, self::STATES, true ) ? $s : 'off';
	}

	public static function set_state( $profile_id, $tool, $state ) {
		$all = get_post_meta( $profile_id, self::META, true );
		$all = is_array( $all ) ? $all : array();
		$all[ $tool ] = $state;
		update_post_meta( $profile_id, self::META, $all );

		$page = self::page_for( $profile_id, $tool );
		if ( $page ) {
			self::purge_cache( $page );
		}
	}

	/** The subpage a tool belongs on, or 0. */
	public static function page_for( $profile_id, $tool ) {
		$data   = CCT_Profiles::get_data( $profile_id );
		$review = (int) ( $data['casino']['review_post_id'] ?? 0 );
		if ( ! $review || empty( self::PAGES[ $tool ] ) ) {
			return 0;
		}
		$kids = get_posts( array(
			'post_type'      => 'any',
			'post_parent'    => $review,
			'name'           => self::PAGES[ $tool ],
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return $kids ? (int) $kids[0] : 0;
	}

	/** Clear page caches so a change shows straight away. */
	private static function purge_cache( $page_id ) {
		clean_post_cache( $page_id );
		do_action( 'cct_purge_page_cache', $page_id );
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $page_id );
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $page_id );
		}
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $page_id );
		}
		do_action( 'litespeed_purge_post', $page_id );
	}

	/* ------------------------------------------------------------- frontend */

	public static function maybe_start() {
		if ( is_admin() || ! is_singular() || is_feed() || wp_doing_ajax() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! $post->post_parent ) {
			return;
		}
		$tool = array_search( $post->post_name, self::PAGES, true );
		if ( ! $tool ) {
			return;
		}
		$profile_id = CCT_Profiles::find_by_review( $post->post_parent );
		if ( ! $profile_id || 'publish' !== get_post_status( $profile_id ) ) {
			return;
		}
		$state = self::get_state( $profile_id, $tool );
		if ( 'off' === $state || ( 'preview' === $state && ! current_user_can( 'edit_posts' ) ) ) {
			return;
		}
		$data = CCT_Profiles::get_data( $profile_id );
		if ( empty( $data[ $tool ]['enabled'] ) ) {
			return;
		}

		self::$current = array( $profile_id, $tool, $state, $data['casino']['slug'] );
		if ( 'preview' === $state ) {
			nocache_headers(); // never let a page cache store the preview
		}
		ob_start( array( __CLASS__, 'insert' ) );
	}

	/**
	 * Render the widget here, before the page is printed: an output-buffer callback
	 * cannot start its own buffer, which the widget template needs.
	 */
	public static function enqueue() {
		if ( ! self::$current ) {
			return;
		}
		list( $profile_id, $tool, $state, $slug ) = self::$current;
		$widget = CCT_Shortcodes::render( $tool, array( 'casino' => $slug ) );
		if ( '' === trim( $widget ) || false !== strpos( $widget, 'cct-notice' ) ) {
			self::$current = null;
			return;
		}
		if ( 'preview' === $state ) {
			$widget = '<div class="cct-preview">'
				. '<div class="cct-preview__bar">' . esc_html__( 'Preview – only logged-in editors see this tool.', 'casinos-cc-tools' )
				. ' <a href="' . esc_url( admin_url( 'admin.php?page=cct-edit&profile=' . $profile_id ) ) . '">' . esc_html__( 'Go live in Casino Tools', 'casinos-cc-tools' ) . '</a></div>'
				. $widget . '</div>';
		}
		self::$widget = "\n<!-- casinos-cc-tools: {$tool} -->\n" . $widget . "\n";
		wp_enqueue_style( 'cct-tools' );
		wp_enqueue_script( 'cct-tools' );
	}

	/** Output-buffer callback: put the tool after the first heading that follows the <h1>. */
	public static function insert( $html ) {
		if ( ! self::$current || '' === self::$widget ) {
			return $html;
		}
		// The page already has a tool (shortcode): leave it alone.
		if ( false !== strpos( $html, 'class="cct-widget' ) ) {
			return $html;
		}
		$pos = self::position( $html );
		if ( false === $pos ) {
			return $html;
		}
		return substr( $html, 0, $pos ) . self::$widget . substr( $html, $pos );
	}

	/**
	 * Byte offset just after the first heading that follows the page's <h1>.
	 * No <h1>: after the first heading in <body>. No heading at all: false.
	 */
	public static function position( $html ) {
		$body = stripos( $html, '<body' );
		$from = false === $body ? 0 : $body;

		if ( preg_match( '/<\/h1\s*>/i', $html, $m, PREG_OFFSET_CAPTURE, $from ) ) {
			$from = $m[0][1] + strlen( $m[0][0] );
		}
		if ( ! preg_match( '/<h([1-6])\b[^>]*>.*?<\/h\1\s*>/is', $html, $m, PREG_OFFSET_CAPTURE, $from ) ) {
			return false;
		}
		return $m[0][1] + strlen( $m[0][0] );
	}

	/* ---------------------------------------------------------------- admin */

	public static function url( $profile_id, $tool, $state ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=cct_placement&profile=' . (int) $profile_id . '&tool=' . $tool . '&state=' . $state ),
			'cct_placement_' . $profile_id
		);
	}

	public static function handle_placement() {
		$id    = absint( $_GET['profile'] ?? 0 );
		$tool  = sanitize_key( $_GET['tool'] ?? '' );
		$state = sanitize_key( $_GET['state'] ?? '' );
		check_admin_referer( 'cct_placement_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) || ! isset( self::PAGES[ $tool ] ) || ! in_array( $state, self::STATES, true ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		self::set_state( $id, $tool, $state );

		// "Preview" opens the page straight away.
		$page = self::page_for( $id, $tool );
		if ( 'preview' === $state && $page ) {
			wp_safe_redirect( get_permalink( $page ) );
			exit;
		}
		$labels = array(
			'live' => __( 'Tool is live on the page.', 'casinos-cc-tools' ),
			'off'  => __( 'Tool removed from the page.', 'casinos-cc-tools' ),
		);
		CCT_Admin::flash( $labels[ $state ] ?? __( 'Saved.', 'casinos-cc-tools' ) );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=' . CCT_Admin::SLUG ) );
		exit;
	}

	/** Compact controls for one tool, used in the list and on the edit screen. */
	public static function controls( $profile_id, $tool, $data ) {
		$names = array(
			'wagering'   => __( 'Wagering calculator', 'casinos-cc-tools' ),
			'withdrawal' => __( 'Withdrawal checker', 'casinos-cc-tools' ),
		);
		$out = '<div class="cct-place"><span class="cct-place__name">' . esc_html( $names[ $tool ] ) . '</span> ';

		if ( empty( $data[ $tool ]['enabled'] ) ) {
			return $out . '<span class="cct-pill cct-pill--off">' . esc_html__( 'Tool off', 'casinos-cc-tools' ) . '</span></div>';
		}
		$page = self::page_for( $profile_id, $tool );
		if ( ! $page ) {
			/* translators: %s: page slug */
			return $out . '<span class="cct-pill cct-pill--err">' . esc_html( sprintf( __( 'No /%s/ page', 'casinos-cc-tools' ), self::PAGES[ $tool ] ) ) . '</span></div>';
		}

		$state = self::get_state( $profile_id, $tool );
		$pill  = array(
			'off'     => '<span class="cct-pill cct-pill--off">' . esc_html__( 'Not on page', 'casinos-cc-tools' ) . '</span>',
			'preview' => '<span class="cct-pill cct-pill--draft">' . esc_html__( 'Preview', 'casinos-cc-tools' ) . '</span>',
			'live'    => '<span class="cct-pill cct-pill--on">' . esc_html__( 'Live', 'casinos-cc-tools' ) . '</span>',
		);
		$links = array();
		if ( 'off' === $state ) {
			$links[] = '<a href="' . esc_url( self::url( $profile_id, $tool, 'preview' ) ) . '" target="_blank">' . esc_html__( 'Insert & preview', 'casinos-cc-tools' ) . '</a>';
		}
		if ( 'preview' === $state ) {
			$links[] = '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">' . esc_html__( 'View preview', 'casinos-cc-tools' ) . '</a>';
			$links[] = '<a href="' . esc_url( self::url( $profile_id, $tool, 'live' ) ) . '"><strong>' . esc_html__( 'Go live', 'casinos-cc-tools' ) . '</strong></a>';
		}
		if ( 'live' === $state ) {
			$links[] = '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">' . esc_html__( 'View', 'casinos-cc-tools' ) . '</a>';
		}
		if ( 'off' !== $state ) {
			$links[] = '<a href="' . esc_url( self::url( $profile_id, $tool, 'off' ) ) . '" class="cct-place__remove">' . esc_html__( 'Remove', 'casinos-cc-tools' ) . '</a>';
		}
		return $out . $pill[ $state ] . ' <span class="cct-place__links">' . implode( ' · ', $links ) . '</span></div>';
	}
}
