<?php

namespace Deposits_WooCommerce;

use Deposits_WooCommerce\Integrations\Wcmp;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Bootstrap {
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

		add_action( 'init', array( $this, 'register_order_type' ) );
		add_action( 'init', array( $this, 'override_hooks' ) );

		add_action( 'woocommerce_register_shop_order_post_statuses', array( $this, 'shop_order_status' ), 10, 1 );
		add_filter( 'wc_order_statuses', array( $this, 'shows_order_status' ) );
		add_filter( 'woocommerce_locate_template', array( $this, 'plugin_template' ), 20, 3 );
		add_action( 'wp_ajax_variation_toggle', array( $this, 'variation_toggle' ) );
		add_action( 'wp_ajax_nopriv_variation_toggle', array( $this, 'variation_toggle' ) );

		add_filter( 'semantic_versioning_notice_text', array( $this, 'disable_auto_update_msg' ), 20, 2 );
		$this->loadClasses();
	}
	public function disable_auto_update_msg( $notice_text, $plugin_file_name ) {

		if ( $plugin_file_name == 'deposits-for-woocommerce/deposits-for-woocommerce.php' ) {
			$notice_text = '<br></br><strong>Heads up, Please backup before upgrade!</strong> <br></br>The latest update includes some substantial changes across different areas of the plugin. We highly recommend you backup your site before upgrading, and make sure you first update in a staging environment';
		}

		return $notice_text;
	}
	// Conditional function that check if Checkout page use Checkout Blocks
	public static function is_checkout_block() {
		return \WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );
	}
	// Conditional function that check if Cart page use Cart Blocks
	public static function is_cart_block() {
		return \WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'cart' ), 'woocommerce/cart' );
	}
	// load plugin classes
	public function loadClasses() {
		Checkout::init(); // Checkout
		Order::init(); // Checkout
		Product::init(); // Single Product
		Cart::init(); // Cart
		Emails::init(); // Emails
		Settings::init(); // Settings
		DepositColums::init();
		new Wcmp();

		$this->set_admin_settings();
	}
	/**
	 * Checks if login is required for deposit.
	 *
	 * @return bool True if login is required for deposit, false otherwise.
	 */
	public static function required_login_for_deposit() {
		if ( cidw_get_option( 'required_login' ) == 1 && ! is_user_logged_in() ) {
			return true;
		} elseif ( cidw_get_option( 'required_login' ) == 1 && is_user_logged_in() ) {
			return false;
		}
		return false;
	}
	public function override_hooks() {
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
	}
	/**
	 * @param  $template
	 * @param  $template_name
	 * @param  $template_path
	 * @return mixed
	 */
	public function plugin_template( $template, $template_name, $template_path ) {
		global $wp;

		$deposit_id = null;

		if ( 'checkout/form-pay.php' === $template_name ) {

			$order_id   = absint( $wp->query_vars['order-pay'] ); // The order ID
			$order      = wc_get_order( $order_id );
			$deposit_id = $order->get_meta( '_deposit_id' );
			if ( ! $order ) {
				return $template;
			}
			if ( ! empty( $deposit_id ) ) {
				$template = CIDW_DEPOSITS_PATH . '/templates/checkout/form-pay.php';
			}
		}

		return $template;
	}
	/**
	 * @return mixed
	 */
	public static function coupon_applied_line_total() {

		return is_object( WC()->cart ) ? WC()->cart->get_cart_contents_total() : '';
	}
	/**
	 * Ajax option for adding deposits value into product
	 * on Variation Toggle
	 *
	 * @since 2.0.3
	 *
	 * @return void
	 */
	public function variation_toggle() {
		if ( ! DOING_AJAX ) {
			wp_die();
		} // Not Ajax

		// Check for nonce security
		$nonce = $_POST['nonce'];

		if ( ! wp_verify_nonce( $nonce, 'deposits_nonce' ) ) {
			wp_die( 'oops!' );
		}

		$variationID = absint( $_POST['product_id'] );
		$vProduct    = wc_get_product( $variationID );
		if ( cidw_is_product_type_deposit( $variationID ) ) {
			$deposit_type  = apply_filters( 'deposits_type', get_post_meta( $variationID, '_deposits_type', true ) );
			$deposit_value = apply_filters( 'deposits_value', get_post_meta( $variationID, '_deposits_value', true ) );
			if ( 'percent' == $deposit_type ) {
				$deposit_value  = ( $deposit_value / 100 ) * $vProduct->get_price();
				$deposit_amount = $deposit_value;
			} else {
				$deposit_amount = $deposit_value;
			}

			$deposit_text = apply_filters( 'single_product_deposits_notice', cidw_get_option( 'txt_deposit_msg', 'Deposit : %s Per item' ), wc_price( $deposit_amount ) );
			?>
			<div class="deposits-frontend-wrapper">
			<p class="deposit-notice"><?php echo $deposit_text; ?></p>
			<div class="deposits-input-wrapper">
			<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
				<input type="radio" name="deposit-mode" value="check_full" >
				<div class="state p-primary-o">
			<?php
			if ( cidw_get_option( 'cidw_radio_theme' ) == 'p-image p-plain' ) {
				echo '<img class="image" src="' . cidw_get_option( 'cidw_radio_theme_image' ) . '">';
			}
			?>

					<label><?php echo esc_html( cidw_get_option( 'txt_full_payment', 'Full Payment' ) ); ?></label>
				</div>
			</div>

			<div class="pretty <?php echo esc_attr( cidw_get_option( 'cidw_radio_theme' ) ); ?>">
				<input type="radio" name="deposit-mode" value="check_deposit" <?php echo ( $deposit_value || isset( $_POST['deposit-mode'] ) && 'check_deposit' == $_POST['deposit-mode'] || ! isset( $_POST['deposit-mode'] ) ) ? 'checked' : ''; ?>>
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

		// RIP
		wp_die();
	}

	/**
	 * Plugin settings options
	 *
	 * @return void
	 */
	public function set_admin_settings() {
		if ( cidw_get_option( 'select_mode' ) == 'allow_mix' ) {
			add_filter( 'deposits_mode', '__return_false', 10 );
		}
		if ( ( cidw_get_option( 'global_deposits_mode' ) == 1 ) ) {
			add_filter(
				'deposits_type',
				function ( $value ) {
					return 'percent';
				}
			);
			add_filter( 'global_product_type_deposit', '__return_true' );
		}
		if ( cidw_get_option( 'exclude_shipping_fee' ) == 1 ) {
			add_filter(
				'dfwc_after_due_payment_label',
				function ( $message ) {
					return cidw_get_option( 'txt_to_shipping_fee', 'Shipping Fee Included' );
				}
			);
		}
		if ( ( cidw_get_option( 'global_deposits_mode' ) == 1 && ! empty( cidw_get_option( 'global_deposits_value' ) ) ) ) {
			add_filter(
				'deposits_value',
				function ( $value ) {
					return absint( cidw_get_option( 'global_deposits_value' ) );
				}
			);
		}
		if ( ( cidw_get_option( 'global_deposits_mode' ) == 1 && cidw_get_option( 'global_force_deposit' ) == 1 ) ) {

			add_filter(
				'deposits_force_check',
				function ( $value ) {
					return 'yes';
				}
			);
		}
		if ( ( cidw_get_option( 'global_deposits_mode' ) == 1 && ! empty( cidw_get_option( 'global_deposits_value' ) ) ) ) {
			add_filter(
				'deposits_value',
				function ( $value ) {
					return absint( cidw_get_option( 'global_deposits_value' ) );
				}
			);
		}
		if ( ( cidw_get_option( 'global_hide_deposit_input' ) == 1 ) ) {
			add_filter( 'frontend_deposits_radio_input', '__return_false', 10 );
		}
		// Text & labels start
		add_filter(
			'single_product_deposits_notice',
			function ( $message, $price ) {
				$message = cidw_get_option( 'txt_deposit_msg', 'Deposit : %s Per item' );
				return bayna_replace_deposit_text( $message, $price );
			},
			10,
			2
		);

		add_filter(
			'label_deposit_paid',
			function ( $message ) {
				$message = cidw_get_option( 'txt_to_deposit_paid', 'Paid:' );
				return $message;
			}
		);
		add_filter(
			'label_due_payment',
			function ( $message ) {
				$message = cidw_get_option( 'txt_to_due_payment', 'Due Payment:' );
				return $message;
			}
		);

		add_filter(
			'label_deposit',
			function ( $message ) {
				$message = cidw_get_option( 'txt_to_deposit', 'Deposit:' );
				return $message;
			}
		);

		// Text & labels end
	}

	/**
	 * register post type for deposit
	 *
	 * @return void
	 */
	public function register_order_type() {
		wc_register_order_type(
			'shop_deposit',
			array(

				'labels'                           => array(
					'name'          => __( 'Deposit Payments', 'deposits-for-woocommerce' ),
					'menu_name'     => _x( 'Deposit Payments', 'Admin menu name', 'deposits-for-woocommerce' ),
					'singular_name' => __( 'Deposit', 'deposits-for-woocommerce' ),
					'edit_item'     => _x( 'Edit Deposit', 'custom post type setting', 'deposits-for-woocommerce' ),
					'new_item'      => _x( 'New Deposit', 'custom post type setting', 'deposits-for-woocommerce' ),
					'view'          => _x( 'View Deposit', 'custom post type setting', 'deposits-for-woocommerce' ),
					'view_item'     => _x( 'View Deposit', 'custom post type setting', 'deposits-for-woocommerce' ),
					'search_items'  => __( 'Search Deposit', 'deposits-for-woocommerce' ),
				),
				'description'                      => __( 'This is where store Deposit Payments are stored.', 'deposits-for-woocommerce' ),
				'public'                           => false,
				'show_ui'                          => true,
				'capability_type'                  => 'shop_order',
				'map_meta_cap'                     => true,
				'publicly_queryable'               => false,
				'exclude_from_search'              => true,
				'show_in_menu'                     => current_user_can( 'manage_woocommerce' ) ? 'woocommerce' : true,
				'hierarchical'                     => false,
				'show_in_nav_menus'                => false,
				'capabilities'                     => array(
					'create_posts' => 'do_not_allow',
				),
				'query_var'                        => false,
				'supports'                         => array( 'title', 'custom-fields' ),
				'has_archive'                      => false,

				// wc_register_order_type() params
				'exclude_from_orders_screen'       => true,
				'add_order_meta_boxes'             => true,
				'exclude_from_order_count'         => true,
				'exclude_from_order_views'         => true,
				'exclude_from_order_webhooks'      => true,
				'exclude_from_order_reports'       => true,
				'exclude_from_order_sales_reports' => false,
				'class_name'                       => 'Deposits_WooCommerce\ShopDeposit',

			)
		);
	}

	/**
	 * add deposit order sattus for Order post type
	 *
	 * @param  [array] $statuses
	 * @return void
	 */
	public function shop_order_status( $statuses ) {
		$statuses['wc-deposit'] = array(
			'label'                     => _x( 'Deposit Payment', 'Order status', 'deposits-for-woocommerce' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Deposit Payment <span class="count">(%s)</span>', 'Deposit Payment <span class="count">(%s)</span>', 'deposits-for-woocommerce' ),
		);

		return $statuses;
	}

	/**
	 * @param  $order_statuses
	 * @return mixed
	 */
	public function shows_order_status( $order_statuses ) {
		$order_statuses['wc-deposit'] = _x( 'Deposit Payment', 'Order status', 'deposits-for-woocommerce' );
		return $order_statuses;
	}
}
