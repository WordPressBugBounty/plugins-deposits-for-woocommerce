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
	 * Updates the cart item data for deposit products.
	 *
	 * @param array $cart_item_data The cart item data.
	 * @param int   $product_id The product ID.
	 * @param int   $variation_id The variation ID.
	 * @return array The updated cart item data.
	 */
	public function cart_item_data( $cart_item_data, $product_id, $variation_id ) {

		$vProductId = ( $variation_id ) ? $variation_id : $product_id;

		if ( cidw_is_product_type_deposit( $vProductId ) == false || ! isset( $_POST['deposit-mode'] ) ) {

			return $cart_item_data; // Exit if not deposit product
		}

		$product = wc_get_product( $vProductId );

		$deposit_value                   = apply_filters( 'deposits_value', get_post_meta( $vProductId, '_deposits_value', true ) );
		$cart_item_data['_deposit_type'] = 'fixed';
		if ( apply_filters( 'deposits_type', get_post_meta( $vProductId, '_deposits_type', true ) ) == 'percent' ) {
			$deposit_value                   = ( $deposit_value / 100 ) * $product->get_price();
			$cart_item_data['_deposit_type'] = 'percent';
		}

		$cart_item_data['_deposit']      = $deposit_value;
		$cart_item_data['_due_payment']  = $product->get_price() - $deposit_value;
		$cart_item_data['_deposit_mode'] = sanitize_text_field( $_POST['deposit-mode'] );

		return $cart_item_data;
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

			$dueValue += ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_due_payment'] : null;
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
	 * Recalculate the price of the cart
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
			$vProductId       = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$deposit_amount  += ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_deposit'] * $cart_item['quantity'] : null;
			$due_amount      += ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_due_payment'] : null;
			$cart_item_total += $cart_item['line_subtotal'] * $cart_item['quantity'];

		}
		// Shipping fee
		if ( cidw_get_option( 'exclude_shipping_fee' ) == '0' ) {
			$deposit_amount += $cart->get_totals()['shipping_total'];

		} elseif ( cidw_get_option( 'exclude_shipping_fee' ) == '1' ) {
			$due_amount += $cart->get_totals()['shipping_total'];
		}

		WC()->session->set( 'bayna_default_cart_total', $cart->total );
		WC()->session->set( 'bayna_cart_deposit_amount', $deposit_amount );
		WC()->session->set( 'bayna_cart_due_amount', $due_amount );
		$cart_total = $total - $due_amount;
		return apply_filters( 'bayna_deposit_cart_total', $cart_total );
	}
	/**
	 * @param $cart
	 */
	public function update_deposit_meta_data( $cart ) {

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			$vProductId    = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$deposit_value = apply_filters( 'deposits_value', get_post_meta( $vProductId, '_deposits_value', true ) );

			if ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) {

				if ( apply_filters( 'deposits_type', get_post_meta( $vProductId, '_deposits_type', true ) ) == 'percent' ) {

					$deposit_value = ( $deposit_value / 100 ) * $cart_item['line_subtotal'];

					$due_payment = $cart_item['line_subtotal'] - $deposit_value;

					WC()->cart->cart_contents[ $cart_item_key ]['_deposit']     = $deposit_value;
					WC()->cart->cart_contents[ $cart_item_key ]['_due_payment'] = $due_payment;
				} else {
					$deposit_value = $deposit_value * $cart_item['quantity'];

					$due_payment = $cart_item['line_subtotal'] - $deposit_value;

					WC()->cart->cart_contents[ $cart_item_key ]['_deposit']     = $deposit_value;
					WC()->cart->cart_contents[ $cart_item_key ]['_due_payment'] = $due_payment;
				}
			}
		}
	}
	/**
	 * Dispaly Deposit amount to know user how much need to pay.
	 */
	public function to_pay_html() {
		if ( cidw_cart_have_deposit_item() ) {
			cidw_display_to_pay_html();
		}
	}
}
