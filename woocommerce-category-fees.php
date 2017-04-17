<?php
/**
 * Plugin Name: WooCommerce - Category Fees
 * Plugin URI:  https://filament-studios.com/downloads/woocommerce-category-fees
 * Description: Add Fees to the cart based off the categories of the items purchased
 * Version:     1.1.3
 * Author:      Filament Studios
 * Author URI:  https://filament-studios.com
 * License:     GPL-2.0+
 */

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'WC_CATFEES_STORE_URL', 'https://filament-studios.com' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

// the name of your product. This should match the download name in EDD exactly
define( 'WC_CATFEES_ITEM_NAME', 'WooCommerce Category Fees' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

if ( ! class_exists( 'Filament_Studios_License_Helper' ) ) {
	include( dirname( __FILE__) . '/includes/FS_License_Helper.php' );
}

if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/includes/EDD_SL_Plugin_Updater.php' );
}

if ( ! class_exists( 'WC_Category_Fees' ) ) {

class WC_Category_Fees {

	protected static $_instance = null;

	/**
	 * Start up the plugin
	 *
	 * @since  1.0
	 */
	private function __construct() {

		$this->setup_constants();
		$this->hooks();
		$this->filters();

	}

	/**
	 * Singleton instance
	 *
	 * @since  1.0
	 * @return class The one true instance
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Define some constants befor we get started
	 *
	 * @since  1.0
	 */
	private function setup_constants() {
		// Plugin version
		if ( ! defined( 'WC_CATFEES_VER' ) ) {
			define( 'WC_CATFEES_VER', '1.1.3' );
		}

		// Plugin path
		if ( ! defined( 'WC_CATFEES_DIR' ) ) {
			define( 'WC_CATFEES_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin URL
		if ( ! defined( 'WC_CATFEES_URL' ) ) {
			define( 'WC_CATFEES_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File
		if ( ! defined( 'WC_CATFEES_FILE' ) ) {
			define( 'WC_CATFEES_FILE', __FILE__ );
		}
	}

	/**
	 * Register our hooks
	 *
	 * @since  1.0
	 */
	private function hooks() {

		// Add our form elements to category create/edit
		add_action( 'product_cat_add_form_fields', array( $this, 'add_category_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_fields' ), 99 );

		// Save the fields
		add_action( 'created_term', array( $this, 'save_category_fields' ), 10, 3 );
		add_action( 'edit_term'   , array( $this, 'save_category_fields' ), 10, 3 );

		// Do the work
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_fees' ) );

		add_action( 'admin_init' , array( $this, 'activate_license' ) );
		add_action( 'admin_init' , array( $this, 'deactivate_license' ) );
		add_action( 'admin_init' , array( $this, 'plugin_updater' ), 0 );
		add_action( 'admin_init' , array( $this, 'register_license_key_setting' ), 99 );

	}

	/**
	 * Register into the licensing system
	 *
	 * @since  1.0
	 */
	private function filters() {
		add_filter( 'fs_lh_licenses', array( $this, 'register_license' ), 10, 1 );
	}

	public function register_license( $licenses ) {
		$licenses['wc_cat_fees_license'] = __( 'WooCommerce - Category Fees', 'wc-catfees' );;

		return $licenses;
	}

	/**
	 * Category thumbnail fields.
	 *
	 * @since 1.0
	 */
	public function add_category_fields() {
		?>
		<style>
			#wc-catfees-fees-table { margin: 1em 0 0; }
			#wc-catfees-fees-table .wc-cf-id { width: 45px; }
			#wc-catfees-fees-table .wc-cf-remove { width: 65px; }
			#wc-catfees-fees-table th { padding: 15px 10px; }
			#wc-catfees-fees-table.widefat { width: 350px; }
			.wc-catfees-meta-wrapper { margin-bottom: 5px; }
			.wc-catfees-meta-wrapper label { display: inline; }
		</style>
		<div class="form-field">
			<label for="fees_enabled"><?php _e( 'Apply Fees', 'wc-catfees' ); ?></label>
			<select id="fees_enabled" name="fees_enabled" class="postform">
				<option value=""><?php _e( 'No', 'wc-catfees' ); ?></option>
				<option value="1"><?php _e( 'Yes', 'wc-catfees' ); ?></option>
			</select>
		</div>
		<div class="wc-catfees-meta-wrapper" style="display: none;">
			<label><?php _e( 'Fee Type', 'wc-catfees' ); ?></label><br />
			<input type="radio" id="per_item" name="fee_type" value="per_item" ><label for="per_item"><?php _e( 'Per Item', 'wc-catfees' ); ?></label><br />
			<input type="radio" id="flat_rate" name="fee_type" value="flat_rate" checked="checked" /><label for="flat_rate"><?php _e( 'Flat Rate', 'wc-catfees' ); ?></label>
		</div>
		<div class="wc-catfees-meta-wrapper" style="display: none;">
			<label><?php _e( 'Fee is Taxable', 'wc-catfees' ); ?></label><br />
			<input type="checkbox" id="fee_is_taxable" name="fee_is_taxable" value="1" /><label for="fee_is_taxable"><?php _e( 'Fee is Taxable', 'wc-catfees' ); ?></label>
		</div>
		<div class="wc-catfees-meta-wrapper" style="display: none;">
			<label><?php _e( 'Custom Name', 'wc-catfees' ); ?></label><br />
			<input type="text" id="custom-name" name="custom_name" value="" placeholder="Custom Fee Name" />
			<label for="custom-name"><?php sprintf( __( 'If left blank "%s Fee" will be used on the cart entry.', 'wc-catfees' ), 'CategoryName' ); ?></label>
		</div>
		<div class="wc-catfees-meta-wrapper" style="display: none;">
			<label><?php _e( 'Fee Amount', 'wc-catfees' ); ?></label>
			<table id="wc-catfees-fees-table" class="wp-list-table widefat fixed striped posts" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" class="manage-column wc-cf-id"><?php _e( 'Fee ID', 'wc-catfees' ); ?></th>
						<th scope="col" class="manage-column"><?php _e( 'Amount', 'wc-catfees' ); ?></th>
						<th scope="col" class="manage-column"><?php _e( 'Max Qty', 'wc-catfees' ); ?></th>
						<th class="manage-column wc-cf-remove"></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="manage-column wc-cf-id"><?php _e( 'Fee ID', 'wc-catfees' ); ?></th>
						<th scope="col" class="manage-column"><?php _e( 'Amount', 'wc-catfees' ); ?></th>
						<th scope="col" class="manage-column"><?php _e( 'Max Quantity', 'wc-catfees' ); ?></th>
						<th class="manage-column wc-cf-remove"></th>
					</tr>
				</tfoot>
				<tbody id="wc-cf-fees">
					<?php echo $this->render_fee_row( 0 ); ?>
				</tbody>
			</table>
			<div class="clear"></div>
			<p>
				<span class="button-secondary" id="wc-cf-add-fee"><?php _e( 'Add Fee', 'wc-catfees' ); ?></span>
			</p>
			<p class="description">
				<em><?php _e( 'To charge a single fee for any quantity, create a fee entry and set the max quantity value to 0', 'wc-catfees' ); ?></em><br />
				<em><?php _e( 'Fee percentages are based on the total value of the items in the cart belonging to this category.', 'wc-catfees' ); ?></em>
			</p>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {

				/**
				 * Settings screen JS
				 */
				var WC_Category_Fees_Config = {

					init : function() {
						this.type();
						this.fees();
					},

					type: function() {
						$('#fees_enabled').on('change', function() {
							var value = $(this).val() === '1' ? true : false;
							if ( true === value ) {
								$('.wc-catfees-meta-wrapper').show();
							} else {
								$('.wc-catfees-meta-wrapper').hide();
							}
						});
					},

					fees : function() {

						$('#wc-cf-add-fee').on('click', function() {
							var row = $('#wc-cf-fees tr:last');
							var clone = row.clone();
							var count = row.parent().find( 'tr' ).length;
							clone.find( 'td input' ).val( '' );
							clone.find( 'td input' ).each(function() {
								var name = $( this ).attr( 'name' );
								name = name.replace( /\[(\d+)\]/, '[' + parseInt( count ) + ']');
								$( this ).attr( 'name', name ).attr( 'id', name );
							});
							clone.find('.fee-id').text(count);
							clone.insertAfter( row );
							return false;
						});

						$('body').on('click', '#wc-cf-fees .wc-cf-remove-fee', function() {
							if( confirm( 'Remove this fee?' ) ) {
								var count = $('#wc-cf-fees tr:visible').length;

								if( count === 1 ) {
									$('.wc-cf-fee-row' ).each(function() {
										$(this).find( 'input' ).each(function() {
											$(this).val('');
										});
									});
								} else {
									$(this).closest('tr').remove();

									var rows  = 0;
									$('.wc-cf-fee-row' ).each(function() {

										$(this).find( 'input, select' ).each(function() {
											var name = $( this ).attr( 'name' );
											name = name.replace( /\[(\d+)\]/, '[' + parseInt( rows ) + ']');
											$( this ).attr( 'name', name ).attr( 'id', name );
										});
										$(this).find('.fee-id').text( rows );

										rows++;
									});
								}
							}
							return false;
						});

					},

				}
				WC_Category_Fees_Config.init();
			});
		</script>
		<?php
	}

	/**
	 * Edit category fee fields
	 *
	 * @since  1.0
	 * @param mixed $term Term (category) being edited
	 */
	public function edit_category_fields( $term ) {

		$fees_enabled = get_term_meta( $term->term_id, 'fees_enabled', true );
		$display      = $fees_enabled === '1' ? '' : ' style="display: none;"';
		$fee_type     = get_term_meta( $term->term_id, 'fee_type', true );
		$fee_tax      = get_term_meta( $term->term_id, 'fee_is_taxable', true );
		$custom_name  = get_term_meta( $term->term_id, 'custom_fee_name', true );


		if ( empty( $fee_type ) ) {
			$fee_type = 'flat_rate';
		}
		$fees         = get_term_meta( $term->term_id, 'term_fees', true );
		?>
		<style>
			#wc-catfees-fees-table { margin: 1em 0 0; }
			#wc-catfees-fees-table .wc-cf-id { width: 45px; }
			#wc-catfees-fees-table .wc-cf-remove { width: 65px; }
			#wc-catfees-fees-table th { padding: 15px 10px; }
			#wc-catfees-fees-table.widefat { width: 350px; }
		</style>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Apply Fee', 'wc-catfees' ); ?></label></th>
			<td>
				<select id="fees_enabled" name="fees_enabled" class="postform">
					<option value="" <?php selected( '', $fees_enabled ); ?>><?php _e( 'No', 'wc-catfees' ); ?></option>
					<option value="1" <?php selected( '1', $fees_enabled ); ?>><?php _e( 'Yes', 'wc-catfees' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="wc-catfees-meta-wrapper" <?php echo $display; ?>>
			<th scope="row" valign="top"><label><?php _e( 'Fee Type', 'wc-catfees' ); ?></label></th>
			<td>
				<input type="radio" id="per_item" name="fee_type" value="per_item" <?php checked( 'per_item', $fee_type, true ); ?> /><label for="per_item"><?php _e( 'Per Item', 'wc-catfees' ); ?></label><br />
				<input type="radio" id="flat_rate" name="fee_type" value="flat_rate" <?php checked( 'flat_rate', $fee_type, true ); ?> /><label for="flat_rate"><?php _e( 'Flat Rate', 'wc-catfees' ); ?></label>
			</td>
		</tr>
		<tr class="wc-catfees-meta-wrapper" <?php echo $display; ?>>
			<th scope="row" valign="top"><label><?php _e( 'Is Taxable', 'wc-catfees' ); ?></label></th>
			<td>
				<input type="checkbox" id="fee_is_taxable" name="fee_is_taxable" value="1" <?php checked( '1', $fee_tax, true ); ?> /><label for="fee_is_taxable"><?php _e( 'Fee is Taxable', 'wc-catfees' ); ?></label>
			</td>
		</tr>
		<tr class="wc-catfees-meta-wrapper" <?php echo $display; ?>>
			<th scope="row" valign="top"><label><?php _e( 'Custom Fee Name', 'wc-catfees' ); ?></label></th>
			<td>
				<input type="text" id="custom-name" name="custom_name" value="<?php echo $custom_name; ?>" placeholder="Custom Fee Name" />
				<label for="custom-name"><?php printf( __( 'If left blank "%s Fee" will be used on the cart entry.', 'wc-catfees' ), $term->name ); ?></label>
			</td>
		</tr>
		<tr class="wc-catfees-meta-wrapper" <?php echo $display; ?>>
			<th scope="row" valign="top"><label><?php _e( 'Fee Amount', 'wc-catfees' ); ?></label></th>
			<td>
				<table id="wc-catfees-fees-table" class="wp-list-table widefat fixed striped posts" cellspacing="0">
					<thead>
						<tr>
							<th scope="col" class="manage-column wc-cf-id"><?php _e( 'Fee ID', 'wc-catfees' ); ?></th>
							<th scope="col" class="manage-column"><?php _e( 'Amount', 'wc-catfees' ); ?></th>
							<th scope="col" class="manage-column"><?php _e( 'Max Quantity', 'wc-catfees' ); ?></th>
							<th class="manage-column wc-cf-remove"></th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th scope="col" class="manage-column wc-cf-id"><?php _e( 'Fee ID', 'wc-catfees' ); ?></th>
							<th scope="col" class="manage-column"><?php _e( 'Amount', 'wc-catfees' ); ?></th>
							<th scope="col" class="manage-column"><?php _e( 'Max Quantity', 'wc-catfees' ); ?></th>
							<th class="manage-column wc-cf-remove"></th>
						</tr>
					</tfoot>
					<tbody id="wc-cf-fees">
					<?php if ( ! empty( $fees ) ) : ?>
						<?php foreach ( $fees as $index => $fee ) : ?>
							<?php echo $this->render_fee_row( $index, $fee ); ?>
						<?php endforeach; ?>
					<?php else: ?>
						<?php echo $this->render_fee_row( 0 ); ?>
					<?php endif; ?>
					</tbody>
				</table>
				<div class="clear"></div>
				<p>
					<span class="button-secondary" id="wc-cf-add-fee"><?php _e( 'Add Fee', 'wc-catfees' ); ?></span>
				</p>
				<p class="description">
					<em><?php _e( 'To charge a single fee for any quantity, create a fee entry and set the max quantity value to 0', 'wc-catfees' ); ?></em><br />
					<em><?php _e( 'Fee percentages are based on the total value of the items in the cart belonging to this category.', 'wc-catfees' ); ?></em>
				</p>
			</td>
		</tr>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {

				/**
				 * Settings screen JS
				 */
				var WC_Category_Fees_Config = {

					init : function() {
						this.type();
						this.fees();
					},

					type: function() {
						$('#fees_enabled').on('change', function() {
							var value = $(this).val() === '1' ? true : false;
							if ( true === value ) {
								$('.wc-catfees-meta-wrapper').show();
							} else {
								$('.wc-catfees-meta-wrapper').hide();
							}
						});
					},

					fees : function() {

						$('#wc-cf-add-fee').on('click', function() {
							var row = $('#wc-cf-fees tr:last');
							var clone = row.clone();
							var count = row.parent().find( 'tr' ).length;
							clone.find( 'td input' ).val( '' );
							clone.find( 'td input' ).each(function() {
								var name = $( this ).attr( 'name' );
								name = name.replace( /\[(\d+)\]/, '[' + parseInt( count ) + ']');
								$( this ).attr( 'name', name ).attr( 'id', name );
							});
							clone.find('.fee-id').text(count);
							clone.insertAfter( row );
							return false;
						});

						$('body').on('click', '#wc-cf-fees .wc-cf-remove-fee', function() {
							if( confirm( 'Remove this fee?' ) ) {
								var count = $('#wc-cf-fees tr:visible').length;

								if( count === 1 ) {
									$('.wc-cf-fee-row' ).each(function() {
										$(this).find( 'input' ).each(function() {
											$(this).val('');
										});
									});
								} else {
									$(this).closest('tr').remove();

									var rows  = 0;
									$('.wc-cf-fee-row' ).each(function() {

										$(this).find( 'input, select' ).each(function() {
											var name = $( this ).attr( 'name' );
											name = name.replace( /\[(\d+)\]/, '[' + parseInt( rows ) + ']');
											$( this ).attr( 'name', name ).attr( 'id', name );
										});
										$(this).find('.fee-id').text( rows );

										rows++;
									});
								}
							}
							return false;
						});

					},

				}
				WC_Category_Fees_Config.init();
			});
		</script>
		<?php
	}

	/**
	 * Render a category fee row
	 *
	 * @since  1.0
	 * @param  boolean $index The Index ID of the row, numericly
	 * @param  array   $fee   The Fee information
	 * @return string         The output of the row to render
	 */
	private function render_fee_row( $index = false, $fee = array() ) {
		if ( false === $index ) {
			return '';
		}

		$default_options = array(
			'amount'       => 0.00,
			'percentage'   => 0,
			'quantity_max' => 0,
		);

		$fee = wp_parse_args( $fee, $default_options );

		$currency_symbol = get_woocommerce_currency_symbol();
		$currency_pos    = get_option( 'woocommerce_currency_pos' );

		ob_start();

		?>
		<tr class="wc-cf-fee-row">
			<td><span class="fee-id"><?php echo $index; ?></span></td>
			<td>
				<?php
				if ( 'left' === $currency_pos || 'left_space' === $currency_pos ) {
					echo $currency_symbol;
				}
				?>
				<input class="small-text" placeholder="0.00" type="number" step="0.01" min="0.00" name="term_fees[<?php echo $index; ?>][amount]" value="<?php echo $fee['amount']; ?>" />
				<?php
				if ( 'right' === $currency_pos || 'right_space' === $currency_pos ) {
					echo $currency_symbol;
				}
				?>
				&plus;
				<input class="small-text" placeholder="0.00" type="number" step="0.01" min="0.00" name="term_fees[<?php echo $index; ?>][percentage]" value="<?php echo $fee['percentage']; ?>" />%
			</td>
			<td>
				<input class="small-text" placeholder="0" type="number" step="1" min="0" name="term_fees[<?php echo $index; ?>][max_quantity]" value="<?php echo ! empty( $fee['max_quantity'] ) ? $fee['max_quantity'] : 0; ?>" />
			</td>
			<td><span class="wc-cf-remove-fee button-secondary"><?php _e( 'Remove', 'wc-catfees' ); ?></span></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * save_category_fields function.
	 *
	 * @since  1.0
	 * @param mixed $term_id Term ID being saved
	 */
	public function save_category_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
		if ( isset( $_POST['fees_enabled'] ) && 'product_cat' === $taxonomy ) {
			update_term_meta( $term_id, 'fees_enabled', esc_attr( $_POST['fees_enabled'] ) );
		}

		if ( isset( $_POST['fee_type'] ) && 'product_cat' === $taxonomy ) {
			update_term_meta( $term_id, 'fee_type', esc_attr( $_POST['fee_type'] ) );
		}


		if ( 'product_cat' === $taxonomy ) {
			$is_taxable = isset( $_POST['fee_is_taxable'] ) ? '1' : '0';

			update_term_meta( $term_id, 'fee_is_taxable', $is_taxable );
		}

		if ( isset( $_POST['term_fees'] ) && 'product_cat' === $taxonomy ) {
			if ( is_array( $_POST['term_fees'] ) ) {
				$term_fees = $_POST['term_fees'];
				foreach ( $term_fees as $index => $term_fee ) {
					if ( empty( $term_fee['amount'] ) && empty( $term_fee['percentage'] ) ) {
						unset( $term_fees[ $index ] );
					} else {
						$term_fees[ $index ]['amount']       = round( (float) $term_fees[ $index ]['amount'], 2 );
						$term_fees[ $index ]['percentage']   = round( (float) $term_fees[ $index ]['percentage'], 2 );
						$term_fees[ $index ]['max_quantity'] = intval( $term_fees[ $index ]['max_quantity'] );
						$term_fees[ $index ]['index']        = intval( $index ); // Set here so we can use listpluck to sort
					}
				}

				$max_quantities = wp_list_pluck( $term_fees, 'max_quantity', 'index' );
				asort( $max_quantities );
				$sorted_fees    = array();
				foreach ( $max_quantities as $index => $max_quantity ) {
					$sorted_fees[] = array(
						'amount'       => $term_fees[ $index ]['amount'],
						'percentage'   => $term_fees[ $index ]['percentage'],
						'max_quantity' => $term_fees[ $index ]['max_quantity'],
					);
				}

				update_term_meta( $term_id, 'term_fees', $sorted_fees );
			}
		}

		if ( isset( $_POST['custom_name'] ) && 'product_cat' === $taxonomy ) {
			$custom_fee_name = sanitize_text_field( trim( $_POST['custom_name'] ) );
			update_term_meta( $term_id, 'custom_fee_name', $custom_fee_name );
		}
	}

	/**
	 * Calculate the fees for the WooCommerce Cart
	 *
	 * @since  1.0
	 * @return void Adds fee items to the cart
	 */
	public function calculate_fees() {
		global $woocommerce;

		$category_fees_enabled = array();
		$fee_data              = array();

		foreach ( $woocommerce->cart->cart_contents as $cart_item ) {

			$item_categories = apply_filters( 'wc_cat_fees_item_categories', get_the_terms( $cart_item['product_id'], 'product_cat' ), $cart_item );

			if ( ! empty( $item_categories ) ) {

				foreach ( $item_categories as $category ) {

					if ( ! array_key_exists( $category->term_id, $category_fees_enabled ) ) {
						$fees_enabled = get_term_meta( $category->term_id, 'fees_enabled', true );
						$category_fees_enabled[ $category->term_id ] = ! empty( $fees_enabled ) ? true : false;
					}

					if ( empty( $category_fees_enabled[ $category->term_id ] ) ) {
						continue;
					}

					if ( ! isset( $fee_data[ $category->term_id ] ) ) {
						$fee_type    = get_term_meta( $category->term_id, 'fee_type', true );
						$fees        = get_term_meta( $category->term_id, 'term_fees', true );
						$fee_tax     = get_term_meta( $category->term_id, 'fee_is_taxable', true );
						$custom_name = get_term_meta( $category->term_id, 'custom_fee_name', true );
						$is_taxable  = empty( $fee_tax ) ? false : true;

						$fee_data[ $category->term_id ] = array(
							'cat_id'        => $category->term_id,
							'name'          => $category->name,
							'display_name'  => ! empty( $custom_name ) ? $custom_name : sprintf( __( '%s Fee', 'wc-catfees' ), $category->name ),
							'fee_type'      => $fee_type,
							'is_taxable'    => $is_taxable,
							'fees'          => $fees,
						);
					}

					if ( empty( $fee_data[ $category->term_id ]['quantity'] ) ) {
						$fee_data[ $category->term_id ]['quantity'] = $cart_item['quantity'];
					} else {
						$fee_data[ $category->term_id ]['quantity'] += $cart_item['quantity'];
					}

					if ( empty( $fee_data[ $category->term_id ]['items_total'] ) ) {
						$fee_data[ $category->term_id ]['items_total'] = $cart_item['line_total'];
					} else {
						$fee_data[ $category->term_id ]['items_total'] += $cart_item['line_total'];
					}
				}
			}
		}

		$display_names = wp_list_pluck( $fee_data, 'display_name' );
		$display_names = array_count_values( $display_names );

		if ( ! empty( $fee_data ) ) {

			foreach ( $fee_data as $fee_item ) {

				$fee_amount = 0.00;
				if ( $this->is_single_rate_fee( $fee_item['fees'] ) && $fee_item['quantity'] > 0 ) {
					$fee_amount     = $fee_item['fees'][0]['amount'];
					$fee_percentage = $fee_item['fees'][0]['percentage'];
				} else {
					foreach ( $fee_item['fees'] as $index => $fee ) {
						if ( $fee_item['quantity'] <= $fee['max_quantity'] ) {
							$fee_amount     = $fee['amount'];
							$fee_percentage = $fee['percentage'];
							break;
						}
					}
				}

				if ( $fee_item['fee_type'] == 'per_item') {
					$fee_amount = $fee_amount * $fee_item['quantity'];
				}

				if ( ! empty( $fee_percentage ) ) {
					$fee_amount += $fee_item['items_total'] * ( $fee_percentage / 100 );
				}

				// Round to avoid errors
				$fee_amount = apply_filters( 'wc_cat_fees_amount', round( $fee_amount, 2 ), $fee_item );

				if ( isset( $display_names[ $fee_item['display_name'] ] ) && $display_names[ $fee_item['display_name'] ] > 1 ) {
					$display_name = $fee_item['display_name'] . ' (' . $fee_item['name'] . ')';
				} else {
					$display_name = $fee_item['display_name'];
				}

				if ( $fee_amount > 0 ) {
					$woocommerce->cart->add_fee( $display_name, $fee_amount, $fee_item['is_taxable'], '' );
				}

			}
		}

	}

	/**
	 * Helper function to determine if a category has a single fee row
	 *
	 * @since  1.0
	 * @param  array  $fees The category's fees
	 * @return boolean      If the category has a single fee, and if it has no max_quantity
	 */
	private function is_single_rate_fee( $fees = array() ) {
		if ( 1 === count( $fees ) && $fees[0]['max_quantity'] === 0 ) {
			return true;
		}

		return false;
	}


	public function activate_license() {

		// listen for our activate button to be clicked
		if( isset( $_POST['edd_license_activate'] ) ) {

			// run a quick security check
		 	if( ! check_admin_referer( 'fs_license_nonce', 'wc_cat_fees_license_nonce' ) )
				return; // get out if we didn't click the Activate button

			// retrieve the license from the database
			$license = trim( get_option( 'wc_cat_fees_license' ) );


			// data to send in our API request
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( WC_CATFEES_ITEM_NAME ), // the name of our product in EDD
				'url'        => home_url()
			);

			// Call the custom API.
			$response = wp_remote_post( WC_CATFEES_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "valid" or "invalid"

			update_option( 'wc_cat_fees_license_status', $license_data->license );

		}
	}

	public function deactivate_license() {

		// listen for our activate button to be clicked
		if( isset( $_POST['edd_license_deactivate'] ) ) {

			// run a quick security check
		 	if( ! check_admin_referer( 'fs_license_nonce', 'wc_cat_fees_license_nonce' ) )
				return; // get out if we didn't click the Activate button

			// retrieve the license from the database
			$license = trim( get_option( 'wc_cat_fees_license' ) );


			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => urlencode( WC_CATFEES_ITEM_NAME ), // the name of our product in EDD
				'url'        => home_url()
			);

			// Call the custom API.
			$response = wp_remote_post( WC_CATFEES_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed"
			if( $license_data->license == 'deactivated' )
				delete_option( 'wc_cat_fees_license_status' );

		}
	}

	function plugin_updater() {

		// retrieve our license key from the DB
		$license_key = trim( get_option( 'wc_cat_fees_license' ) );

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater( WC_CATFEES_STORE_URL, __FILE__, array(
				'version'   => WC_CATFEES_VER,
				'license'   => $license_key,
				'item_name' => WC_CATFEES_ITEM_NAME,
				'author'    => 'Filament Studios'
			)
		);

	}

	function register_license_key_setting() {
		register_setting( 'fs_license_helper', 'wc_cat_fees_license', array( $this, 'sanitize_license' ) );
	}

	public function sanitize_license( $new ) {
		$old = get_option( 'wc_cat_fees_license' );
		if( $old && $old != $new ) {
			delete_option( 'wc_cat_fees_license' ); // new license has been entered, so must reactivate
		}
		return $new;
	}


}


}


/**
 * Returns the main instance of WC to prevent the need to use globals.
 *
 * @since  2.1
 * @return WooCommerce
 */
function WC_Category_Fees() {
	return WC_Category_Fees::instance();
}

// Global for backwards compatibility.
$GLOBALS['wc_category_fees'] = WC_Category_Fees();
