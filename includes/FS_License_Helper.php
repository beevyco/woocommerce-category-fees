<?php


class Filament_Studios_License_Helper {

	protected static $_instance = null;

	private function __construct() {

		$this->hooks();
	}

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ), 1 );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
	}

	public function register_settings_page() {
		add_options_page( 'Filament Studios Helper', 'Filament Studios Licenses', 'administrator', 'fs-license-helper', array( $this, 'render_license' ) );
	}

	public function register_settings() {
		add_settings_section( 'fs_license_helper', 'License Keys', array( $this, 'render_license_fields' ), 'fs-license-helper' );
	}

	public function render_license() {
		?>
		<div class="wrap">
			<h2><?php _e( 'Filament Studios - Licenses' ); ?></h2>
			<form method="post" action="options.php">
		<?php
			do_settings_sections( 'fs-license-helper' );
			submit_button();
		?>
			</form>
		</div>
		<?php
	}

	public function render_license_fields() {
		$registered_licenses = $this->get_license_ids();
		foreach ( $registered_licenses as $id => $name ) {
			$license = get_option( $id );
			$status  = get_option( $id . '_status' );
			echo '<label for="' . $id . '">' . $name . '</label> <input size="50" id="'. $id . '" name="' . $id . '" value="' . $license . '" placeholder="' . __( 'Enter License Key' ) . '" />';
			if( ! empty( $license ) ) {
				if( $status !== false && $status == 'valid' ) { ?>
					<span style="color:green;"><?php _e('active'); ?></span>
					<?php wp_nonce_field( 'fs_license_nonce', $id . '_nonce' ); ?>
					<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
				<?php } else {
					wp_nonce_field( 'fs_license_nonce', $id . '_nonce' ); ?>
					<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
				<?php }
			}
		}

		settings_fields( 'fs_license_helper' );

	}

	private function get_license_ids() {
		$licenses = apply_filters( 'fs_lh_licenses', array() );

		return $licenses;
	}

}


function load_fs_license_helper() {
	return Filament_Studios_License_Helper::instance();
}
add_action( 'plugins_loaded', 'load_fs_license_helper', 1 );
