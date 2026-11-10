<?php
/**
 * [cc_bonus_finder] – compares every casino's deposit bonuses for the visitor's deposit.
 *
 * Data: all published profiles with the wagering calculator switched on, in one currency.
 * Built once and cached; the cache is cleared whenever a profile is saved, imported,
 * published, unpublished or deleted.
 *
 * Shortcode attributes (all optional):
 *   deposit="100"         starting deposit (snaps to the slider steps)
 *   type="welcome"        starting tab: welcome | reload | highroller (default: all)
 *   wagering="35"         starting max wagering: 10 | 20 | 25 | 30 | 35 | 40
 *   sort="value"          value | bonus | wager | min
 *   per_casino="yes"      best offer per casino only (yes/no)
 *   limit="10"            results shown before "Show more"
 *   currency="EUR"        which profiles to include
 *   title="…" intro="…"   optional heading and intro text
 */

defined( 'ABSPATH' ) || exit;

class CCT_Finder {

	const CACHE = 'cct_finder_data_v1';

	/** Slider steps: they grow with the amount so small and large deposits are both easy to set. */
	const STOPS = array( 10, 15, 20, 25, 30, 40, 50, 60, 75, 100, 125, 150, 200, 250, 300, 400, 500, 600, 750, 1000, 1250, 1500, 2000, 2500, 3000, 4000, 5000 );

	const CRYPTO = array( 'Bitcoin', 'Ethereum', 'Litecoin', 'Bitcoin Cash', 'Dogecoin', 'Cardano', 'Tether', 'USDT', 'XRP', 'TRX', 'BNB', 'USDC', 'Solana' );

	private static $instance = 0;

	public static function init() {
		add_shortcode( 'cc_bonus_finder', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		$flush = function ( $post_id ) {
			if ( CCT_Profiles::POST_TYPE === get_post_type( $post_id ) ) {
				self::flush();
			}
		};
		add_action( 'save_post', $flush );
		add_action( 'before_delete_post', $flush );
		add_action( 'transition_post_status', function ( $new, $old, $post ) {
			if ( $post instanceof WP_Post && CCT_Profiles::POST_TYPE === $post->post_type && $new !== $old ) {
				self::flush();
			}
		}, 10, 3 );
	}

	public static function flush() {
		foreach ( array( 'EUR', 'USD', 'CAD', 'GBP', 'AUD', 'NZD' ) as $cur ) {
			delete_transient( self::CACHE . '_' . $cur );
		}
	}

	public static function register_assets() {
		wp_register_script( 'cct-finder', CCT_URL . 'public/js/finder.js', array(), CCT_VERSION, true );
		$post = get_post();
		if ( $post && is_singular() && has_shortcode( $post->post_content, 'cc_bonus_finder' ) ) {
			wp_enqueue_style( 'cct-tools' );
		}
	}

	/* ----------------------------------------------------------------- data */

	/**
	 * @return array{c: array, o: array} casinos and offers, compact for the page.
	 *   casino: n name, u review URL, b bonuses-page URL, mb max bet, ex excluded methods
	 *   offer:  [casino index, name, type, match %, max bonus, min deposit, x, base, extras, extras min, days]
	 */
	public static function data( $currency = 'EUR' ) {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', $currency ) ) ?: 'EUR';
		$key      = self::CACHE . '_' . $currency;
		$cached   = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$casinos = array();
		$offers  = array();
		$posts   = get_posts( array(
			'post_type'      => CCT_Profiles::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( $posts as $p ) {
			$d = json_decode( $p->post_content, true );
			if ( ! is_array( $d ) || empty( $d['wagering']['enabled'] ) || strtoupper( $d['casino']['currency'] ?? 'EUR' ) !== $currency ) {
				continue;
			}
			$w      = $d['wagering'];
			$review = (int) ( $d['casino']['review_post_id'] ?? 0 );
			$url    = $review && get_post_status( $review ) === 'publish' ? get_permalink( $review ) : '';
			if ( ! $url ) {
				continue; // nothing to link to
			}
			$bonus_page = CCT_AutoInsert::page_for( $p->ID, 'wagering' );

			$ci        = count( $casinos );
			$casinos[] = array(
				'n'  => trim( $d['casino']['name'] ),
				'u'  => $url,
				'b'  => $bonus_page ? get_permalink( $bonus_page ) : $url,
				'mb' => $w['max_bet'] ?? null,
				'ex' => array_values( $w['excluded_methods'] ?? array() ),
			);
			$added = 0;
			foreach ( (array) ( $w['offers'] ?? array() ) as $o ) {
				if ( 'deposit' !== ( $o['kind'] ?? 'deposit' ) || empty( $o['match_pct'] ) || empty( $o['max_bonus'] ) || ! isset( $o['wagering_x'] ) ) {
					continue;
				}
				$name = (string) $o['name'];
				$type = preg_match( '/welcome|1st|first|2nd|3rd|4th|5th|6th|sign.?up|new player/i', $name ) ? 'welcome'
					: ( preg_match( '/high.?roller|vip|mvp/i', $name ) ? 'highroller' : 'reload' );
				$base = array( 'deposit_bonus' => 'db', 'bonus' => 'b', 'deposit' => 'd', 'winnings' => 'b' );
				$offers[] = array(
					$ci,
					$name,
					$type,
					0 + $o['match_pct'],
					0 + $o['max_bonus'],
					isset( $o['min_deposit'] ) ? 0 + $o['min_deposit'] : null,
					0 + $o['wagering_x'],
					$base[ $o['wagering_base'] ?? 'deposit_bonus' ] ?? 'db',
					(string) ( $o['extras'] ?? '' ),
					isset( $o['extras_min_deposit'] ) ? 0 + $o['extras_min_deposit'] : null,
					isset( $o['days'] ) ? (int) $o['days'] : ( isset( $w['days'] ) ? (int) $w['days'] : null ),
				);
				$added++;
			}
			if ( ! $added ) {
				array_pop( $casinos );
			}
		}

		$data = array( 'c' => $casinos, 'o' => $offers );
		set_transient( $key, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/** Same ranking as finder.js, used for the server-rendered first view. */
	public static function rank( $data, $dep, $type, $wx, $sort, $per_casino ) {
		$rows = array();
		foreach ( $data['o'] as $o ) {
			list( $ci, $name, $otype, $pct, $max, $min, $x, $base, $extras, $extras_min, $days ) = $o;
			if ( ( $type && $otype !== $type ) || ( null !== $min && $dep < $min ) || ( $wx && $x > $wx ) ) {
				continue;
			}
			$bonus  = min( $dep * $pct / 100, $max );
			$on     = array( 'db' => $dep + $bonus, 'b' => $bonus, 'd' => $dep );
			$wager  = $on[ $base ] * $x;
			$rows[] = array(
				'c' => $data['c'][ $ci ], 'name' => $name, 'pct' => $pct, 'max' => $max, 'min' => $min, 'x' => $x, 'base' => $base,
				'bonus' => $bonus, 'wager' => $wager, 'value' => $bonus - $wager * 0.04, 'days' => $days,
				'extras' => ( $extras && ( null === $extras_min || $dep >= $extras_min ) ) ? $extras : '',
			);
		}
		usort( $rows, function ( $a, $b ) use ( $sort ) {
			switch ( $sort ) {
				case 'bonus':
					return ( $b['bonus'] <=> $a['bonus'] ) ?: ( $a['wager'] <=> $b['wager'] );
				case 'wager':
					return ( $a['wager'] <=> $b['wager'] ) ?: ( $b['bonus'] <=> $a['bonus'] );
				case 'min':
					return ( (float) $a['min'] <=> (float) $b['min'] ) ?: ( $b['value'] <=> $a['value'] );
				default:
					return ( $b['value'] <=> $a['value'] ) ?: ( $b['bonus'] <=> $a['bonus'] );
			}
		} );
		if ( $per_casino ) {
			$seen = array();
			$rows = array_values( array_filter( $rows, function ( $r ) use ( &$seen ) {
				if ( isset( $seen[ $r['c']['n'] ] ) ) {
					return false;
				}
				$seen[ $r['c']['n'] ] = true;
				return true;
			} ) );
		}
		return $rows;
	}

	/* ------------------------------------------------------------ shortcode */

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array(
			'deposit'    => 100,
			'type'       => '',
			'wagering'   => '',
			'sort'       => 'value',
			'per_casino' => 'yes',
			'limit'      => 10,
			'currency'   => 'EUR',
			'title'      => '',
			'intro'      => '',
		), $atts, 'cc_bonus_finder' );

		$data = self::data( $a['currency'] );
		if ( ! $data['o'] ) {
			return current_user_can( 'edit_posts' )
				? '<p class="cct-notice" style="padding:10px 14px;border:1px dashed #e24f4b;border-radius:8px;color:#c2312d;font-size:14px">' . esc_html__( 'Bonus finder: no published casino has offers in this currency yet.', 'casinos-cc-tools' ) . '</p>'
				: '';
		}

		// snap the starting deposit to the nearest slider step
		$dep  = max( 1, (float) $a['deposit'] );
		$idx  = 0;
		foreach ( self::STOPS as $i => $v ) {
			if ( abs( $v - $dep ) < abs( self::STOPS[ $idx ] - $dep ) ) {
				$idx = $i;
			}
		}
		$dep        = self::STOPS[ $idx ];
		$type       = in_array( $a['type'], array( 'welcome', 'reload', 'highroller' ), true ) ? $a['type'] : '';
		$wx         = in_array( (int) $a['wagering'], array( 10, 20, 25, 30, 35, 40 ), true ) ? (int) $a['wagering'] : 0;
		$sort       = in_array( $a['sort'], array( 'value', 'bonus', 'wager', 'min' ), true ) ? $a['sort'] : 'value';
		$per_casino = ! in_array( strtolower( (string) $a['per_casino'] ), array( 'no', '0', 'false' ), true );
		$limit      = max( 1, min( 50, (int) $a['limit'] ) );
		$currency   = strtoupper( $a['currency'] ) ?: 'EUR';

		wp_enqueue_style( 'cct-tools' );
		wp_enqueue_script( 'cct-finder' );

		self::$instance++;
		$uid    = 'cctf' . self::$instance;
		$rows   = self::rank( $data, $dep, $type, $wx, $sort, $per_casino );
		$config = array(
			'stops'    => self::STOPS,
			'crypto'   => self::CRYPTO,
			'limit'    => $limit,
			'currency' => $currency,
			'i18n'     => self::strings(),
		);
		$json = CCT_Schema::encode( array( 'data' => $data, 'config' => $config ), false );

		ob_start();
		include CCT_DIR . 'public/views/finder.php';
		return ob_get_clean();
	}

	public static function strings() {
		return array(
			'summary'      => __( 'With a <b>{amount}</b> deposit you qualify for <b>{n}</b>.', 'casinos-cc-tools' ),
			'summaryAt'    => __( 'With a <b>{amount}</b> deposit you qualify for <b>{n}</b> at <b>{c} casinos</b>.', 'casinos-cc-tools' ),
			'offer'        => __( '1 offer', 'casinos-cc-tools' ),
			'offers'       => __( '{n} offers', 'casinos-cc-tools' ),
			'none'         => __( 'No bonus matches a {amount} deposit with these filters. Try a larger deposit or “Any wagering”.', 'casinos-cc-tools' ),
			'upTo'         => __( '{pct}% up to {max}', 'casinos-cc-tools' ),
			'bonus'        => __( 'bonus', 'casinos-cc-tools' ),
			'toWager'      => __( 'to wager', 'casinos-cc-tools' ),
			'db'           => __( '{x}x deposit + bonus', 'casinos-cc-tools' ),
			'b'            => __( '{x}x bonus', 'casinos-cc-tools' ),
			'd'            => __( '{x}x deposit', 'casinos-cc-tools' ),
			'noWagering'   => __( 'no wagering', 'casinos-cc-tools' ),
			'days'         => __( '{n} days', 'casinos-cc-tools' ),
			'noLimit'      => __( 'time limit not published', 'casinos-cc-tools' ),
			'maxBet'       => __( 'max bet {amount}', 'casinos-cc-tools' ),
			'value'        => __( 'expected value', 'casinos-cc-tools' ),
			'calculate'    => __( 'Calculate', 'casinos-cc-tools' ),
			'review'       => __( 'Review', 'casinos-cc-tools' ),
			'more'         => __( 'Show {n} more', 'casinos-cc-tools' ),
			'rank'         => __( 'Rank {n}', 'casinos-cc-tools' ),
		);
	}
}
