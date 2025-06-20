<?php

namespace Deposits_WooCommerce;

use Deposits_WooCommerce\Modules\DeleteCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Product {

	/**
	 * @var mixed
	 */
	protected $background_dc;
	/**
	 * The unique instance of the plugin.
	 */
	private static $instance;

	/**
	 * Gets an instance of our plugin.
	 *
	 * @return Class Instance.
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->background_dc = new DeleteCache();
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'before_add_to_cart' ), 10 );
		add_action( 'woocommerce_product_data_tabs', array( $this, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'options_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_metadata' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variations_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'variations_fields_save' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'deposit_icon' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_item_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_frontend_input' ), 10, 3 );
		add_action( 'save_post', array( $this, 'product_variation_clear_transient_data' ) );
	}
	/**
	 * Clears the transient data for a product variation.
	 *
	 * This function checks if the given ID belongs to a product post type.
	 * If it does, it constructs the cache key for the product variation and checks if the transient data exists.
	 * If the site is a multisite installation, it appends the current site ID to the cache key.
	 * If the transient data exists, it deletes the transient data.
	 *
	 * @param int $id The ID of the product variation.
	 * @return void
	 */
	public function product_variation_clear_transient_data( $id ) {
		if ( 'product' === get_post_type( $id ) ) {
			$product_variation_cache = 'bayna_product_variation_' . $id;
			if ( is_multisite() ) {
				$product_variation_cache = 'site_' . get_current_blog_id() . $product_variation_cache;
				if ( get_site_transient( $product_variation_cache ) ) {
					delete_site_transient( $product_variation_cache );
				}
			} elseif ( get_transient( $product_variation_cache ) ) {
				delete_transient( $product_variation_cache );
			}
		}
	}
	/**
	 * Delete all 'bayna_product_variation' transients from the database.
	 */
	public static function delete_transients() {
		$pf = new self();
		$pf->delete_transients_with_prefix( 'bayna_product_variation_' );
	}
	/**
	 * Delete all transients from the database whose keys have a specific prefix.
	 *
	 * @param string $prefix The prefix. Example: 'my_cool_transient_'.
	 */
	public function delete_transients_with_prefix( $prefix ) {

		// Process the product IDs in batches
		$batch_size   = 30; // Number of products in each batch
		$keys_batches = array_chunk( $this->get_transient_keys_with_prefix( $prefix ), $batch_size );
		foreach ( $keys_batches as $batch ) {
			$this->background_dc->push_to_queue( $batch );
		}
		// Lets dispatch the queue to start processing.
		$this->background_dc->save()->dispatch();
	}
	/**
	 * Gets all transient keys in the database with a specific prefix.
	 *
	 * Note that this doesn't work for sites that use a persistent object
	 * cache, since in that case, transients are stored in memory.
	 *
	 * @param  string $prefix Prefix to search for.
	 * @return array          Transient keys with prefix, or empty array on error.
	 */
	private function get_transient_keys_with_prefix( $prefix ) {
		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_' . $prefix );
		$sql    = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%s'";
		$keys   = $wpdb->get_results( $wpdb->prepare( $sql, $prefix . '%' ), ARRAY_A );

		if ( is_wp_error( $keys ) ) {
			return array();
		}

		return array_map(
			function ( $key ) {
				// Remove '_transient_' from the option name.
				return substr( $key['option_name'], strlen( '_transient_' ) );
			},
			$keys
		);
	}
	/**
	 * Custom set transient
	 *
	 * @param  mixed $key Key.
	 * @param  mixed $data Data.
	 * @param  mixed $time Time.
	 * @return void
	 */
	public function set_cache( $key, $data, $time ) {
		if ( ! is_admin() ) {
			if ( is_multisite() ) {
				set_site_transient( 'site_' . get_current_blog_id() . $key, $data, $time );
			} else {
				set_transient( $key, $data, $time );
			}
		}
	}

		/**
		 * Retrieves the cached data from the transient storage.
		 *
		 * @param string $key The key used to store the cached data.
		 * @return mixed The cached data retrieved from the transient storage.
		 */
	public function get_cache( $key ) {
		$cached_data = '';
		if ( is_multisite() ) {
			$cached_data = get_site_transient( 'site_' . get_current_blog_id() . $key );
		} else {
			$cached_data = get_transient( $key );
		}
		return $cached_data;
	}
	/**
	 * add item data based on deposit option
	 *
	 * @param  array $cart_item_data
	 * @param  int   $product_id
	 * @return cart_item_data
	 */
	public function add_item_data( $cart_item_data, $product_id, $variation_id ) {

		if ( Bootstrap::required_login_for_deposit() ) {
			return $cart_item_data;
		}
		$vProductId = ( $variation_id ) ? $variation_id : $product_id;

		if ( cidw_get_option( 'global_deposits_mode' ) == 1 && ! empty( cidw_get_option( 'global_deposits_value' ) ) ) {
			$deposit_value = apply_filters( 'deposits_value', get_post_meta( $vProductId, '_deposits_value', true ) );

			$product = wc_get_product( $vProductId );
			$value   = ( $deposit_value / 100 ) * $product->get_price();

			$cart_item_data['_deposit']      = $value;
			$cart_item_data['_due_payment']  = $product->get_price() - $value;
			$cart_item_data['_deposit_mode'] = 'check_deposit';
		} else {
			$deposit_checked = WC()->session->get( 'deposit_checked' );

			if ( 'yes' == $deposit_checked ) {
				$product = wc_get_product( $vProductId );

				$deposit_value = get_post_meta( $vProductId, '_deposits_value', true );

				if ( get_post_meta( $vProductId, '_deposits_type', true ) == 'percent' ) {
					$deposit_value = get_post_meta( $vProductId, '_deposits_value', true );
					$deposit_value = ( $deposit_value / 100 ) * $product->get_price();
				}

				$cart_item_data['_deposit']      = $deposit_value;
				$cart_item_data['_due_payment']  = $product->get_price() - $deposit_value;
				$cart_item_data['_deposit_mode'] = 'check_deposit';
			}
		}

		return $cart_item_data;
	}

	/**
	 * enqueue scripts and styles
	 */
	public function scripts() {
		wp_register_script( 'dfwc-public', CIDW_DEPOSITS_ASSETS . '/js/public.js', array( 'jquery' ), CIDW_DEPOSITS_VERSION, true );

		// run only product page
		wp_enqueue_style( 'pretty-checkbox', CIDW_DEPOSITS_ASSETS . '/css/pretty-checkbox.min.css', array(), CIDW_DEPOSITS_VERSION );
		$activeColor = cidw_get_option( 'cidw_box_active_color' );

		$custom_css = "

            .pretty.p-default input:checked~.state label:after,
            .pretty.p-default:not(.p-fill) input:checked~.state.p-primary-o label:after
            {
                            background: {$activeColor} !important;
            }
            .pretty input:checked~.state.p-primary-o label:before, .pretty.p-toggle .state.p-primary-o label:before {
                border-color: {$activeColor} !important;
            }";

		wp_add_inline_style( 'pretty-checkbox', $custom_css );

		$params = array(
			'ajax_url'         => admin_url( 'admin-ajax.php', 'relative' ),
			'ajax_nonce'       => wp_create_nonce( 'deposits_nonce' ),
			'variation_markup' => $this->get_variaton_markup( get_the_ID() ),
			'product_id'       => get_the_ID(),

		);
		wp_localize_script( 'dfwc-public', 'deposits_params', $params );

		wp_enqueue_script( 'dfwc-public' );
	}
	/**
	 * @param $product_id
	 * @return mixed
	 */
	public function get_variaton_markup( $product_id ) {

		if ( ! wc_get_product( $product_id ) ) {
			return;
		}

		$variation_cache_data = $this->get_cache( 'bayna_product_variation_' . $product_id );
		$data                 = $this->get_variation_data( $product_id );

		if ( $variation_cache_data ) {

			return $variation_cache_data;
		} elseif ( $data ) {

			$this->set_cache( 'bayna_product_variation_' . $product_id, $data, apply_filters( 'bayna_clear_variation_cache', DAY_IN_SECONDS * 7 ) );
			return $data;

		}
	}
	/**
	 * Retrieves the variation data for a given product.
	 *
	 * @param int $product_id The ID of the product.
	 * @return array|bool An array of variation images or false if the product is not a variable product.
	 */
	public function get_variation_data( $product_id ) {

		// get all product variations
		$product_variations = new \WC_Product_Variable( $product_id );

		$variations = $product_variations->get_available_variations();

		$deposit_variation_ids = array();

		$product = wc_get_product( $product_id );

		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		foreach ( $variations as $variation ) {
			if ( ( cidw_get_option( 'global_deposits_mode' ) == 1 && ! empty( cidw_get_option( 'global_deposits_value' ) ) ) ) {
				$deposit_variation_ids[ $variation['variation_id'] ] = $this->html_markup( $variation['variation_id'], $product_id );
			} elseif ( cidw_is_product_type_deposit( $variation['variation_id'] ) ) {
				$deposit_variation_ids[ $variation['variation_id'] ] = $this->html_markup( $variation['variation_id'], $product_id );
			}
		}

		return $deposit_variation_ids;
	}
	/**
	 * @param $id
	 */
	public function html_markup( $variation_id, $product_id ) {

		ob_start();

		$vProduct = wc_get_product( $variation_id );

		if ( cidw_get_option( 'global_deposits_mode' ) == 1 && ! empty( cidw_get_option( 'global_deposits_value' ) ) ) {

			$deposit_value = apply_filters( 'deposits_value', 0 );
			$deposit_type  = apply_filters( 'deposits_type', '' );

			$product_deposit_value         = $deposit_value;
			$product_deposit_type          = $deposit_type;
			$product_force_deposit_checked = ( cidw_get_option( 'global_force_deposit' ) == 1 ) ? 'yes' : 'no';
			$product_enable_deposit        = ( get_post_meta( $variation_id, '_enable_deposit', true ) == 'yes' ) ? true : false;

		} elseif ( cidw_is_product_type_deposit( $variation_id ) ) {

			$product_deposit_value         = get_post_meta( $variation_id, '_deposits_value', true );
			$product_deposit_type          = get_post_meta( $variation_id, '_deposits_type', true );
			$product_force_deposit_checked = apply_filters( 'deposits_force_check', get_post_meta( $variation_id, '_force_deposit_checked', true ) );

			$product_enable_deposit = ( get_post_meta( $variation_id, '_enable_deposit', true ) == 'yes' ) ? true : false;

		}

		$deposit_type = ( $product_enable_deposit ) ? $product_deposit_type : apply_filters( 'deposits_type', '' );

		$deposit_value = ( $product_deposit_value && $product_enable_deposit ) ? $product_deposit_value : apply_filters( 'deposits_value', 0 );

		if ( 'percent' == $deposit_type ) {
			$deposit_value  = ( $deposit_value / 100 ) * $vProduct->get_price();
			$deposit_amount = $deposit_value;
		} else {
			$deposit_amount = $deposit_value;
		}

		$deposit_text = apply_filters( 'single_product_deposits_notice', cidw_get_option( 'txt_deposit_msg', 'Deposit : %s Per item' ), wc_price( $deposit_amount ) );?>
			<div class="deposits-frontend-wrapper" style="display:block">
			<p class="deposit-notice"><?php echo $deposit_text; ?></p>
			<div class="deposits-input-wrapper">
			<?php
			if ( apply_filters( 'frontend_deposits_radio_input', true ) ) {

				if ( 'yes' != $product_force_deposit_checked ) {
					?>

			<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
				<input type="radio" name="deposit-mode" value="check_full" <?php echo ( cidw_get_option( 'predefine_full_payment' ) == 1 ) ? 'checked' : ''; ?>>
				<div class="state p-primary-o">
						<?php
						if ( cidw_get_option( 'cidw_radio_theme' ) == 'p-image p-plain' ) {
							echo '<img class="image" src="' . cidw_get_option( 'cidw_radio_theme_image' ) . '">';
						}
						?>

					<label><?php echo esc_html( cidw_get_option( 'txt_full_payment', 'Full Payment' ) ); ?></label>
				</div>
			</div>

					<?php
				}
				?>
				<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
					<input type="radio" name="deposit-mode" value="check_deposit" <?php echo ( $deposit_value || isset( $_POST['deposit-mode'] ) && 'check_deposit' == $_POST['deposit-mode'] || ! isset( $_POST['deposit-mode'] ) || get_post_meta( $variation_id, '_force_deposit_checked', true ) == 'yes' ) ? ( cidw_get_option( 'predefine_full_payment' ) != 1 ) ? 'checked' : '' : ''; ?>>
					<div class="state p-primary-o">
						<?php
						if ( cidw_get_option( 'cidw_radio_theme' ) == 'p-image p-plain' ) {
							echo '<img class="image" src="' . cidw_get_option( 'cidw_radio_theme_image' ) . '">';
						}
						?>

						<label><?php echo esc_html( cidw_get_option( 'txt_pay_deposit', 'Pay Deposit' ) ); ?></label>
					</div>
				</div>
				<?php
			} else {
				echo '<input type="hidden" name="deposit-mode" value="check_deposit">';
			}
			?>
			
			</div>

			<span style="margin-bottom:15px;display: block;"></span>
		</div>
		<?php

			return ob_get_clean();
	}
	/**
	 * vaildate desposit input
	 *
	 * @param  [boolen] $passed
	 * @param  [int]    $product_id
	 * @param  [int]    $quantity
	 * @return boolen
	 */
	public function validate_frontend_input( $passed, $product_id, $quantity ) {

		if ( ! WC()->cart->is_empty() && cidw_cart_have_deposit_item() && apply_filters( 'deposits_mode', true ) ) {

			if ( isset( $_REQUEST['deposit-mode'] ) && 'check_deposit' == $_REQUEST['deposit-mode'] ) {
				return $passed;
			}
			$passed = false;
			wc_add_notice( cidw_get_option( 'deposit_notice' ), 'error' );
			return $passed;
		}

		if ( ! WC()->cart->is_empty() && cidw_cart_have_deposit_item() == false && apply_filters( 'deposits_mode', true ) ) {

			if ( isset( $_REQUEST['deposit-mode'] ) && 'check_full' == $_REQUEST['deposit-mode'] ) {
				return $passed;
			} elseif ( ! isset( $_REQUEST['deposit-mode'] ) ) {
				return $passed;
			}
			$passed = false;
			wc_add_notice( cidw_get_option( 'regular_notice' ), 'error' );
			return $passed;
		}

		return $passed;
	}

	/**
	 * Add elements before add to cart form
	 * on the single product page
	 */
	public function before_add_to_cart() {
		if ( Bootstrap::required_login_for_deposit() ) {
			return;
		}
		$product                       = wc_get_product( get_the_ID() );
		$product_force_deposit_checked = apply_filters( 'deposits_force_check', get_post_meta( get_the_id(), '_force_deposit_checked', true ) );
		if ( cidw_is_product_type_deposit( get_the_ID() ) ) {
			$deposit_type  = apply_filters( 'deposits_type', get_post_meta( get_the_ID(), '_deposits_type', true ) );
			$deposit_value = apply_filters( 'deposits_value', get_post_meta( get_the_ID(), '_deposits_value', true ) );
			if ( 'percent' == $deposit_type ) {
				$deposit_value  = ( $deposit_value / 100 ) * $product->get_price();
				$deposit_amount = $deposit_value;
			} else {
				$deposit_amount = $deposit_value;
			}

			$deposit_text = apply_filters( 'single_product_deposits_notice', cidw_get_option( 'txt_deposit_msg', 'Deposit : %s Per item' ), wc_price( $deposit_amount ) );
			?>
			<div class="deposits-frontend-wrapper">
			<p class="deposit-notice"><?php echo $deposit_text; ?></p>
			<div class="deposits-input-wrapper">
				<?php
				if ( $product_force_deposit_checked != 'yes' ) {
					?>

				<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
					<input type="radio" name="deposit-mode" value="check_full" <?php echo ( cidw_get_option( 'predefine_full_payment' ) == 1 ) ? 'checked' : ''; ?>>
					<div class="state p-primary-o">
						<?php
						if ( cidw_get_option( 'cidw_radio_theme' ) == 'p-image p-plain' ) {
							echo '<img class="image" src="' . cidw_get_option( 'cidw_radio_theme_image' ) . '">';
						}
						?>

						<label><?php echo esc_html( cidw_get_option( 'txt_full_payment', 'Full Payment' ) ); ?></label>
					</div>
				</div>
			
					<?php
				}
				?>
				

				<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
					<input type="radio" name="deposit-mode" value="check_deposit" <?php echo ( cidw_get_option( 'predefine_full_payment' ) != 1 && ( $deposit_value || isset( $_POST['deposit-mode'] ) && 'check_deposit' == $_POST['deposit-mode'] || ! isset( $_POST['deposit-mode'] ) ) ) ? 'checked' : ''; ?>>
					<div class="state p-primary-o">
						<?php
						if ( cidw_get_option( 'cidw_radio_theme' ) == 'p-image p-plain' ) {
							echo '<img class="image" src="' . cidw_get_option( 'cidw_radio_theme_image' ) . '">';
						}
						?>

						<label><?php echo esc_html( cidw_get_option( 'txt_pay_deposit', 'Pay Deposit' ) ); ?></label>
					</div>
				</div>
			</div>

			<span style="margin-bottom:15px;display: block;"></span>
			</div>


			<?php
		}
	}
	/**
	 * Add deposits icon
	 */
	public function deposit_icon() {
		echo '<style>
        #woocommerce-product-data ul li.deposits_options.deposits_tab a:before{
            content:"\f184";
        }
        </style>';
	}
	/**
	 * Following code Saves  WooCommerce Product deposits Custom Fields
	 */
	public function save_product_metadata( $post_id ) {

		$field_enable_deposit = isset( $_POST['_enable_deposit'] ) ? 'yes' : 'no';
		$variationDeposit     = isset( $_POST['_enable_variation_deposit'] ) ? 'yes' : 'no';
		$field_deposits_type  = sanitize_text_field( $_POST['_deposits_type'] );
		$field_deposits_value = sanitize_text_field( $_POST['_deposits_value'] );

		update_post_meta( $post_id, '_enable_deposit', $field_enable_deposit );
		update_post_meta( $post_id, '_enable_variation_deposit', $variationDeposit );

		if ( ! empty( $field_deposits_type ) ) {
			update_post_meta( $post_id, '_deposits_type', $field_deposits_type );
		}
		if ( ! empty( $field_deposits_value ) ) {
			update_post_meta( $post_id, '_deposits_value', intval( $field_deposits_value ) );
		}
	}

	/**
	 * Add a custom product tab.
	 *
	 * @param  array $tabs
	 * @return mixed
	 */
	public function product_tab( $tabs ) {
		$tabs['deposits'] = array(
			'label'  => __( 'Deposit', 'deposits-for-woocommerce' ),
			'target' => 'woo_desposits_options',
			'class'  => array( 'show_if_simple' ),
		);

		return $tabs;
	}
	/**
	 * Fileds for Deposits tab
	 */
	public function options_fields() {
		?>
		<div id="woo_desposits_options" class="panel woocommerce_options_panel">
		<div class="options_group deposit-variation-option" style="display:none">
		<?php

		woocommerce_wp_checkbox(
			array(
				'id'          => '_enable_variation_deposit',
				'label'       => __( 'Enable Deposit', 'deposits-for-woocommerce' ),
				'value'       => get_post_meta( get_the_ID(), '_enable_variation_deposit', true ),
				'description' => __( 'Enable deposit feature for this Variable product. <a href="https://www.codeixer.com/docs/how-to-enable-deposit-partial-payment-feature-for-product/" target="_blank">Learn more</a>', 'deposits-for-woocommerce' ),
			)
		);
		?>
		</div>
		<div class="options_group deposit-simple-options">
			<?php
			
			woocommerce_wp_checkbox(
				array(
					'id'          => '_enable_deposit',
					'label'       => __( 'Enable Deposit', 'deposits-for-woocommerce' ),
					'value'       => get_post_meta( get_the_ID(), '_enable_deposit', true ),
					'description' => __( 'Enable deposits feature for this product.', 'deposits-for-woocommerce' ),
				)
			);

			// Type.
			woocommerce_wp_select(
				array(
					'id'      => '_deposits_type',
					'label'   => __( 'Deposit type', 'deposits-for-woocommerce' ),
					'class'   => 'wc-enhanced-select',
					'options' => array(
						'percent' => __( 'Percentage of Amount', 'deposits-for-woocommerce' ),
						'fixed'   => __( 'Fixed Amount', 'deposits-for-woocommerce' ),
					),
					'value'   => get_post_meta( get_the_ID(), '_deposits_type', true ),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'          => '_deposits_value',
					'label'       => __( 'Deposit Value *', 'deposits-for-woocommerce' ),
					// 'wrapper_class' => 'show_if_simple', //show_if_simple or show_if_variable
					'placeholder' => '',
					'value'       => get_post_meta( get_the_ID(), '_deposits_value', true ),
					'style'       => 'width:60px;',
					'description' => __( 'Enter the value for deposit. only number allow.', 'deposits-for-woocommerce' ),
				)
			);

			do_action( 'deposits_options_fileds' );
			?>
		</div>
		</div>
		<?php
	}

	/**
	 * Add deposit Fields to variable products
	 *
	 * @since 2.0.3
	 *
	 * @param $loop
	 * @param $variation_data
	 * @param $variation
	 */
	public function variations_fields( $loop, $variation_data, $variation ) {
		echo '<div class="bayna-variable-options options_group woocommerce_options_panel form-row form-row-full ">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_enable_deposit' . $variation->ID,
				'label'       => __( 'Enable Deposit', 'deposits-for-woocommerce' ),
				'value'       => get_post_meta( $variation->ID, '_enable_deposit', true ),
				'description' => __( 'Enable deposits feature for this product.', 'deposits-for-woocommerce' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_deposits_type' . $variation->ID,
				'label'   => __( 'Deposit type', 'deposits-for-woocommerce' ),
				'class'   => 'wc-enhanced-select',
				'options' => array(
					'percent' => __( 'Percentage of Amount', 'deposits-for-woocommerce' ),
					'fixed'   => __( 'Fixed Amount', 'deposits-for-woocommerce' ),
				),
				'value'   => get_post_meta( $variation->ID, '_deposits_type', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_deposits_value' . $variation->ID,
				'label'       => __( 'Deposit Value *', 'deposits-for-woocommerce' ),
				// 'wrapper_class' => 'show_if_simple', //show_if_simple or show_if_variable
				'placeholder' => '',
				'value'       => get_post_meta( $variation->ID, '_deposits_value', true ),
				'style'       => 'width:60px;',
				'description' => __( 'Enter the value for deposit. only number allow.', 'deposits-for-woocommerce' ),
			)
		);

		do_action( 'deposits_options_fileds' );

		echo '</div>';
	}

	/**
	 * Save our variable product fields
	 *
	 *  @since 2.0.3
	 * @param $post_id
	 */
	public function variations_fields_save( $post_id ) {
		// $variation_id = $_POST['variable_post_id'][array_keys($_POST['variable_post_id'])[0]];
		$product = wc_get_product( $post_id );

		$enableDeposit = isset( $_POST[ '_enable_deposit' . $post_id ] ) ? 'yes' : 'no';

		$depositType  = sanitize_text_field( $_POST[ '_deposits_type' . $post_id ] );
		$depositValue = sanitize_text_field( $_POST[ '_deposits_value' . $post_id ] );

		$product->update_meta_data( '_enable_deposit', $enableDeposit );

		$product->update_meta_data( '_deposits_type', $depositType );
		$product->update_meta_data( '_deposits_value', $depositValue );

		$product->save();
	}
}
