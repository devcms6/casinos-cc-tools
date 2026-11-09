<?php
/**
 * Admin screens: Casino Tools → Casinos, Add/Edit casino, Settings.
 * JSON import lives in CCT_Import.
 */

defined( 'ABSPATH' ) || exit;

class CCT_Admin {

	const CAP  = 'edit_posts';
	const SLUG = 'cct-casinos';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_cct_save_profile', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cct_delete_profile', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_cct_export_profile', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_cct_save_settings', array( __CLASS__, 'handle_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CCT_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Casinos', 'casinos-cc-tools' ) . '</a>' );
			return $links;
		} );
	}

	public static function menu() {
		add_menu_page( __( 'Casino Tools', 'casinos-cc-tools' ), __( 'Casino Tools', 'casinos-cc-tools' ), self::CAP, self::SLUG, array( __CLASS__, 'page_list' ), 'dashicons-calculator', 58 );
		add_submenu_page( self::SLUG, __( 'Casinos', 'casinos-cc-tools' ), __( 'Casinos', 'casinos-cc-tools' ), self::CAP, self::SLUG, array( __CLASS__, 'page_list' ) );
		add_submenu_page( self::SLUG, __( 'Add casino', 'casinos-cc-tools' ), __( 'Add casino', 'casinos-cc-tools' ), self::CAP, 'cct-edit', array( __CLASS__, 'page_edit' ) );
		add_submenu_page( self::SLUG, __( 'Import JSON', 'casinos-cc-tools' ), __( 'Import JSON', 'casinos-cc-tools' ), self::CAP, 'cct-import', array( 'CCT_Import', 'page' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'casinos-cc-tools' ), __( 'Settings', 'casinos-cc-tools' ), 'manage_options', 'cct-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'cct-' ) && false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'cct-admin', CCT_URL . 'admin/css/admin.css', array(), CCT_VERSION );
		wp_enqueue_script( 'cct-admin', CCT_URL . 'admin/js/admin.js', array(), CCT_VERSION, true );
	}

	/* ------------------------------------------------------------------ list */

	public static function page_list() {
		$profiles = get_posts( array(
			'post_type'      => CCT_Profiles::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		?>
		<div class="wrap cct-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Casino Tools', 'casinos-cc-tools' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cct-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add casino', 'casinos-cc-tools' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cct-import' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import JSON', 'casinos-cc-tools' ); ?></a>
			<hr class="wp-header-end">
			<?php self::flash(); ?>

			<?php if ( ! $profiles ) : ?>
				<div class="cct-empty">
					<h2><?php esc_html_e( 'No casinos yet', 'casinos-cc-tools' ); ?></h2>
					<p><?php esc_html_e( 'Add a casino by hand, or import a JSON file generated from BrandFinder facts.', 'casinos-cc-tools' ); ?></p>
				</div>
			<?php else : ?>
			<table class="widefat striped cct-list">
				<thead><tr>
					<th><?php esc_html_e( 'Casino', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Review page', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Tools', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Facts checked', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'BrandFinder', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'casinos-cc-tools' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $profiles as $p ) :
					$d      = CCT_Profiles::get_data( $p->ID ) ?: CCT_Schema::blank();
					$review = (int) ( $d['casino']['review_post_id'] ?? 0 );
					$edit   = admin_url( 'admin.php?page=cct-edit&profile=' . $p->ID );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $p->post_title ); ?></a></strong>
							<?php if ( 'draft' === $p->post_status ) : ?> — <span class="cct-pill cct-pill--draft"><?php esc_html_e( 'Draft', 'casinos-cc-tools' ); ?></span><?php endif; ?>
							<div class="row-actions">
								<a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'casinos-cc-tools' ); ?></a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cct_export_profile&profile=' . $p->ID ), 'cct_export_' . $p->ID ) ); ?>"><?php esc_html_e( 'Export JSON', 'casinos-cc-tools' ); ?></a> |
								<span class="trash"><a class="cct-confirm" data-confirm="<?php esc_attr_e( 'Delete this tool profile? The tools disappear from its pages.', 'casinos-cc-tools' ); ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cct_delete_profile&profile=' . $p->ID ), 'cct_delete_' . $p->ID ) ); ?>"><?php esc_html_e( 'Delete', 'casinos-cc-tools' ); ?></a></span>
							</div>
						</td>
						<td><?php if ( $review && get_post( $review ) ) : ?><a href="<?php echo esc_url( get_permalink( $review ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $review ) ); ?></a><?php else : ?><span class="cct-muted">—</span><?php endif; ?></td>
						<td>
							<?php foreach ( array( 'wagering' => __( 'Wagering', 'casinos-cc-tools' ), 'withdrawal' => __( 'Withdrawal', 'casinos-cc-tools' ) ) as $k => $label ) : ?>
								<span class="cct-pill <?php echo ! empty( $d[ $k ]['enabled'] ) ? 'cct-pill--on' : 'cct-pill--off'; ?>"><?php echo esc_html( $label ); ?></span>
							<?php endforeach; ?>
						</td>
						<td><?php echo $d['casino']['verified_at'] ? esc_html( $d['casino']['verified_at'] ) : '<span class="cct-muted">—</span>'; ?></td>
						<td><?php echo $d['casino']['brandfinder_analysis_id'] ? '#' . (int) $d['casino']['brandfinder_analysis_id'] : '<span class="cct-muted">—</span>'; ?></td>
						<td><?php echo esc_html( human_time_diff( get_post_modified_time( 'U', true, $p ) ) . ' ' . __( 'ago', 'casinos-cc-tools' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Add [cc_wagering_calculator] or [cc_withdrawal_checker] to a casino’s review or any of its subpages. The casino is found from the page’s parent.', 'casinos-cc-tools' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ edit */

	public static function page_edit() {
		$profile_id = isset( $_GET['profile'] ) ? absint( $_GET['profile'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$failed     = get_transient( 'cct_failed_' . get_current_user_id() );
		$messages   = array( 'errors' => array(), 'warnings' => array() );

		if ( $failed && (int) $failed['profile'] === $profile_id ) {
			$d        = $failed['data'];
			$messages = $failed;
			delete_transient( 'cct_failed_' . get_current_user_id() );
		} elseif ( $profile_id ) {
			$d = CCT_Profiles::get_data( $profile_id );
			if ( ! $d ) {
				echo '<div class="wrap"><p>' . esc_html__( 'Profile not found.', 'casinos-cc-tools' ) . '</p></div>';
				return;
			}
			$check    = CCT_Schema::validate( $d );
			$d        = $check['data'];
			$messages = array( 'errors' => array(), 'warnings' => $check['warnings'] );
		} else {
			$d = CCT_Schema::blank();
		}

		$status    = $profile_id ? get_post_status( $profile_id ) : 'draft';
		$revisions = $profile_id ? wp_get_post_revisions( $profile_id, array( 'fields' => 'ids' ) ) : array();
		$c         = $d['casino'];
		$w         = $d['wagering'];
		$wd        = $d['withdrawal'];
		?>
		<div class="wrap cct-admin">
			<h1><?php echo $profile_id ? esc_html( sprintf( __( 'Edit %s', 'casinos-cc-tools' ), $c['name'] ) ) : esc_html__( 'Add casino', 'casinos-cc-tools' ); ?></h1>
			<?php self::flash(); self::messages( $messages ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cct-form" id="cct-form">
				<input type="hidden" name="action" value="cct_save_profile">
				<input type="hidden" name="profile" value="<?php echo (int) $profile_id; ?>">
				<input type="hidden" name="mode" value="form" id="cct-mode">
				<?php wp_nonce_field( 'cct_save_profile' ); ?>

				<h2 class="nav-tab-wrapper cct-tabs">
					<a href="#cct-tab-form" class="nav-tab nav-tab-active" data-mode="form"><?php esc_html_e( 'Form', 'casinos-cc-tools' ); ?></a>
					<a href="#cct-tab-json" class="nav-tab" data-mode="json"><?php esc_html_e( 'JSON', 'casinos-cc-tools' ); ?></a>
				</h2>

				<div class="cct-layout">
				<div class="cct-main">
				<div id="cct-tab-form" class="cct-tab">

					<div class="cct-box">
						<h2><?php esc_html_e( 'Casino', 'casinos-cc-tools' ); ?></h2>
						<div class="cct-grid">
							<?php self::text( 'cct[casino][name]', __( 'Name', 'casinos-cc-tools' ), $c['name'], array( 'required' => true ) ); ?>
							<?php self::text( 'cct[casino][slug]', __( 'Slug', 'casinos-cc-tools' ), $c['slug'], array( 'help' => __( 'Used in casino="…". Usually the review URL slug.', 'casinos-cc-tools' ) ) ); ?>
							<?php self::text( 'cct[casino][review_post_id]', __( 'Review page (URL or post ID)', 'casinos-cc-tools' ), $c['review_post_id'] ? $c['review_post_id'] : '', array(
								'help' => $c['review_post_id'] && get_post( $c['review_post_id'] ) ? sprintf( __( 'Linked: %s', 'casinos-cc-tools' ), get_the_title( $c['review_post_id'] ) ) : __( 'Paste the main review URL, e.g. https://casinos.cc/golazzo-casino/', 'casinos-cc-tools' ),
							) ); ?>
							<?php self::text( 'cct[casino][currency]', __( 'Currency', 'casinos-cc-tools' ), $c['currency'], array( 'class' => 'small-text', 'maxlength' => 3 ) ); ?>
							<?php self::text( 'cct[casino][brandfinder_analysis_id]', __( 'BrandFinder analysis ID', 'casinos-cc-tools' ), $c['brandfinder_analysis_id'], array( 'type' => 'number' ) ); ?>
							<?php self::text( 'cct[casino][verified_at]', __( 'Facts checked on', 'casinos-cc-tools' ), $c['verified_at'], array( 'type' => 'date' ) ); ?>
						</div>
					</div>

					<div class="cct-box">
						<h2><label><input type="checkbox" name="cct[wagering][enabled]" value="1" <?php checked( $w['enabled'] ); ?>> <?php esc_html_e( 'Wagering calculator', 'casinos-cc-tools' ); ?></label></h2>
						<p class="description"><?php esc_html_e( 'Shortcode:', 'casinos-cc-tools' ); ?> <code>[cc_wagering_calculator]</code></p>
						<div class="cct-grid">
							<?php self::text( 'cct[wagering][max_bet]', __( 'Max bet with bonus', 'casinos-cc-tools' ), $w['max_bet'], array( 'type' => 'number', 'step' => 'any' ) ); ?>
							<?php self::text( 'cct[wagering][days]', __( 'Days to wager (default)', 'casinos-cc-tools' ), $w['days'], array( 'type' => 'number' ) ); ?>
							<?php self::text( 'cct[wagering][activate_hours]', __( 'Hours to activate', 'casinos-cc-tools' ), $w['activate_hours'], array( 'type' => 'number', 'step' => 'any' ) ); ?>
							<?php self::text( 'cct[wagering][excluded_methods]', __( 'Deposit methods excluded from bonuses', 'casinos-cc-tools' ), implode( ', ', $w['excluded_methods'] ), array( 'help' => __( 'Comma separated', 'casinos-cc-tools' ) ) ); ?>
							<?php self::text( 'cct[wagering][win_cap][multiplier]', __( 'Win cap (x bonus)', 'casinos-cc-tools' ), $w['win_cap']['multiplier'], array( 'type' => 'number', 'step' => 'any' ) ); ?>
							<?php self::text( 'cct[wagering][win_cap][countries]', __( 'Win cap countries', 'casinos-cc-tools' ), implode( ', ', $w['win_cap']['countries'] ), array( 'help' => __( 'Comma separated', 'casinos-cc-tools' ) ) ); ?>
						</div>

						<h3><?php esc_html_e( 'Offers', 'casinos-cc-tools' ); ?></h3>
						<?php self::repeater( 'offers', 'cct[wagering][offers]', $w['offers'], array(
							'name'               => array( __( 'Name', 'casinos-cc-tools' ), 'text', 'wide' ),
							'kind'               => array( __( 'Type', 'casinos-cc-tools' ), array( 'deposit' => __( 'Deposit bonus', 'casinos-cc-tools' ), 'spins' => __( 'Free spins', 'casinos-cc-tools' ) ) ),
							'match_pct'          => array( __( 'Match %', 'casinos-cc-tools' ), 'number' ),
							'max_bonus'          => array( __( 'Max bonus', 'casinos-cc-tools' ), 'number' ),
							'min_deposit'        => array( __( 'Min dep.', 'casinos-cc-tools' ), 'number' ),
							'wagering_x'         => array( __( 'Wager x', 'casinos-cc-tools' ), 'number' ),
							'wagering_base'      => array( __( 'Wager on', 'casinos-cc-tools' ), array( 'deposit_bonus' => __( 'Deposit + bonus', 'casinos-cc-tools' ), 'bonus' => __( 'Bonus', 'casinos-cc-tools' ), 'deposit' => __( 'Deposit', 'casinos-cc-tools' ), 'winnings' => __( 'Winnings', 'casinos-cc-tools' ) ) ),
							'extras'             => array( __( 'Extras', 'casinos-cc-tools' ), 'text' ),
							'extras_min_deposit' => array( __( 'Extras min.', 'casinos-cc-tools' ), 'number' ),
							'days'               => array( __( 'Days', 'casinos-cc-tools' ), 'number' ),
							'id'                 => array( 'ID', 'hidden' ),
							'description'        => array( 'Description', 'hidden' ),
						) ); ?>

						<div class="cct-grid cct-grid--1">
							<?php self::text( 'cct[wagering][contribution_note]', __( 'Game contribution note', 'casinos-cc-tools' ), $w['contribution_note'] ); ?>
							<?php self::textarea( 'cct[wagering][sources]', __( 'Sources (shown under the tool)', 'casinos-cc-tools' ), $w['sources'] ); ?>
						</div>
					</div>

					<div class="cct-box">
						<h2><label><input type="checkbox" name="cct[withdrawal][enabled]" value="1" <?php checked( $wd['enabled'] ); ?>> <?php esc_html_e( 'Withdrawal checker', 'casinos-cc-tools' ); ?></label></h2>
						<p class="description"><?php esc_html_e( 'Shortcode:', 'casinos-cc-tools' ); ?> <code>[cc_withdrawal_checker]</code></p>

						<h3><?php esc_html_e( 'Payment methods', 'casinos-cc-tools' ); ?></h3>
						<?php self::repeater( 'methods', 'cct[withdrawal][methods]', $wd['methods'], array(
							'name'           => array( __( 'Method', 'casinos-cc-tools' ), 'text', 'wide' ),
							'type'           => array( __( 'Type', 'casinos-cc-tools' ), array_combine( CCT_Schema::METHOD_TYPES, CCT_Schema::METHOD_TYPES ) ),
							'withdrawals'    => array( __( 'Withdrawals', 'casinos-cc-tools' ), array( 'yes' => __( 'Yes', 'casinos-cc-tools' ), 'no' => __( 'No (deposits only)', 'casinos-cc-tools' ), 'unknown' => __( 'Unknown', 'casinos-cc-tools' ) ) ),
							'min_withdrawal' => array( __( 'Min. withdrawal', 'casinos-cc-tools' ), 'number' ),
							'time'           => array( __( 'Processing time', 'casinos-cc-tools' ), 'text' ),
							'fee'            => array( __( 'Fee', 'casinos-cc-tools' ), 'text' ),
						) ); ?>

						<div class="cct-grid">
							<?php self::text( 'cct[withdrawal][default_time][other]', __( 'Default processing time', 'casinos-cc-tools' ), $wd['default_time']['other'], array( 'help' => __( 'Used when a method has no time of its own', 'casinos-cc-tools' ) ) ); ?>
							<?php self::text( 'cct[withdrawal][default_time][bank]', __( 'Default time for bank methods', 'casinos-cc-tools' ), $wd['default_time']['bank'] ); ?>
						</div>

						<h3><?php esc_html_e( 'Withdrawal limits (VIP levels)', 'casinos-cc-tools' ); ?></h3>
						<?php self::repeater( 'limits', 'cct[withdrawal][limits]', $wd['limits'], array(
							'label'   => array( __( 'Level', 'casinos-cc-tools' ), 'text', 'wide' ),
							'daily'   => array( __( 'Daily', 'casinos-cc-tools' ), 'number' ),
							'weekly'  => array( __( 'Weekly', 'casinos-cc-tools' ), 'number' ),
							'monthly' => array( __( 'Monthly', 'casinos-cc-tools' ), 'number' ),
						) ); ?>

						<div class="cct-grid cct-grid--1">
							<?php self::textarea( 'cct[withdrawal][sources]', __( 'Sources (shown under the tool)', 'casinos-cc-tools' ), $wd['sources'] ); ?>
						</div>
					</div>
				</div>

				<div id="cct-tab-json" class="cct-tab" hidden>
					<div class="cct-box">
						<p class="description"><?php esc_html_e( 'This is the last saved version of the profile. Edit it and save from this tab to replace the form values. The format is described in docs/schema.md in the plugin folder.', 'casinos-cc-tools' ); ?></p>
						<textarea name="cct_json" id="cct-json" class="large-text code" rows="32" spellcheck="false"><?php echo esc_textarea( CCT_Schema::encode( $d ) ); ?></textarea>
					</div>
				</div>
				</div>

				<aside class="cct-side">
					<div class="cct-box">
						<h2><?php esc_html_e( 'Publish', 'casinos-cc-tools' ); ?></h2>
						<p>
							<label for="cct-status"><?php esc_html_e( 'Status', 'casinos-cc-tools' ); ?></label><br>
							<select name="status" id="cct-status">
								<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Live – tools show on the site', 'casinos-cc-tools' ); ?></option>
								<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft – tools hidden', 'casinos-cc-tools' ); ?></option>
							</select>
						</p>
						<?php submit_button( __( 'Save profile', 'casinos-cc-tools' ), 'primary', 'submit', false ); ?>
						<?php if ( $profile_id ) : ?>
							<p class="cct-side__links">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cct_export_profile&profile=' . $profile_id ), 'cct_export_' . $profile_id ) ); ?>"><?php esc_html_e( 'Export JSON', 'casinos-cc-tools' ); ?></a>
								<?php if ( $revisions ) : ?>
									<br><a href="<?php echo esc_url( admin_url( 'revision.php?revision=' . reset( $revisions ) ) ); ?>"><?php echo esc_html( sprintf( _n( '%d revision', '%d revisions', count( $revisions ), 'casinos-cc-tools' ), count( $revisions ) ) ); ?></a>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
					<?php if ( $c['review_post_id'] && get_post( $c['review_post_id'] ) ) : ?>
					<div class="cct-box">
						<h2><?php esc_html_e( 'Pages', 'casinos-cc-tools' ); ?></h2>
						<ul class="cct-pages">
							<li><a href="<?php echo esc_url( get_permalink( $c['review_post_id'] ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $c['review_post_id'] ) ); ?></a></li>
							<?php foreach ( get_children( array( 'post_parent' => $c['review_post_id'], 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 20 ) ) as $child ) : ?>
								<li>↳ <a href="<?php echo esc_url( get_permalink( $child ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $child ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
				</aside>
				</div>
			</form>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		check_admin_referer( 'cct_save_profile' );

		$profile_id = absint( $_POST['profile'] ?? 0 );
		$status     = sanitize_key( $_POST['status'] ?? 'draft' );
		$mode       = sanitize_key( $_POST['mode'] ?? 'form' );

		if ( 'json' === $mode ) {
			$input = json_decode( wp_unslash( $_POST['cct_json'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by CCT_Schema
			if ( null === $input ) {
				self::fail( $profile_id, CCT_Schema::blank(), array( sprintf( __( 'Invalid JSON: %s', 'casinos-cc-tools' ), json_last_error_msg() ) ) );
			}
		} else {
			$input = wp_unslash( $_POST['cct'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by CCT_Schema
			$input['schema'] = CCT_SCHEMA_VERSION;
			foreach ( array( 'offers' => 'wagering', 'methods' => 'withdrawal', 'limits' => 'withdrawal' ) as $list => $tool ) {
				$rows = isset( $input[ $tool ][ $list ] ) && is_array( $input[ $tool ][ $list ] ) ? $input[ $tool ][ $list ] : array();
				// drop empty repeater rows and the template row
				$input[ $tool ][ $list ] = array_values( array_filter( $rows, function ( $row, $key ) {
					return '__i__' !== (string) $key && is_array( $row ) && '' !== trim( (string) ( $row['name'] ?? $row['label'] ?? '' ) );
				}, ARRAY_FILTER_USE_BOTH ) );
			}
		}

		$review = $input['casino']['review_post_id'] ?? '';
		if ( ! $review && ! empty( $input['casino']['review_url'] ) ) {
			$review = $input['casino']['review_url'];
		}
		$input['casino']['review_post_id'] = self::resolve_post( $review );

		$check = CCT_Schema::validate( $input );
		if ( $check['errors'] ) {
			self::fail( $profile_id, $check['data'], $check['errors'], $check['warnings'] );
		}
		$other = CCT_Profiles::find_by_slug( $check['data']['casino']['slug'] );
		if ( $other && $other !== $profile_id ) {
			self::fail( $profile_id, $check['data'], array( sprintf( __( 'Another profile already uses the slug "%s".', 'casinos-cc-tools' ), $check['data']['casino']['slug'] ) ), $check['warnings'] );
		}

		$id = CCT_Profiles::save( $check['data'], $profile_id, $status );
		if ( is_wp_error( $id ) ) {
			self::fail( $profile_id, $check['data'], array( $id->get_error_message() ) );
		}

		self::flash( __( 'Profile saved.', 'casinos-cc-tools' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=cct-edit&profile=' . $id ) );
		exit;
	}

	/** Accepts a post ID or a URL on this site. */
	public static function resolve_post( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}
		$id = url_to_postid( esc_url_raw( $value ) );
		return (int) $id;
	}

	private static function fail( $profile_id, $data, $errors, $warnings = array() ) {
		set_transient( 'cct_failed_' . get_current_user_id(), array(
			'profile'  => $profile_id,
			'data'     => $data,
			'errors'   => $errors,
			'warnings' => $warnings,
		), 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=cct-edit' . ( $profile_id ? '&profile=' . $profile_id : '' ) ) );
		exit;
	}

	public static function handle_delete() {
		$id = absint( $_GET['profile'] ?? 0 );
		check_admin_referer( 'cct_delete_' . $id );
		if ( ! current_user_can( 'delete_post', $id ) || CCT_Profiles::POST_TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		wp_delete_post( $id, true );
		self::flash( __( 'Profile deleted.', 'casinos-cc-tools' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public static function handle_export() {
		$id = absint( $_GET['profile'] ?? 0 );
		check_admin_referer( 'cct_export_' . $id );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		$data = CCT_Profiles::get_data( $id );
		if ( ! $data ) {
			wp_die( esc_html__( 'Profile not found.', 'casinos-cc-tools' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $data['casino']['slug'] ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore
		exit;
	}

	/* -------------------------------------------------------------- settings */

	public static function page_settings() {
		$settings = get_option( 'cct_settings', array() );
		$release  = CCT_Updater::get_release();
		?>
		<div class="wrap cct-admin">
			<h1><?php esc_html_e( 'Casino Tools settings', 'casinos-cc-tools' ); ?></h1>
			<?php self::flash(); ?>

			<div class="cct-box cct-box--narrow">
				<h2><?php esc_html_e( 'Plugin updates', 'casinos-cc-tools' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Installed version', 'casinos-cc-tools' ); ?></th><td><strong><?php echo esc_html( CCT_VERSION ); ?></strong></td></tr>
					<tr><th><?php esc_html_e( 'Latest release', 'casinos-cc-tools' ); ?></th><td>
						<?php if ( is_wp_error( $release ) ) : ?>
							<span class="cct-error"><?php echo esc_html( $release->get_error_message() ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $release['url'] ); ?>" target="_blank"><?php echo esc_html( $release['tag'] ); ?></a>
							<?php if ( version_compare( $release['version'], CCT_VERSION, '>' ) ) : ?>
								— <a href="<?php echo esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>"><?php esc_html_e( 'Update now', 'casinos-cc-tools' ); ?></a>
							<?php else : ?>
								— <?php esc_html_e( 'up to date', 'casinos-cc-tools' ); ?>
							<?php endif; ?>
						<?php endif; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Repository', 'casinos-cc-tools' ); ?></th><td><code><?php echo esc_html( CCT_GITHUB_REPO ); ?></code></td></tr>
				</table>
				<p><a class="button button-secondary" href="<?php echo esc_url( CCT_Updater::check_url() ); ?>"><?php esc_html_e( 'Check for updates', 'casinos-cc-tools' ); ?></a></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cct-box cct-box--narrow">
				<input type="hidden" name="action" value="cct_save_settings">
				<?php wp_nonce_field( 'cct_save_settings' ); ?>
				<h2><?php esc_html_e( 'GitHub access', 'casinos-cc-tools' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Needed only if the repository is private. Create a fine-grained token on GitHub with read-only "Contents" access to this repository.', 'casinos-cc-tools' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="cct-token"><?php esc_html_e( 'GitHub token', 'casinos-cc-tools' ); ?></label></th><td>
						<?php if ( defined( 'CCT_GITHUB_TOKEN' ) ) : ?>
							<em><?php esc_html_e( 'Set in wp-config.php (CCT_GITHUB_TOKEN).', 'casinos-cc-tools' ); ?></em>
						<?php else : ?>
							<input type="password" id="cct-token" name="github_token" class="regular-text" autocomplete="off" placeholder="<?php echo ! empty( $settings['github_token'] ) ? esc_attr__( 'Saved. Enter a new token to replace it.', 'casinos-cc-tools' ) : 'github_pat_…'; ?>">
							<?php if ( ! empty( $settings['github_token'] ) ) : ?>
								<label><input type="checkbox" name="clear_token" value="1"> <?php esc_html_e( 'Remove saved token', 'casinos-cc-tools' ); ?></label>
							<?php endif; ?>
						<?php endif; ?>
					</td></tr>
				</table>
				<?php submit_button( __( 'Save settings', 'casinos-cc-tools' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		check_admin_referer( 'cct_save_settings' );
		$settings = get_option( 'cct_settings', array() );
		if ( ! empty( $_POST['clear_token'] ) ) {
			$settings['github_token'] = '';
		} elseif ( ! empty( $_POST['github_token'] ) ) {
			$settings['github_token'] = sanitize_text_field( wp_unslash( $_POST['github_token'] ) );
		}
		update_option( 'cct_settings', $settings, false );
		delete_site_transient( CCT_Updater::CACHE_KEY );
		self::flash( __( 'Settings saved.', 'casinos-cc-tools' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=cct-settings' ) );
		exit;
	}

	/* --------------------------------------------------------------- helpers */

	public static function flash( $msg = null ) {
		$key = 'cct_flash_' . get_current_user_id();
		if ( null !== $msg ) {
			set_transient( $key, $msg, 60 );
			return;
		}
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	public static function messages( $m ) {
		if ( ! empty( $m['errors'] ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Not saved. Fix these first:', 'casinos-cc-tools' ) . '</strong></p><ul class="cct-msgs">';
			foreach ( $m['errors'] as $e ) {
				echo '<li>' . esc_html( $e ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( ! empty( $m['warnings'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Check these:', 'casinos-cc-tools' ) . '</strong></p><ul class="cct-msgs">';
			foreach ( $m['warnings'] as $w ) {
				echo '<li>' . esc_html( $w ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	private static function field_id( $name ) {
		return 'f-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
	}

	private static function text( $name, $label, $value, $o = array() ) {
		$id = self::field_id( $name );
		printf(
			'<p class="cct-field"><label for="%1$s">%2$s</label><input type="%3$s" id="%1$s" name="%4$s" value="%5$s" class="%6$s"%7$s%8$s%9$s>%10$s</p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $o['type'] ?? 'text' ),
			esc_attr( $name ),
			esc_attr( null === $value ? '' : $value ),
			esc_attr( $o['class'] ?? 'regular-text' ),
			isset( $o['step'] ) ? ' step="' . esc_attr( $o['step'] ) . '"' : '',
			isset( $o['maxlength'] ) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '',
			! empty( $o['required'] ) ? ' required' : '',
			isset( $o['help'] ) ? '<span class="description">' . esc_html( $o['help'] ) . '</span>' : ''
		);
	}

	private static function textarea( $name, $label, $value ) {
		$id = self::field_id( $name );
		printf( '<p class="cct-field"><label for="%1$s">%2$s</label><textarea id="%1$s" name="%3$s" rows="2" class="large-text">%4$s</textarea></p>',
			esc_attr( $id ), esc_html( $label ), esc_attr( $name ), esc_textarea( $value ) );
	}

	/**
	 * Editable table of rows. Columns: key => [label, 'text'|'number'|'hidden'|options array, 'wide'?]
	 */
	private static function repeater( $key, $base, $rows, $cols ) {
		$render_row = function ( $i, $row ) use ( $base, $cols ) {
			echo '<tr class="cct-rep__row">';
			$hidden = '';
			foreach ( $cols as $col => $def ) {
				$name  = $base . '[' . $i . '][' . $col . ']';
				$value = isset( $row[ $col ] ) && null !== $row[ $col ] ? $row[ $col ] : '';
				if ( 'hidden' === $def[1] ) {
					$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
					continue;
				}
				echo '<td' . ( ! empty( $def[2] ) ? ' class="cct-rep__wide"' : '' ) . '>';
				if ( is_array( $def[1] ) ) {
					echo '<select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $def[0] ) . '">';
					foreach ( $def[1] as $v => $l ) {
						echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) $value, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
					}
					echo '</select>';
				} else {
					echo '<input type="' . esc_attr( $def[1] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $def[0] ) . '"' . ( 'number' === $def[1] ? ' step="any"' : '' ) . '>';
				}
				echo '</td>';
			}
			echo '<td class="cct-rep__actions">' . $hidden . '<button type="button" class="button-link cct-rep__remove" aria-label="' . esc_attr__( 'Remove row', 'casinos-cc-tools' ) . '">✕</button></td></tr>'; // phpcs:ignore
		};
		?>
		<div class="cct-rep" data-rep="<?php echo esc_attr( $key ); ?>">
			<div class="cct-rep__scroll">
			<table class="widefat cct-rep__table">
				<thead><tr>
					<?php foreach ( $cols as $def ) : if ( 'hidden' === $def[1] ) { continue; } ?><th><?php echo esc_html( $def[0] ); ?></th><?php endforeach; ?>
					<th></th>
				</tr></thead>
				<tbody>
					<?php foreach ( array_values( $rows ) as $i => $row ) { $render_row( $i, $row ); } ?>
				</tbody>
			</table>
			</div>
			<template><?php $render_row( '__i__', array() ); ?></template>
			<p><button type="button" class="button cct-rep__add"><?php esc_html_e( 'Add row', 'casinos-cc-tools' ); ?></button></p>
		</div>
		<?php
	}
}
