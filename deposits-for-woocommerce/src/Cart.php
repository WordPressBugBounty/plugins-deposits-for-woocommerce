<?php

namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cart {
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
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'to_pay_html' ) );
		add_filter( 'woocommerce_after_cart_item_name', array( $this, 'display_cart_item_deposit_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'cart_item_data' ), 20, 3 );
		add_filter( 'woocommerce_cart_totals_order_total_html', array( $this, 'cart_total_html' ), 10, 1 );
		add_filter( 'woocommerce_calculated_total', array( $this, 'recalculate_price' ), 100, 2 );
	}

	/**
	 * Updates the cart item data for deposit products with FREE version 4-scenario validation
	 *
	 * @param array $cart_item_data The cart item data.
	 * @param int   $product_id The product ID.
	 * @param int   $variation_id The variation ID.
	 * @return array The updated cart item data.
	 */
	public function cart_item_data( $cart_item_data, $product_id, $variation_id ) {

		$vProductId = ( $variation_id ) ? $variation_id : $product_id;

		// CRITICAL: Apply FREE version 4-scenario logic before processing
		if ( ! $this->should_product_have_deposit( $vProductId ) || ! isset( $_POST['deposit-mode'] ) ) {
			return $cart_item_data; // Exit if product shouldn't have deposits
		}

		$product = wc_get_product( $vProductId );

		// Get the appropriate deposit settings based on our logic
		$deposit_settings = $this->get_product_deposit_settings( $vProductId );

		$deposit_value                   = $deposit_settings['value'];
		$cart_item_data['_deposit_type'] = $deposit_settings['type'];

		if ( $deposit_settings['type'] == 'percent' ) {
			$deposit_value = ( $deposit_value / 100 ) * $product->get_price();
		}

		$cart_item_data['_deposit']      = $deposit_value;
		$cart_item_data['_due_payment']  = $product->get_price() - $deposit_value;
		$cart_item_data['_deposit_mode'] = sanitize_text_field( $_POST['deposit-mode'] );

		return $cart_item_data;
	}

	/**
	 * CRITICAL: FREE VERSION 4-scenario logic to determine if product should have deposits
	 */
	private function should_product_have_deposit( $product_id ) {
		$global_deposits_enabled  = cidw_get_option( 'global_deposits_mode', 0 ) == 1;
		$global_excluded_products = cidw_get_option( 'global_deposits_exclude_products', array() );

		if ( ! is_array( $global_excluded_products ) ) {
			$global_excluded_products = array();
		}

		// Check individual product deposit settings
		$product_deposit_enabled    = get_post_meta( $product_id, '_enable_deposit', true ) === 'yes';
		$product_deposit_type       = get_post_meta( $product_id, '_deposits_type', true );
		$product_deposit_value      = get_post_meta( $product_id, '_deposits_value', true );
		$product_has_valid_settings = ! empty( $product_deposit_type ) && ! empty( $product_deposit_value );

		// Check if product is excluded from global deposits
		$product_excluded_from_global = in_array( $product_id, $global_excluded_products );

		// SCENARIO 1: Global OFF + Product OFF → No deposit
		if ( ! $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) ) {
			return false;
		}

		// SCENARIO 2: Global ON + Product ON → Product settings take priority
		if ( $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings && ! $product_excluded_from_global ) {
			return true;
		}

		// SCENARIO 3: Global ON + Product OFF → Global settings apply (if not excluded)
		if ( $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) && ! $product_excluded_from_global ) {
			return true;
		}

		// SCENARIO 4: Global OFF + Product ON → Product settings apply
		if ( ! $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings ) {
			return true;
		}

		// EDGE CASE: Global ON but product is excluded → No deposit
		if ( $global_deposits_enabled && $product_excluded_from_global ) {
			return false;
		}

		// Default fallback: No deposit
		return false;
	}

	/**
	 * Get product deposit settings with FREE version priority logic
	 */
	private function get_product_deposit_settings( $product_id ) {
		$global_deposits_enabled  = cidw_get_option( 'global_deposits_mode', 0 ) == 1;
		$global_deposits_value    = cidw_get_option( 'global_deposits_value', 0 );
		$global_excluded_products = cidw_get_option( 'global_deposits_exclude_products', array() );

		if ( ! is_array( $global_excluded_products ) ) {
			$global_excluded_products = array();
		}

		// Check individual product settings first
		$product_deposit_enabled      = get_post_meta( $product_id, '_enable_deposit', true ) === 'yes';
		$product_deposit_type         = get_post_meta( $product_id, '_deposits_type', true );
		$product_deposit_value        = get_post_meta( $product_id, '_deposits_value', true );
		$product_has_valid_settings   = ! empty( $product_deposit_type ) && ! empty( $product_deposit_value );
		$product_excluded_from_global = in_array( $product_id, $global_excluded_products );

		// Apply FREE VERSION priority logic
		if ( $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings && ! $product_excluded_from_global ) {
			// Global on + Product on → Use product settings
			return array(
				'type'      => $product_deposit_type,
				'value'     => floatval( $product_deposit_value ),
				'is_global' => false,
			);
		}

		if ( $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) && ! $product_excluded_from_global ) {
			// Global on + Product off → Use global settings
			return array(
				'type'      => 'percent', // Global deposits are always percentage
				'value'     => floatval( $global_deposits_value ),
				'is_global' => true,
			);
		}

		if ( ! $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings ) {
			// Global off + Product on → Use product settings
			return array(
				'type'      => $product_deposit_type,
				'value'     => floatval( $product_deposit_value ),
				'is_global' => false,
			);
		}

		// Fallback
		return array(
			'type'      => 'fixed',
			'value'     => 0,
			'is_global' => false,
		);
	}

	/**
	 * @param $cart_total
	 */
	public function cart_total_html( $cart_total ) {

		$cartTotal = WC()->cart->total;
		// Loop over $cart items
		$depositValue = 0; // no value
		$dueValue     = 0; // no value
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

			// CRITICAL: Only calculate due value if product should have deposits
			if ( $this->should_product_have_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) {
				$dueValue += $cart_item['_due_payment'];
			}
		}

		$value = $cartTotal + $dueValue;

		return '<strong>' . wc_price( $value ) . '</strong>';
	}

	/**
	 * Display the Deposit Data as item data
	 */
	public function display_cart_item_deposit_data( $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['_deposit'] ) && is_cart() && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) {

			$cart_item['_deposit']     = $cart_item['_deposit'];
			$cart_item['_due_payment'] = $cart_item['_due_payment'];

			$deposit_info = sprintf(
				'<p>' . apply_filters( 'label_deposit', __( 'Deposit:', 'deposits-for-woocommerce' ) ) . ' %s <br> ' . apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ) . ' %s </p>',
				wc_price( $cart_item['_deposit'] ),
				wc_price( $cart_item['_due_payment'] )
			);

			echo $deposit_info;
		}
	}

	/**
	 * Recalculate the price of the cart with FREE version 4-scenario validation
	 *
	 * @param $total
	 * @param $cart
	 * @return mixed
	 */
	public function recalculate_price( $total, $cart ) {
		// Loop over $cart items
		$cart_item_total = 0; // no value
		$deposit_amount  = 0; // no value
		$due_amount      = 0; // no value

		$this->update_deposit_meta_data( $cart );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

			// CRITICAL: Only process deposits if product should have them according to our logic
			if ( $this->should_product_have_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) {
				$deposit_amount += $cart_item['_deposit'] ;
				$due_amount     += $cart_item['_due_payment'];
			}

			$cart_item_total += $cart_item['line_subtotal'] * $cart_item['quantity'];
		}

		// Only apply shipping handling if we have valid deposit items
		if ( $deposit_amount > 0 ) {
			// Shipping fee
			if ( cidw_get_option( 'exclude_shipping_fee' ) == '0' ) {
				$deposit_amount += $cart->get_totals()['shipping_total'];
			} elseif ( cidw_get_option( 'exclude_shipping_fee' ) == '1' ) {
				$due_amount += $cart->get_totals()['shipping_total'];
			}
		}

		WC()->session->set( 'bayna_default_cart_total', $cart->total );
		WC()->session->set( 'bayna_cart_deposit_amount', $deposit_amount );
		WC()->session->set( 'bayna_cart_due_amount', $due_amount );
		$cart_total = $total - $due_amount;
		return apply_filters( 'bayna_deposit_cart_total', $cart_total );
	}

	/**
	 * Update deposit meta data with FREE version validation
	 *
	 * @param $cart
	 */
	public function update_deposit_meta_data( $cart ) {

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			$vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

			// CRITICAL: Only update deposit meta if product should have deposits
			if ( ! $this->should_product_have_deposit( $vProductId ) || ! isset( $cart_item['_deposit_mode'] ) || 'check_deposit' != $cart_item['_deposit_mode'] ) {
				continue;
			}

			$deposit_settings = $this->get_product_deposit_settings( $vProductId );
			$deposit_value    = $deposit_settings['value'];

			if ( $deposit_settings['is_global'] || $deposit_settings['type'] == 'percent' ) {
				$deposit_value = ( $deposit_value / 100 ) * $cart_item['line_subtotal'];
				$due_payment   = $cart_item['line_subtotal'] - $deposit_value;

				WC()->cart->cart_contents[ $cart_item_key ]['_deposit']     = $deposit_value;
				WC()->cart->cart_contents[ $cart_item_key ]['_due_payment'] = $due_payment;
			} else {
				$deposit_value = $deposit_value * $cart_item['quantity'];
				$due_payment   = $cart_item['line_subtotal'] - $deposit_value;

				WC()->cart->cart_contents[ $cart_item_key ]['_deposit']     = $deposit_value;
				WC()->cart->cart_contents[ $cart_item_key ]['_due_payment'] = $due_payment;
			}
		}
	}

	/**
	 * Get cart deposit data for blocks with FREE version logic validation
	 */
	public function get_cart_deposit_data() {
		$deposit_items = array();
		$total_deposit = 0;
		$total_due     = 0;

		if ( ! WC()->cart ) {
			return array(
				'has_deposit_items' => false,
				'items_details'     => array(),
				'deposit_total'     => 0,
				'due_total'         => 0,
				'original_total'    => 0,
			);
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

			// CRITICAL: Apply FREE version logic before processing
			if ( $this->should_product_have_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && $cart_item['_deposit_mode'] === 'check_deposit' ) {
				$deposit_amount = $cart_item['_deposit'] * $cart_item['quantity'];
				$due_amount     = $cart_item['_due_payment'] * $cart_item['quantity'];

				$deposit_items[] = array(
					'cart_item_key'  => $cart_item_key,
					'product_id'     => $cart_item['product_id'],
					'variation_id'   => $cart_item['variation_id'],
					'product_name'   => $cart_item['data']->get_name(),
					'deposit_amount' => $deposit_amount,
					'due_amount'     => $due_amount,
					'has_deposit'    => true,
				);

				$total_deposit += $deposit_amount;
				$total_due     += $due_amount;
			}
		}

		// Add shipping handling only if we have valid deposit items
		if ( ! empty( $deposit_items ) ) {
			if ( cidw_get_option( 'exclude_shipping_fee' ) == '0' ) {
				$total_deposit += WC()->cart->get_shipping_total();
			} else {
				$total_due += WC()->cart->get_shipping_total();
			}
		}

		$original_total = WC()->cart->get_total( 'edit' );
		if ( ! empty( $deposit_items ) ) {
			$original_total += $total_due;
		}

		return array(
			'has_deposit_items' => ! empty( $deposit_items ),
			'items_details'     => $deposit_items,
			'deposit_total'     => $total_deposit,
			'due_total'         => $total_due,
			'original_total'    => $original_total,
		);
	}

	/**
	 * Dispaly Deposit amount to know user how much need to pay.
	 */
	public function to_pay_html() {
		// CRITICAL: Only show deposit HTML if we actually have valid deposit items
		if ( $this->has_valid_deposit_items() ) {
			cidw_display_to_pay_html();
		}
	}

	/**
	 * Check if cart has valid deposit items according to FREE version logic
	 */
	private function has_valid_deposit_items() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

			if ( $this->should_product_have_deposit( $vProductId ) &&
				isset( $cart_item['_deposit_mode'] ) &&
				$cart_item['_deposit_mode'] === 'check_deposit' ) {
				return true;
			}
		}

		return false;
	}
}
