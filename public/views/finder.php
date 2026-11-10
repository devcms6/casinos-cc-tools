<?php
/**
 * Bonus finder markup. Available: $uid, $a, $dep, $idx, $type, $wx, $sort, $per_casino,
 * $limit, $currency, $rows, $json.
 * The first results are rendered here (readable without JS and by search engines);
 * finder.js takes over and re-renders on every change.
 */
defined( 'ABSPATH' ) || exit;

$money = function ( $n ) use ( $currency ) {
	$symbols = array( 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$' );
	return ( $symbols[ $currency ] ?? $currency . ' ' ) . number_format_i18n( round( $n ) );
};
$base_label = function ( $r ) {
	if ( ! $r['x'] ) {
		return __( 'no wagering', 'casinos-cc-tools' );
	}
	$l = array(
		'db' => __( '%sx deposit + bonus', 'casinos-cc-tools' ),
		'b'  => __( '%sx bonus', 'casinos-cc-tools' ),
		'd'  => __( '%sx deposit', 'casinos-cc-tools' ),
	);
	return sprintf( $l[ $r['base'] ], $r['x'] );
};
$tabs = array(
	''           => __( 'All offers', 'casinos-cc-tools' ),
	'welcome'    => __( 'Welcome bonuses', 'casinos-cc-tools' ),
	'reload'     => __( 'Reloads', 'casinos-cc-tools' ),
	'highroller' => __( 'High roller', 'casinos-cc-tools' ),
);
$stops = CCT_Finder::STOPS;
$ticks = array( 10, 50, 250, 1000, 5000 );
?>
<div class="cct-finder" id="<?php echo esc_attr( $uid ); ?>" data-limit="<?php echo (int) $limit; ?>">
	<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
	<?php if ( $a['intro'] ) : ?><p><?php echo esc_html( $a['intro'] ); ?></p><?php endif; ?>

	<form class="cctf__controls" onsubmit="return false">
		<div class="cctf__field">
			<div class="cctf__dep">
				<label class="cctf__label" for="<?php echo esc_attr( $uid ); ?>-range"><?php esc_html_e( 'Your deposit', 'casinos-cc-tools' ); ?></label>
				<output class="cctf__val" for="<?php echo esc_attr( $uid ); ?>-range" data-out="val"><?php echo esc_html( $money( $dep ) ); ?></output>
			</div>
			<input class="cctf__range" id="<?php echo esc_attr( $uid ); ?>-range" name="range" type="range" min="0" max="<?php echo count( $stops ) - 1; ?>" step="1" value="<?php echo (int) $idx; ?>" aria-valuetext="<?php echo esc_attr( $money( $dep ) ); ?>" style="--fill:<?php echo esc_attr( round( $idx / ( count( $stops ) - 1 ) * 100, 2 ) ); ?>%">
			<div class="cctf__ticks" aria-hidden="true">
				<?php foreach ( $ticks as $t ) : $pos = array_search( $t, $stops, true ); ?>
					<span style="left:<?php echo esc_attr( round( $pos / ( count( $stops ) - 1 ) * 100, 2 ) ); ?>%"><?php echo esc_html( $money( $t ) ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="cctf__field">
			<label class="cctf__label" for="<?php echo esc_attr( $uid ); ?>-method"><?php esc_html_e( 'Deposit method', 'casinos-cc-tools' ); ?></label>
			<select class="cctf__select" id="<?php echo esc_attr( $uid ); ?>-method" name="method">
				<option value=""><?php esc_html_e( 'Card, e-wallet or bank', 'casinos-cc-tools' ); ?></option>
				<option value="Skrill">Skrill</option>
				<option value="Neteller">Neteller</option>
				<option value="Volt">Volt</option>
				<option value="crypto"><?php esc_html_e( 'Crypto', 'casinos-cc-tools' ); ?></option>
			</select>
		</div>
		<div class="cctf__field">
			<label class="cctf__label" for="<?php echo esc_attr( $uid ); ?>-wx"><?php esc_html_e( 'Wagering', 'casinos-cc-tools' ); ?></label>
			<select class="cctf__select" id="<?php echo esc_attr( $uid ); ?>-wx" name="wx">
				<option value=""><?php esc_html_e( 'Any wagering', 'casinos-cc-tools' ); ?></option>
				<?php foreach ( array( 10, 20, 25, 30, 35, 40 ) as $v ) : ?>
					<option value="<?php echo (int) $v; ?>" <?php selected( $wx, $v ); ?>><?php echo esc_html( sprintf( __( '%dx or less', 'casinos-cc-tools' ), $v ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="cctf__field cctf__field--wide">
			<label class="cctf__label" for="<?php echo esc_attr( $uid ); ?>-sort"><?php esc_html_e( 'Sort by', 'casinos-cc-tools' ); ?></label>
			<select class="cctf__select" id="<?php echo esc_attr( $uid ); ?>-sort" name="sort">
				<option value="value" <?php selected( $sort, 'value' ); ?>><?php esc_html_e( 'Best value', 'casinos-cc-tools' ); ?></option>
				<option value="bonus" <?php selected( $sort, 'bonus' ); ?>><?php esc_html_e( 'Biggest bonus', 'casinos-cc-tools' ); ?></option>
				<option value="wager" <?php selected( $sort, 'wager' ); ?>><?php esc_html_e( 'Least to wager', 'casinos-cc-tools' ); ?></option>
				<option value="min" <?php selected( $sort, 'min' ); ?>><?php esc_html_e( 'Lowest minimum deposit', 'casinos-cc-tools' ); ?></option>
			</select>
		</div>
		<div class="cctf__tabs" role="group" aria-label="<?php esc_attr_e( 'Offer type', 'casinos-cc-tools' ); ?>">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<button type="button" class="cctf__tab" data-type="<?php echo esc_attr( $k ); ?>" aria-pressed="<?php echo $k === $type ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</div>
		<label class="cctf__check" for="<?php echo esc_attr( $uid ); ?>-one"><input type="checkbox" id="<?php echo esc_attr( $uid ); ?>-one" name="one" <?php checked( $per_casino ); ?>> <?php esc_html_e( 'Best offer per casino only', 'casinos-cc-tools' ); ?></label>
	</form>

	<div class="cctf__summary" aria-live="polite">
		<p data-out="sum"><?php
			$n = count( $rows );
			/* translators: 1: deposit amount, 2: number of offers */
			echo wp_kses( sprintf( __( 'With a <b>%1$s</b> deposit you qualify for <b>%2$s</b>.', 'casinos-cc-tools' ), esc_html( $money( $dep ) ), esc_html( sprintf( _n( '%d offer', '%d offers', $n, 'casinos-cc-tools' ), $n ) ) ), array( 'b' => array() ) );
		?></p>
		<small><?php esc_html_e( 'Expected value assumes 96% RTP slots that count 100% towards wagering.', 'casinos-cc-tools' ); ?></small>
	</div>

	<ol class="cctf__list" data-out="list">
		<?php foreach ( array_slice( $rows, 0, $limit ) as $i => $r ) : $pos = $r['value'] >= 0; ?>
		<li class="cctf__offer">
			<span class="cctf__rank"><?php echo (int) $i + 1; ?></span>
			<div class="cctf__who">
				<a href="<?php echo esc_url( $r['c']['u'] ); ?>"><?php echo esc_html( $r['c']['n'] ); ?></a>
				<span class="cctf__what"><?php echo esc_html( $r['name'] . ' · ' . sprintf( __( '%1$s%% up to %2$s', 'casinos-cc-tools' ), $r['pct'], $money( $r['max'] ) ) ); ?></span>
				<?php if ( $r['extras'] ) : ?><span class="cctf__extra">+ <?php echo esc_html( $r['extras'] ); ?></span><?php endif; ?>
			</div>
			<div class="cctf__stats">
				<div class="cctf__stat"><b><?php echo esc_html( $money( $r['bonus'] ) ); ?></b><small><?php esc_html_e( 'bonus', 'casinos-cc-tools' ); ?></small></div>
				<div class="cctf__stat"><b><?php echo esc_html( $money( $r['wager'] ) ); ?></b><small><?php echo esc_html( __( 'to wager', 'casinos-cc-tools' ) . ' · ' . $base_label( $r ) ); ?></small></div>
				<div class="cctf__stat"><b><?php echo $r['days'] ? esc_html( sprintf( __( '%d days', 'casinos-cc-tools' ), $r['days'] ) ) : '–'; ?></b><small><?php echo esc_html( ( $r['days'] ? __( 'to wager', 'casinos-cc-tools' ) : __( 'time limit not published', 'casinos-cc-tools' ) ) . ( $r['c']['mb'] ? ' · ' . sprintf( __( 'max bet %s', 'casinos-cc-tools' ), $money( $r['c']['mb'] ) ) : '' ) ); ?></small></div>
			</div>
			<div class="cctf__ev"><span class="cctf__evpill <?php echo $pos ? 'is-pos' : 'is-neg'; ?>"><?php echo esc_html( ( $pos ? '+' : '−' ) . $money( abs( $r['value'] ) ) ); ?></span><small><?php esc_html_e( 'expected value', 'casinos-cc-tools' ); ?></small></div>
			<div class="cctf__go"><a class="cctf__btn cctf__btn--y" href="<?php echo esc_url( $r['c']['b'] ); ?>"><?php esc_html_e( 'Calculate', 'casinos-cc-tools' ); ?></a><a class="cctf__btn cctf__btn--l" href="<?php echo esc_url( $r['c']['u'] ); ?>"><?php esc_html_e( 'Review', 'casinos-cc-tools' ); ?></a></div>
		</li>
		<?php endforeach; ?>
	</ol>
	<button type="button" class="cctf__more" data-out="more" <?php echo count( $rows ) > $limit ? '' : 'hidden'; ?>><?php echo esc_html( sprintf( __( 'Show %d more', 'casinos-cc-tools' ), min( $limit, count( $rows ) - $limit ) ) ); ?></button>
	<p class="cctf__src"><?php esc_html_e( 'Based on each casino’s terms and conditions. “Least to wager” sorts by the total amount you need to bet, not the multiplier. Free-spin offers aren’t included because their value depends on what the spins pay out.', 'casinos-cc-tools' ); ?></p>

	<script type="application/json" class="cctf-data"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with HEX_TAG/AMP/APOS ?></script>
</div>
