<?php
/**
 * Wagering calculator markup.
 * Available: $uid, $casino, $tool, $json, $atts
 */
defined( 'ABSPATH' ) || exit;

$cap_countries = $tool['win_cap']['countries'];
$excluded      = $tool['excluded_methods'];
?>
<div class="<?php echo esc_attr( $class ); ?>" data-cct="wagering" id="<?php echo esc_attr( $uid ); ?>">
	<?php if ( $atts['title'] ) : ?><h2><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
	<?php if ( $atts['intro'] ) : ?><p><?php echo esc_html( $atts['intro'] ); ?></p><?php endif; ?>

	<div class="cct">
		<form class="cct__card" novalidate onsubmit="return false">
			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-offer"><?php esc_html_e( 'Offer', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-offer" name="offer">
					<?php foreach ( $tool['offers'] as $o ) : ?>
						<option value="<?php echo esc_attr( $o['id'] ); ?>"><?php echo esc_html( $o['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="cct__help" data-out="offer-help"></span>
			</div>

			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-amount" data-out="amount-label"><?php esc_html_e( 'Deposit', 'casinos-cc-tools' ); ?></label>
				<div class="cct__money" data-symbol=""><input class="cct__input" id="<?php echo esc_attr( $uid ); ?>-amount" name="amount" type="number" min="0" step="10" value="100" inputmode="decimal"></div>
				<div class="cct__quick" data-quick="20,50,100,250,500"></div>
				<span class="cct__help" data-out="amount-help"></span>
			</div>

			<?php if ( $excluded ) : ?>
			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-method"><?php esc_html_e( 'Deposit method', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-method" name="method">
					<option value=""><?php esc_html_e( 'Card, crypto or other method', 'casinos-cc-tools' ); ?></option>
					<?php foreach ( $excluded as $m ) : ?>
						<option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<?php if ( $cap_countries && $tool['win_cap']['multiplier'] ) : ?>
			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-country"><?php esc_html_e( 'Country', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-country" name="country">
					<option value=""><?php esc_html_e( 'Other countries', 'casinos-cc-tools' ); ?></option>
					<?php foreach ( $cap_countries as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="cct__field">
				<label class="cct__label" for="<?php echo esc_attr( $uid ); ?>-rtp"><?php esc_html_e( 'Average slot RTP', 'casinos-cc-tools' ); ?></label>
				<select class="cct__select" id="<?php echo esc_attr( $uid ); ?>-rtp" name="rtp">
					<option value="94">94%</option><option value="95">95%</option><option value="96" selected>96%</option><option value="97">97%</option>
				</select>
			</div>
		</form>

		<div class="cct__bar" aria-hidden="true">
			<div><b data-out="bar-v">–</b><?php esc_html_e( 'to wager', 'casinos-cc-tools' ); ?></div>
			<button type="button" data-jump tabindex="-1"><?php esc_html_e( 'Breakdown', 'casinos-cc-tools' ); ?></button>
		</div>

		<div class="cct__card" aria-live="polite" data-results>
			<div class="cct__total">
				<span class="cct__total-v" data-out="wager">–</span>
				<span class="cct__total-l"><?php esc_html_e( 'to wager before you can withdraw', 'casinos-cc-tools' ); ?></span>
			</div>
			<ul class="cct__rows">
				<li class="cct__row"><span><?php esc_html_e( 'Bonus money', 'casinos-cc-tools' ); ?></span><b data-out="bonus">–</b></li>
				<li class="cct__row" data-row="extras"><span><?php esc_html_e( 'Extras', 'casinos-cc-tools' ); ?></span><b data-out="extras">–</b></li>
				<li class="cct__row"><span><?php esc_html_e( 'Wagering rule', 'casinos-cc-tools' ); ?></span><b data-out="rule">–</b></li>
				<?php if ( null !== $tool['max_bet'] ) : ?>
				<li class="cct__row"><span><?php
					/* translators: %s: max bet amount */
					printf( esc_html__( 'Spins needed at %s max bet', 'casinos-cc-tools' ), '<span data-out="maxbet"></span>' ); ?></span><b data-out="spins">–</b></li>
				<?php endif; ?>
				<li class="cct__row" data-row="perday"><span data-out="perday-l"><?php esc_html_e( 'Per day to finish in time', 'casinos-cc-tools' ); ?></span><b data-out="perday">–</b></li>
				<li class="cct__row"><span><?php esc_html_e( 'Expected cost of wagering', 'casinos-cc-tools' ); ?></span><b data-out="cost">–</b></li>
			</ul>
			<ul class="rules" data-out="rules"></ul>
		</div>
	</div>

	<?php if ( $tool['sources'] || $tool['contribution_note'] || $casino['verified_at'] ) : ?>
	<p class="src">
		<?php if ( $tool['sources'] ) : ?><b><?php esc_html_e( 'Sources.', 'casinos-cc-tools' ); ?></b> <?php echo esc_html( $tool['sources'] ); ?> <?php endif; ?>
		<?php echo esc_html( $tool['contribution_note'] ); ?>
		<?php if ( $casino['verified_at'] ) : ?><?php
			/* translators: %s: date */
			printf( esc_html__( 'Last checked %s.', 'casinos-cc-tools' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $casino['verified_at'] ) ) ) ); ?><?php endif; ?>
	</p>
	<?php endif; ?>

	<script type="application/json" class="cct-data"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with HEX_TAG/AMP/APOS ?></script>
</div>
