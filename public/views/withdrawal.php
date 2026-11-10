<?php
/**
 * Withdrawal checker markup.
 * Available: $uid, $casino, $tool, $json, $atts
 */
defined( 'ABSPATH' ) || exit;

$types_present = array_values( array_unique( wp_list_pluck( $tool['methods'], 'type' ) ) );
$type_labels   = array(
	'crypto'  => __( 'Crypto', 'casinos-cc-tools' ),
	'ewallet' => __( 'E-wallets', 'casinos-cc-tools' ),
	'bank'    => __( 'Bank', 'casinos-cc-tools' ),
	'card'    => __( 'Cards & vouchers', 'casinos-cc-tools' ),
	'other'   => __( 'Other', 'casinos-cc-tools' ),
);
?>
<div class="<?php echo esc_attr( $class ); ?>" data-cct="withdrawal" id="<?php echo esc_attr( $uid ); ?>">
	<?php
	$cct_label = trim( (string) $atts['label'] );
	if ( ! in_array( strtolower( $cct_label ), array( 'no', 'none', '0', 'false' ), true ) ) :
		$cct_label = '' !== $cct_label ? $cct_label : __( 'Withdrawal estimation tool', 'casinos-cc-tools' );
		?><div class="cct-label"><span class="cct-label__dot" aria-hidden="true"></span><?php echo esc_html( $cct_label ); ?></div>
	<?php endif; ?>
	<?php if ( $atts['title'] ) : ?><h2><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
	<?php if ( $atts['intro'] ) : ?><p><?php echo esc_html( $atts['intro'] ); ?></p><?php endif; ?>

	<div class="cct">
		<form class="cct__card" novalidate onsubmit="return false">
			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-amount"><?php esc_html_e( 'Amount to withdraw', 'casinos-cc-tools' ); ?></label>
				<div class="cct__money" data-symbol=""><input class="cct__input" id="<?php echo esc_attr( $uid ); ?>-amount" name="amount" type="number" min="0" step="10" value="500" inputmode="decimal"></div>
				<div class="cct__quick" data-quick="100,500,1500,5000"></div>
			</div>

			<?php if ( $tool['limits'] ) : ?>
			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-vip"><?php esc_html_e( 'VIP level', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-vip" name="vip">
					<?php foreach ( $tool['limits'] as $i => $l ) : ?>
						<option value="<?php echo (int) $i; ?>"><?php echo esc_html( $l['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="cct__help" data-out="vip-help"></span>
			</div>
			<?php endif; ?>

			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-dep"><?php esc_html_e( 'You deposited with', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-dep" name="dep">
					<?php foreach ( $tool['methods'] as $m ) : if ( 'unknown' === $m['withdrawals'] ) { continue; } ?>
						<option><?php echo esc_html( $m['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="cct__field">
				<label class="cct__check" for="<?php echo esc_attr( $uid ); ?>-bonus"><input type="checkbox" id="<?php echo esc_attr( $uid ); ?>-bonus" name="bonus"><span><?php esc_html_e( 'I still have an active bonus', 'casinos-cc-tools' ); ?></span></label>
			</div>
		</form>

		<div class="cct__bar" aria-hidden="true">
			<div><b data-out="bar-v">–</b><span data-out="bar-l"></span></div>
			<button type="button" data-jump tabindex="-1"><?php esc_html_e( 'See methods', 'casinos-cc-tools' ); ?></button>
		</div>

		<div class="cct__card" aria-live="polite" data-results>
			<p class="cct__lead" data-out="lead"></p>
			<?php if ( count( $types_present ) > 1 ) : ?>
			<div class="cct__filters" role="group" aria-label="<?php esc_attr_e( 'Method type', 'casinos-cc-tools' ); ?>">
				<button type="button" class="cct__filter" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'casinos-cc-tools' ); ?></button>
				<?php foreach ( array( 'crypto', 'ewallet', 'bank', 'card', 'other' ) as $t ) :
					$has = in_array( $t, $types_present, true ) || ( 'card' === $t && in_array( 'voucher', $types_present, true ) );
					if ( ! $has ) { continue; } ?>
					<button type="button" class="cct__filter" data-filter="<?php echo esc_attr( $t ); ?>" aria-pressed="false"><?php echo esc_html( $type_labels[ $t ] ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<div class="cct__table-wrap">
				<table class="cct__table">
					<thead><tr>
						<th><?php esc_html_e( 'Method', 'casinos-cc-tools' ); ?></th>
						<th><?php esc_html_e( 'Min. withdrawal', 'casinos-cc-tools' ); ?></th>
						<th><?php esc_html_e( 'Status', 'casinos-cc-tools' ); ?></th>
						<th><?php esc_html_e( 'Processing', 'casinos-cc-tools' ); ?></th>
					</tr></thead>
					<tbody data-out="rows">
						<?php /* Server-rendered rows: readable without JS and by search engines. JS replaces them. */
						foreach ( $tool['methods'] as $m ) : ?>
						<tr><td class="c-name"><span class="fw-b"><?php echo esc_html( $m['name'] ); ?></span></td>
							<td class="c-min num"><?php echo null !== $m['min_withdrawal'] && 'yes' === $m['withdrawals'] ? esc_html( number_format_i18n( $m['min_withdrawal'] ) . ' ' . $casino['currency'] ) : ''; ?></td>
							<td class="c-status"><?php echo 'yes' === $m['withdrawals'] ? esc_html__( 'Available', 'casinos-cc-tools' ) : ( 'no' === $m['withdrawals'] ? esc_html__( 'Deposits only', 'casinos-cc-tools' ) : esc_html__( 'Not confirmed', 'casinos-cc-tools' ) ); ?></td>
							<td class="c-time"><?php echo esc_html( $m['time'] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<button type="button" class="cct__more" data-out="more" aria-expanded="false" hidden></button>
			<ul class="rules" data-out="rules"></ul>
		</div>
	</div>

	<p class="src"><?php
		/* translators: %s: casino name */
		printf( esc_html__( 'Based on %s’s terms and conditions.', 'casinos-cc-tools' ), esc_html( $casino['name'] ) );
		if ( $tool['note'] ) {
			echo ' ' . esc_html( $tool['note'] );
		}
	?></p>

	<script type="application/json" class="cct-data"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with HEX_TAG/AMP/APOS ?></script>
</div>
