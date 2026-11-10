<?php
/**
 * Shortcodes:
 *   [cc_wagering_calculator]           casino from the current page or its parents
 *   [cc_withdrawal_checker]
 *   [cc_tool type="wagering|withdrawal"]
 * Optional on all: casino="golazzo-casino"  title="…"  intro="…"  boxed="yes" (grey panel)
 */

defined( 'ABSPATH' ) || exit;

class CCT_Shortcodes {

	const TAGS = array( 'cc_wagering_calculator', 'cc_withdrawal_checker', 'cc_tool' );

	private static $instance = 0;

	public static function init() {
		add_shortcode( 'cc_wagering_calculator', function ( $atts ) {
			return self::render( 'wagering', $atts );
		} );
		add_shortcode( 'cc_withdrawal_checker', function ( $atts ) {
			return self::render( 'withdrawal', $atts );
		} );
		add_shortcode( 'cc_tool', function ( $atts ) {
			$atts = shortcode_atts( array( 'type' => '' ), $atts, 'cc_tool' ) + (array) $atts;
			return self::render( sanitize_key( $atts['type'] ), $atts );
		} );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'cct-tools', CCT_URL . 'public/css/tools.css', array(), CCT_VERSION );
		wp_register_script( 'cct-tools', CCT_URL . 'public/js/tools.js', array(), CCT_VERSION, true );
		wp_localize_script( 'cct-tools', 'cctI18n', self::js_strings() );

		// Load the CSS in <head> when the current post uses a tool, so the widget doesn't flash unstyled.
		$post = get_post();
		if ( $post && is_singular() ) {
			foreach ( self::TAGS as $tag ) {
				if ( has_shortcode( $post->post_content, $tag ) ) {
					wp_enqueue_style( 'cct-tools' );
					break;
				}
			}
		}
	}

	public static function render( $type, $atts ) {
		$atts = shortcode_atts( array(
			'casino' => '',
			'title'  => '',
			'intro'  => '',
			'boxed'  => '',
			'label'  => '', // custom label text, or "no" to hide it
		), $atts );

		if ( ! in_array( $type, array( 'wagering', 'withdrawal' ), true ) ) {
			return self::admin_notice( __( 'Unknown tool type. Use type="wagering" or type="withdrawal".', 'casinos-cc-tools' ) );
		}

		// Some themes render content outside the loop, where get_the_ID() is empty.
		$page_id = get_the_ID() ?: get_queried_object_id();

		if ( $atts['casino'] ) {
			$profile_id = CCT_Profiles::find_by_slug( $atts['casino'] );
			if ( ! $profile_id ) {
				/* translators: %s: slug */
				return self::admin_notice( sprintf( __( 'No tool profile has the slug "%s". Check the casino="…" value against Casino Tools → Casinos.', 'casinos-cc-tools' ), $atts['casino'] ) );
			}
		} else {
			$profile_id = CCT_Profiles::find_for_post( $page_id );
			if ( ! $profile_id ) {
				$chain = CCT_Profiles::chain( $page_id );
				return self::admin_notice( sprintf(
					/* translators: 1: post IDs, 2: slugs */
					__( 'No tool profile is linked to this page. Checked post IDs %1$s and slugs %2$s. In the casino’s profile, set "Review page" to one of these IDs, or add casino="slug" to the shortcode.', 'casinos-cc-tools' ),
					$chain ? implode( ', ', $chain ) : '(none)',
					$chain ? implode( ', ', array_filter( array_map( function ( $id ) { return get_post_field( 'post_name', $id ); }, $chain ) ) ) : '(none)'
				) );
			}
		}

		if ( 'publish' !== get_post_status( $profile_id ) ) {
			/* translators: %s: casino name */
			return self::admin_notice( sprintf( __( 'The %s tool profile is a draft. Set its status to Live in Casino Tools → Casinos to show the tool.', 'casinos-cc-tools' ), get_the_title( $profile_id ) ) );
		}

		$data = CCT_Profiles::get_data( $profile_id );
		if ( ! $data || empty( $data[ $type ]['enabled'] ) ) {
			/* translators: %s: casino name */
			return self::admin_notice( sprintf( __( 'This tool is switched off in the %s tool profile.', 'casinos-cc-tools' ), get_the_title( $profile_id ) ) );
		}

		wp_enqueue_style( 'cct-tools' );
		wp_enqueue_script( 'cct-tools' );

		self::$instance++;
		$uid    = 'cct' . self::$instance;
		$class  = 'cct-widget' . ( in_array( strtolower( $atts['boxed'] ), array( '1', 'yes', 'true' ), true ) ? ' cct-widget--boxed' : '' );
		$casino = $data['casino'];
		$tool   = $data[ $type ];
		// Only what the widget needs goes into the page. Internal fields (sources,
		// research IDs, check dates) stay in the admin.
		$public = $tool;
		unset( $public['sources'] );
		$json = CCT_Schema::encode( array(
			'casino' => array( 'name' => $casino['name'], 'currency' => $casino['currency'] ),
			'tool'   => $public,
		), false );

		ob_start();
		include CCT_DIR . 'public/views/' . $type . '.php';
		return ob_get_clean();
	}

	/** Visible to editors only, so a broken shortcode never shows to players. */
	private static function admin_notice( $msg ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="cct-notice" style="padding:10px 14px;border:1px dashed #e24f4b;border-radius:8px;color:#c2312d;font-size:14px">'
			. esc_html__( 'Casino tool:', 'casinos-cc-tools' ) . ' ' . esc_html( $msg ) . '</p>';
	}

	private static function js_strings() {
		return array(
			'toWager'         => __( 'to wager before you can withdraw', 'casinos-cc-tools' ),
			'toWagerShort'    => __( 'to wager', 'casinos-cc-tools' ),
			'deposit'         => __( 'Deposit', 'casinos-cc-tools' ),
			'spinWinnings'    => __( 'Free spin winnings', 'casinos-cc-tools' ),
			'spinHelp'        => __( 'Enter what the free spins paid out.', 'casinos-cc-tools' ),
			'fullBonus'       => __( 'Deposit {amount} to get the full bonus.', 'casinos-cc-tools' ),
			'offerLine'       => __( '{pct}% up to {max}', 'casinos-cc-tools' ),
			'minDeposit'      => __( 'Minimum deposit {amount}.', 'casinos-cc-tools' ),
			'needAmount'      => __( 'Need {amount}+', 'casinos-cc-tools' ),
			'ruleXdb'         => __( '{x}x deposit + bonus', 'casinos-cc-tools' ),
			'ruleXb'          => __( '{x}x bonus', 'casinos-cc-tools' ),
			'ruleXd'          => __( '{x}x deposit', 'casinos-cc-tools' ),
			'ruleXw'          => __( '{x}x winnings', 'casinos-cc-tools' ),
			'noWagering'      => __( 'No wagering', 'casinos-cc-tools' ),
			'belowMin'        => __( 'This offer needs at least <b>{min}</b>. A deposit of {amount} won’t trigger it.', 'casinos-cc-tools' ),
			'extrasMin'       => __( 'Deposit <b>{min}</b> or more to get the {extras} as well.', 'casinos-cc-tools' ),
			'excluded'        => __( '<b>{method}</b> deposits don’t qualify for {casino} bonuses.', 'casinos-cc-tools' ),
			'winCap'          => __( 'Players in <b>{country}</b> can win at most {x}x the bonus: <b>{amount}</b> here.', 'casinos-cc-tools' ),
			'costHigh'        => __( 'At {rtp}% RTP, wagering is expected to cost you <b>{cost}</b>, which is more than the {bonus} bonus.', 'casinos-cc-tools' ),
			'costOk'          => __( 'At {rtp}% RTP, the {bonus} bonus covers the expected {cost} cost of wagering.', 'casinos-cc-tools' ),
			'activate'        => __( 'Activate the bonus within <b>{hours} hours</b> and before your first bet.', 'casinos-cc-tools' ),
			'maxBet'          => __( 'Keep each bet at <b>{amount}</b> or less until wagering is done.', 'casinos-cc-tools' ),
			'forfeit'         => __( 'Requesting a withdrawal or cancelling the bonus before you finish wagering removes the bonus and its winnings.', 'casinos-cc-tools' ),
			'methodsPay'      => __( '<b>{n} methods</b> can pay out {amount}.', 'casinos-cc-tools' ),
			'methodPay'       => __( '<b>1 method</b> can pay out {amount}.', 'casinos-cc-tools' ),
			'barMethods'      => __( 'methods can pay out {amount}', 'casinos-cc-tools' ),
			'barMethod'       => __( 'method can pay out {amount}', 'casinos-cc-tools' ),
			'dailyPayments'   => __( 'At your level it arrives in <b>{n} daily payments</b>.', 'casinos-cc-tools' ),
			'limitsHelp'      => __( 'Limits: {daily} a day, {weekly} a week, {monthly} a month.', 'casinos-cc-tools' ),
			'available'       => __( 'Available', 'casinos-cc-tools' ),
			'minShort'        => __( 'Min. {amount}', 'casinos-cc-tools' ),
			'minLabel'        => __( 'Min.', 'casinos-cc-tools' ),
			'notConfirmed'    => __( 'Not confirmed', 'casinos-cc-tools' ),
			'depositsOnly'    => __( 'Deposits only', 'casinos-cc-tools' ),
			'showMore'        => __( 'Show {n} methods that can’t pay out', 'casinos-cc-tools' ),
			'showOne'         => __( 'Show 1 method that can’t pay out', 'casinos-cc-tools' ),
			'hideMore'        => __( 'Hide methods that can’t pay out', 'casinos-cc-tools' ),
			'activeBonus'     => __( 'A withdrawal request now <b>cancels your active bonus and its winnings</b>. Finish the wagering first.', 'casinos-cc-tools' ),
			'cantReceive'     => __( '<b>{method}</b> can’t receive payouts at {casino}. Pick an available method in your own name.', 'casinos-cc-tools' ),
			'methodMin'       => __( '{method} pays out from <b>{amount}</b>.', 'casinos-cc-tools' ),
			'canReturn'       => __( 'You can cash out back to <b>{method}</b>.', 'casinos-cc-tools' ),
			'splitDays'       => __( 'Your limit means {amount} is paid over at least <b>{n} days</b>. Higher VIP levels raise the limit.', 'casinos-cc-tools' ),
			'types'           => array(
				'crypto'  => __( 'Crypto', 'casinos-cc-tools' ),
				'ewallet' => __( 'E-wallet', 'casinos-cc-tools' ),
				'bank'    => __( 'Bank', 'casinos-cc-tools' ),
				'card'    => __( 'Card', 'casinos-cc-tools' ),
				'voucher' => __( 'Voucher', 'casinos-cc-tools' ),
				'other'   => __( 'Other', 'casinos-cc-tools' ),
			),
		);
	}
}
