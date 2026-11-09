<?php
/**
 * JSON import in two steps: validate and preview, then import.
 *
 * Accepts one profile object, an array of profiles, or { "profiles": [ … ] }.
 * Profiles are matched to existing ones by casino.slug, so re-importing updates rather than
 * duplicates. Every update creates a revision, so an import can be rolled back per casino.
 */

defined( 'ABSPATH' ) || exit;

class CCT_Import {

	public static function init() {
		add_action( 'admin_post_cct_import_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_cct_import_commit', array( __CLASS__, 'handle_commit' ) );
	}

	private static function key( $token ) {
		return 'cct_import_' . get_current_user_id() . '_' . preg_replace( '/[^a-z0-9]/', '', $token );
	}

	public static function page() {
		$token   = isset( $_GET['token'] ) ? sanitize_key( $_GET['token'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$preview = $token ? get_transient( self::key( $token ) ) : false;
		$error   = get_transient( 'cct_import_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'cct_import_error_' . get_current_user_id() );
		}
		?>
		<div class="wrap cct-admin">
			<h1><?php esc_html_e( 'Import JSON', 'casinos-cc-tools' ); ?></h1>
			<?php CCT_Admin::flash(); ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<?php if ( $preview ) : self::preview_table( $preview, $token ); else : ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cct-box cct-box--narrow">
				<input type="hidden" name="action" value="cct_import_preview">
				<?php wp_nonce_field( 'cct_import_preview' ); ?>
				<p><?php esc_html_e( 'Upload or paste one casino profile, or several. Nothing is saved until you confirm on the next screen.', 'casinos-cc-tools' ); ?></p>
				<p class="cct-field"><label for="cct-file"><?php esc_html_e( 'JSON file', 'casinos-cc-tools' ); ?></label><input type="file" id="cct-file" name="file" accept=".json,application/json"></p>
				<p class="cct-field"><label for="cct-paste"><?php esc_html_e( 'Or paste JSON', 'casinos-cc-tools' ); ?></label><textarea id="cct-paste" name="json" rows="14" class="large-text code" spellcheck="false" placeholder='{ "schema": 1, "casino": { "name": "…", "slug": "…" }, … }'></textarea></p>
				<?php submit_button( __( 'Check JSON', 'casinos-cc-tools' ), 'primary', 'submit', false ); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function preview_table( $preview, $token ) {
		$ok = count( array_filter( $preview, function ( $p ) { return ! $p['errors']; } ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cct_import_commit">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'cct_import_commit' ); ?>
			<table class="widefat striped cct-list">
				<thead><tr>
					<th><?php esc_html_e( 'Casino', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Action', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Review page', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Contents', 'casinos-cc-tools' ); ?></th>
					<th><?php esc_html_e( 'Checks', 'casinos-cc-tools' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $preview as $p ) : $d = $p['data']; ?>
					<tr>
						<td><strong><?php echo esc_html( $d['casino']['name'] ?: '—' ); ?></strong><br><code><?php echo esc_html( $d['casino']['slug'] ); ?></code></td>
						<td><?php
						if ( $p['errors'] ) {
							echo '<span class="cct-pill cct-pill--err">' . esc_html__( 'Skipped', 'casinos-cc-tools' ) . '</span>';
						} elseif ( $p['existing'] ) {
							echo '<span class="cct-pill cct-pill--on">' . esc_html__( 'Update', 'casinos-cc-tools' ) . '</span>';
						} else {
							echo '<span class="cct-pill cct-pill--new">' . esc_html__( 'Create', 'casinos-cc-tools' ) . '</span>';
						}
						?></td>
						<td><?php
						$rid = (int) $d['casino']['review_post_id'];
						echo $rid && get_post( $rid ) ? esc_html( get_the_title( $rid ) ) : '<span class="cct-muted">' . esc_html__( 'not linked', 'casinos-cc-tools' ) . '</span>';
						?></td>
						<td><?php
						echo esc_html( sprintf(
							/* translators: 1: offers, 2: methods, 3: limits */
							__( '%1$d offers · %2$d methods · %3$d limit levels', 'casinos-cc-tools' ),
							count( $d['wagering']['offers'] ), count( $d['withdrawal']['methods'] ), count( $d['withdrawal']['limits'] )
						) );
						?></td>
						<td>
							<?php if ( ! $p['errors'] && ! $p['warnings'] ) : ?><span class="cct-ok">✓ <?php esc_html_e( 'All good', 'casinos-cc-tools' ); ?></span><?php endif; ?>
							<?php if ( $p['errors'] ) : ?><ul class="cct-msgs cct-msgs--err"><?php foreach ( $p['errors'] as $e ) { echo '<li>' . esc_html( $e ) . '</li>'; } ?></ul><?php endif; ?>
							<?php if ( $p['warnings'] ) : ?><ul class="cct-msgs cct-msgs--warn"><?php foreach ( $p['warnings'] as $w ) { echo '<li>' . esc_html( $w ) . '</li>'; } ?></ul><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $ok ) : ?>
			<p class="description"><?php esc_html_e( 'Imported profiles are saved as Live, so their tools show on the site straight away. Tools switched off in the JSON stay off.', 'casinos-cc-tools' ); ?></p>
			<p>
				<?php submit_button( sprintf( _n( 'Import %d profile', 'Import %d profiles', $ok, 'casinos-cc-tools' ), $ok ), 'primary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cct-import' ) ); ?>"><?php esc_html_e( 'Cancel', 'casinos-cc-tools' ); ?></a>
			</p>
			<?php else : ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cct-import' ) ); ?>"><?php esc_html_e( 'Back', 'casinos-cc-tools' ); ?></a></p>
			<?php endif; ?>
		</form>
		<?php
	}

	public static function handle_preview() {
		if ( ! current_user_can( CCT_Admin::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		check_admin_referer( 'cct_import_preview' );

		$raw = '';
		if ( ! empty( $_FILES['file']['tmp_name'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
			$raw = file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore
		} elseif ( ! empty( $_POST['json'] ) ) {
			$raw = wp_unslash( $_POST['json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below
		}
		$raw   = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $raw ); // strip BOM
		$input = json_decode( $raw, true );

		if ( ! is_array( $input ) ) {
			set_transient( 'cct_import_error_' . get_current_user_id(), $raw ? sprintf( __( 'Invalid JSON: %s', 'casinos-cc-tools' ), json_last_error_msg() ) : __( 'Choose a file or paste JSON.', 'casinos-cc-tools' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=cct-import' ) );
			exit;
		}

		if ( isset( $input['profiles'] ) && is_array( $input['profiles'] ) ) {
			$items = $input['profiles'];
		} elseif ( isset( $input['casino'] ) ) {
			$items = array( $input );
		} else {
			$items = array_values( $input );
		}

		$preview = array();
		$seen    = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item['casino']['review_post_id'] ) ) {
				$item['casino']['review_post_id'] = CCT_Admin::resolve_post( $item['casino']['review_post_id'] );
			} elseif ( is_array( $item ) && ! empty( $item['casino']['review_url'] ) ) {
				$item['casino']['review_post_id'] = CCT_Admin::resolve_post( $item['casino']['review_url'] );
			}
			$check = CCT_Schema::validate( $item );
			$slug  = $check['data']['casino']['slug'];
			if ( $slug && isset( $seen[ $slug ] ) ) {
				$check['errors'][] = __( 'The same slug appears twice in this file.', 'casinos-cc-tools' );
			}
			$seen[ $slug ] = true;
			$preview[]     = array(
				'data'     => $check['data'],
				'errors'   => $check['errors'],
				'warnings' => $check['warnings'],
				'existing' => $slug ? CCT_Profiles::find_by_slug( $slug ) : 0,
			);
		}

		$token = strtolower( wp_generate_password( 12, false ) );
		set_transient( self::key( $token ), $preview, HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=cct-import&token=' . $token ) );
		exit;
	}

	public static function handle_commit() {
		if ( ! current_user_can( CCT_Admin::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'casinos-cc-tools' ) );
		}
		check_admin_referer( 'cct_import_commit' );

		$token   = sanitize_key( $_POST['token'] ?? '' );
		$preview = get_transient( self::key( $token ) );
		if ( ! $preview ) {
			set_transient( 'cct_import_error_' . get_current_user_id(), __( 'The preview expired. Upload the file again.', 'casinos-cc-tools' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=cct-import' ) );
			exit;
		}
		delete_transient( self::key( $token ) );

		// Imports always go live. Use the profile's Status field to hide a casino later.
		$done = 0;
		foreach ( $preview as $p ) {
			if ( $p['errors'] ) {
				continue;
			}
			$existing = CCT_Profiles::find_by_slug( $p['data']['casino']['slug'] );
			$id       = CCT_Profiles::save( $p['data'], $existing, 'publish' );
			if ( ! is_wp_error( $id ) ) {
				$done++;
			}
		}

		CCT_Admin::flash( sprintf( _n( '%d profile imported and live.', '%d profiles imported and live.', $done, 'casinos-cc-tools' ), $done ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . CCT_Admin::SLUG ) );
		exit;
	}
}
