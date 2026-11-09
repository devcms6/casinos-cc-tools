<?php
/**
 * Tool profile data: normalisation and validation.
 *
 * The JSON format is documented in docs/schema.md and docs/schema.json.
 * validate() never throws. It returns the cleaned data plus lists of errors (block saving)
 * and warnings (shown to the editor, saving allowed).
 */

defined( 'ABSPATH' ) || exit;

class CCT_Schema {

	const WAGERING_BASES = array( 'deposit_bonus', 'bonus', 'deposit', 'winnings' );
	const OFFER_KINDS    = array( 'deposit', 'spins' );
	const METHOD_TYPES   = array( 'crypto', 'ewallet', 'bank', 'card', 'voucher', 'other' );
	const WITHDRAW_STATE = array( 'yes', 'no', 'unknown' );

	/** Empty profile used for "Add new". */
	public static function blank() {
		return array(
			'schema'     => CCT_SCHEMA_VERSION,
			'casino'     => array(
				'slug'                    => '',
				'name'                    => '',
				'review_post_id'          => 0,
				'currency'                => 'EUR',
				'brandfinder_analysis_id' => null,
				'verified_at'             => '',
			),
			'wagering'   => array(
				'enabled'           => false,
				'max_bet'           => null,
				'days'              => null,
				'activate_hours'    => null,
				'excluded_methods'  => array(),
				'win_cap'           => array( 'multiplier' => null, 'countries' => array() ),
				'contribution_note' => '',
				'offers'            => array(),
				'sources'           => '',
			),
			'withdrawal' => array(
				'enabled'      => false,
				'methods'      => array(),
				'default_time' => array( 'bank' => '', 'other' => '' ),
				'limits'       => array(),
				'note'         => '',
				'sources'      => '',
			),
		);
	}

	/**
	 * @param mixed $input Decoded JSON (array).
	 * @return array{data: array, errors: string[], warnings: string[]}
	 */
	public static function validate( $input ) {
		$errors   = array();
		$warnings = array();

		if ( ! is_array( $input ) ) {
			return array( 'data' => self::blank(), 'errors' => array( __( 'The data is not a JSON object.', 'casinos-cc-tools' ) ), 'warnings' => array() );
		}

		$schema = isset( $input['schema'] ) ? (int) $input['schema'] : CCT_SCHEMA_VERSION;
		if ( $schema > CCT_SCHEMA_VERSION ) {
			$errors[] = sprintf( __( 'Schema version %1$d is newer than this plugin supports (%2$d). Update the plugin first.', 'casinos-cc-tools' ), $schema, CCT_SCHEMA_VERSION );
		}

		$d = self::blank();

		/* casino */
		$c                                     = isset( $input['casino'] ) && is_array( $input['casino'] ) ? $input['casino'] : array();
		$d['casino']['name']                   = self::str( $c, 'name' );
		$d['casino']['slug']                   = sanitize_title( self::str( $c, 'slug' ) ?: $d['casino']['name'] );
		$d['casino']['review_post_id']         = absint( $c['review_post_id'] ?? 0 );
		$d['casino']['currency']               = strtoupper( preg_replace( '/[^A-Za-z]/', '', self::str( $c, 'currency' ) ?: 'EUR' ) );
		$d['casino']['brandfinder_analysis_id'] = isset( $c['brandfinder_analysis_id'] ) && '' !== $c['brandfinder_analysis_id'] ? absint( $c['brandfinder_analysis_id'] ) : null;
		$d['casino']['verified_at']            = self::date( self::str( $c, 'verified_at' ) );

		if ( '' === $d['casino']['name'] ) {
			$errors[] = __( 'casino.name is required.', 'casinos-cc-tools' );
		}
		if ( '' === $d['casino']['slug'] ) {
			$errors[] = __( 'casino.slug is required.', 'casinos-cc-tools' );
		}
		if ( 3 !== strlen( $d['casino']['currency'] ) ) {
			$errors[] = __( 'casino.currency must be a 3-letter code such as EUR.', 'casinos-cc-tools' );
		}
		if ( ! $d['casino']['review_post_id'] ) {
			$warnings[] = __( 'No review post linked. Shortcodes will only work with casino="…".', 'casinos-cc-tools' );
		}

		/* wagering */
		$w = isset( $input['wagering'] ) && is_array( $input['wagering'] ) ? $input['wagering'] : array();
		$d['wagering']['enabled']           = ! empty( $w['enabled'] );
		$d['wagering']['max_bet']           = self::num( $w, 'max_bet' );
		$d['wagering']['days']              = self::int( $w, 'days' );
		$d['wagering']['activate_hours']    = self::num( $w, 'activate_hours' );
		$d['wagering']['excluded_methods']  = self::list_of_strings( $w['excluded_methods'] ?? array() );
		$cap                                = isset( $w['win_cap'] ) && is_array( $w['win_cap'] ) ? $w['win_cap'] : array();
		$d['wagering']['win_cap']           = array(
			'multiplier' => self::num( $cap, 'multiplier' ),
			'countries'  => self::list_of_strings( $cap['countries'] ?? array() ),
		);
		$d['wagering']['contribution_note'] = self::str( $w, 'contribution_note' );
		$d['wagering']['sources']           = self::str( $w, 'sources' );

		$ids = array();
		foreach ( ( isset( $w['offers'] ) && is_array( $w['offers'] ) ? $w['offers'] : array() ) as $i => $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$n     = $i + 1;
			$offer = array(
				'id'                 => sanitize_key( self::str( $o, 'id' ) ?: 'offer-' . $n ),
				'name'               => self::str( $o, 'name' ),
				'kind'               => in_array( $o['kind'] ?? 'deposit', self::OFFER_KINDS, true ) ? $o['kind'] ?? 'deposit' : 'deposit',
				'match_pct'          => self::num( $o, 'match_pct' ),
				'max_bonus'          => self::num( $o, 'max_bonus' ),
				'min_deposit'        => self::num( $o, 'min_deposit' ),
				'wagering_x'         => self::num( $o, 'wagering_x' ),
				'wagering_base'      => in_array( $o['wagering_base'] ?? '', self::WAGERING_BASES, true ) ? $o['wagering_base'] : '',
				'extras'             => self::str( $o, 'extras' ),
				'extras_min_deposit' => self::num( $o, 'extras_min_deposit' ),
				'days'               => self::int( $o, 'days' ),
				'description'        => self::str( $o, 'description' ),
			);
			if ( '' === $offer['wagering_base'] ) {
				$offer['wagering_base'] = 'spins' === $offer['kind'] ? 'winnings' : 'deposit_bonus';
			}
			if ( '' === $offer['name'] ) {
				$errors[] = sprintf( __( 'Offer %d has no name.', 'casinos-cc-tools' ), $n );
			}
			if ( null === $offer['wagering_x'] ) {
				$errors[] = sprintf( __( 'Offer "%s" has no wagering_x. Use 0 for no wagering.', 'casinos-cc-tools' ), $offer['name'] ?: $n );
			}
			if ( 'deposit' === $offer['kind'] && ( null === $offer['match_pct'] || null === $offer['max_bonus'] ) ) {
				$errors[] = sprintf( __( 'Deposit offer "%s" needs match_pct and max_bonus.', 'casinos-cc-tools' ), $offer['name'] ?: $n );
			}
			if ( 'deposit' === $offer['kind'] && null === $offer['min_deposit'] ) {
				$warnings[] = sprintf( __( 'Offer "%s" has no min_deposit. The minimum-deposit check is skipped.', 'casinos-cc-tools' ), $offer['name'] ?: $n );
			}
			if ( isset( $ids[ $offer['id'] ] ) ) {
				$offer['id'] .= '-' . $n;
			}
			$ids[ $offer['id'] ]         = true;
			$d['wagering']['offers'][] = $offer;
		}

		if ( $d['wagering']['enabled'] ) {
			if ( ! $d['wagering']['offers'] ) {
				$errors[] = __( 'Wagering calculator is enabled but has no offers.', 'casinos-cc-tools' );
			}
			if ( null === $d['wagering']['max_bet'] ) {
				$warnings[] = __( 'No max_bet. The "spins needed" row is hidden.', 'casinos-cc-tools' );
			}
			if ( null === $d['wagering']['days'] ) {
				$warnings[] = __( 'No wagering days. The "per day" row is hidden unless offers set their own days.', 'casinos-cc-tools' );
			}
		}

		/* withdrawal */
		$wd = isset( $input['withdrawal'] ) && is_array( $input['withdrawal'] ) ? $input['withdrawal'] : array();
		$d['withdrawal']['enabled'] = ! empty( $wd['enabled'] );
		$d['withdrawal']['sources'] = self::str( $wd, 'sources' );
		$d['withdrawal']['note']    = self::str( $wd, 'note' );
		$dt                         = isset( $wd['default_time'] ) && is_array( $wd['default_time'] ) ? $wd['default_time'] : array();
		$d['withdrawal']['default_time'] = array( 'bank' => self::str( $dt, 'bank' ), 'other' => self::str( $dt, 'other' ) );

		foreach ( ( isset( $wd['methods'] ) && is_array( $wd['methods'] ) ? $wd['methods'] : array() ) as $i => $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$method = array(
				'name'           => self::str( $m, 'name' ),
				'type'           => in_array( $m['type'] ?? '', self::METHOD_TYPES, true ) ? $m['type'] : 'other',
				'withdrawals'    => in_array( $m['withdrawals'] ?? '', self::WITHDRAW_STATE, true ) ? $m['withdrawals'] : 'unknown',
				'min_withdrawal' => self::num( $m, 'min_withdrawal' ),
				'time'           => self::str( $m, 'time' ),
				'fee'            => self::str( $m, 'fee' ),
			);
			if ( '' === $method['name'] ) {
				$errors[] = sprintf( __( 'Payment method %d has no name.', 'casinos-cc-tools' ), $i + 1 );
				continue;
			}
			if ( 'yes' === $method['withdrawals'] && null === $method['min_withdrawal'] ) {
				$warnings[] = sprintf( __( '%s supports withdrawals but has no min_withdrawal. It is treated as €0.', 'casinos-cc-tools' ), $method['name'] );
			}
			$d['withdrawal']['methods'][] = $method;
		}

		foreach ( ( isset( $wd['limits'] ) && is_array( $wd['limits'] ) ? $wd['limits'] : array() ) as $i => $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$limit = array(
				'label'   => self::str( $l, 'label' ) ?: sprintf( __( 'Level %d', 'casinos-cc-tools' ), $i + 1 ),
				'daily'   => self::num( $l, 'daily' ),
				'weekly'  => self::num( $l, 'weekly' ),
				'monthly' => self::num( $l, 'monthly' ),
			);
			if ( null === $limit['daily'] && null === $limit['weekly'] && null === $limit['monthly'] ) {
				$warnings[] = sprintf( __( 'Limit "%s" has no amounts and was skipped.', 'casinos-cc-tools' ), $limit['label'] );
				continue;
			}
			$d['withdrawal']['limits'][] = $limit;
		}

		if ( $d['withdrawal']['enabled'] ) {
			$payable = array_filter( $d['withdrawal']['methods'], function ( $m ) { return 'yes' === $m['withdrawals']; } );
			if ( ! $payable ) {
				$errors[] = __( 'Withdrawal checker is enabled but no method supports withdrawals.', 'casinos-cc-tools' );
			}
			if ( ! $d['withdrawal']['limits'] ) {
				$warnings[] = __( 'No withdrawal limits. The VIP level field is hidden.', 'casinos-cc-tools' );
			}
		}

		if ( ! $d['wagering']['enabled'] && ! $d['withdrawal']['enabled'] ) {
			$warnings[] = __( 'No tool is enabled for this casino.', 'casinos-cc-tools' );
		}
		if ( ! $d['casino']['verified_at'] ) {
			$warnings[] = __( 'casino.verified_at is empty. Add the date the facts were checked (internal, not shown on the site).', 'casinos-cc-tools' );
		}

		return array( 'data' => $d, 'errors' => $errors, 'warnings' => $warnings );
	}

	/** JSON encoding that survives post_content filters (no raw <, >, &, '). */
	public static function encode( array $data, $pretty = true ) {
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
		if ( $pretty ) {
			$flags |= JSON_PRETTY_PRINT;
		}
		return wp_json_encode( $data, $flags );
	}

	/* ---------- helpers ---------- */

	private static function str( $a, $k ) {
		return isset( $a[ $k ] ) && is_scalar( $a[ $k ] ) ? trim( sanitize_text_field( (string) $a[ $k ] ) ) : '';
	}

	private static function num( $a, $k ) {
		if ( ! isset( $a[ $k ] ) || '' === $a[ $k ] || ! is_numeric( $a[ $k ] ) ) {
			return null;
		}
		return 0 + $a[ $k ];
	}

	private static function int( $a, $k ) {
		$n = self::num( $a, $k );
		return null === $n ? null : (int) $n;
	}

	private static function date( $s ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}

	private static function list_of_strings( $v ) {
		if ( is_string( $v ) ) {
			$v = explode( ',', $v );
		}
		if ( ! is_array( $v ) ) {
			return array();
		}
		return array_values( array_filter( array_map( function ( $s ) {
			return is_scalar( $s ) ? trim( sanitize_text_field( (string) $s ) ) : '';
		}, $v ) ) );
	}
}
