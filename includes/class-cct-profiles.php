<?php
/**
 * Tool profiles: one private post (cct_profile) per casino.
 *
 * post_title   = casino name
 * post_name    = casino slug (e.g. golazzo-casino)
 * post_content = profile JSON (source of truth, kept in revisions)
 * meta _cct_review_post_id = linked review post, for fast lookup from subpages
 */

defined( 'ABSPATH' ) || exit;

class CCT_Profiles {

	const POST_TYPE = 'cct_profile';
	const META_REVIEW = '_cct_review_post_id';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );

		// Send "edit" links and the default editor to the plugin's own edit screen.
		add_filter( 'get_edit_post_link', function ( $link, $post_id ) {
			return self::POST_TYPE === get_post_type( $post_id ) ? admin_url( 'admin.php?page=cct-edit&profile=' . (int) $post_id ) : $link;
		}, 10, 2 );
		// Restoring a revision changes the JSON: keep the review link meta in sync.
		add_action( 'wp_restore_post_revision', function ( $post_id ) {
			if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
				return;
			}
			$data = self::get_data( $post_id );
			update_post_meta( $post_id, self::META_REVIEW, (int) ( $data['casino']['review_post_id'] ?? 0 ) );
		} );

		add_action( 'load-post.php', function () {
			$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $id && self::POST_TYPE === get_post_type( $id ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=cct-edit&profile=' . $id ) );
				exit;
			}
		} );
	}

	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels'          => array(
				'name'          => __( 'Tool profiles', 'casinos-cc-tools' ),
				'singular_name' => __( 'Tool profile', 'casinos-cc-tools' ),
			),
			'public'          => false,
			'show_ui'         => true,  // needed for the revisions screen
			'show_in_menu'    => false, // editing happens in Casino Tools → Casinos
			'show_in_rest'    => false,
			'supports'        => array( 'title', 'revisions' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => false,
			'query_var'       => false,
		) );
	}

	/** @return array|null Profile data, or null. */
	public static function get_data( $profile_id ) {
		$post = get_post( $profile_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$data = json_decode( $post->post_content, true );
		return is_array( $data ) ? $data : null;
	}

	public static function find_by_slug( $slug ) {
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'name'           => sanitize_title( $slug ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return $posts ? (int) $posts[0] : 0;
	}

	public static function find_by_review( $review_post_id ) {
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::META_REVIEW,
			'meta_value'     => (int) $review_post_id,
		) );
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Find the profile for a page: the page itself, then its parents.
	 * /golazzo-casino/bonuses/ (child) resolves to /golazzo-casino/ (review).
	 */
	public static function find_for_post( $post_id ) {
		$ids = array_merge( array( (int) $post_id ), array_map( 'intval', get_post_ancestors( $post_id ) ) );
		foreach ( $ids as $id ) {
			$found = self::find_by_review( $id );
			if ( $found ) {
				return $found;
			}
		}
		return 0;
	}

	/**
	 * Create or update a profile from validated data.
	 *
	 * @param array $data       Output of CCT_Schema::validate()['data'].
	 * @param int   $profile_id 0 to create (or match by slug).
	 * @param string $status    publish|draft
	 * @return int|WP_Error
	 */
	public static function save( array $data, $profile_id = 0, $status = 'publish' ) {
		if ( ! $profile_id ) {
			$profile_id = self::find_by_slug( $data['casino']['slug'] );
		}

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $data['casino']['name'],
			'post_name'    => $data['casino']['slug'],
			'post_status'  => in_array( $status, array( 'publish', 'draft' ), true ) ? $status : 'publish',
			'post_content' => wp_slash( CCT_Schema::encode( $data ) ),
		);
		if ( $profile_id ) {
			$postarr['ID'] = $profile_id;
		}

		// JSON is not HTML: keep kses from touching it.
		$kses = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		$id = $profile_id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
		if ( $kses ) {
			kses_init_filters();
		}

		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META_REVIEW, (int) $data['casino']['review_post_id'] );
		return (int) $id;
	}
}
