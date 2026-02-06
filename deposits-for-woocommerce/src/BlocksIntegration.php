<?php

namespace Deposits_WooCommerce;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BlocksIntegration {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'early_init' ), 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Checkout processing hooks
		$this->extend_store_api();
		$this->init_checkout_processing();
	}

	/**
	 * Early initialization - Register AJAX handlers
	 */
	public function early_init() {
		add_action( 'wp_ajax_bayna_get_cart_deposit_data', array( $this, 'ajax_get_cart_deposit_data' ) );
		add_action( 'wp_ajax_nopriv_bayna_get_cart_deposit_data', array( $this, 'ajax_get_cart_deposit_data' ) );
	}


	/**
	 * Initialize checkout processing
	 */
	public function init_checkout_processing() {
		// $this->log( 'Initializing checkout processing', 'info' );

		// Main checkout processing hooks
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'process_blocks_checkout' ), 10, 1 );
		add_filter( 'woocommerce_store_api_checkout_order_processed', array( $this, 'after_checkout_processed' ), 10, 1 );
	}


	/**
	 * After checkout processed
	 */
	public function after_checkout_processed( $order ) {

		$deposit_value = $order->get_meta( '_deposit_value', true );
		// CRITICAL FIX: Set the deposit total here
		if ( $deposit_value > 0 ) {

			$order->set_total( $deposit_value );
			$order->save();

		}

		return $order;
	}
	/**
	 * Enqueue scripts for blocks
	 */
	public function enqueue_scripts() {
		if ( ! is_cart() && ! has_block( 'woocommerce/cart' ) && ! is_checkout() && ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		wp_register_script(
			'bayna-cart-block',
			CIDW_DEPOSITS_ASSETS . '/js/cart-block.js',
			array( 'wp-element', 'wp-i18n', 'wp-plugins' ),
			CIDW_DEPOSITS_VERSION,
			true
		);

		wp_register_script(
			'bayna-checkout-block',
			CIDW_DEPOSITS_ASSETS . '/js/checkout-block.js',
			array( 'wp-element', 'wp-i18n', 'wp-plugins' ),
			CIDW_DEPOSITS_VERSION,
			true
		);

		$localization_data = array(
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'ajax_nonce'     => wp_create_nonce( 'deposits_blocks_nonce' ),
			'deposit_labels' => array(
				'deposit'        => cidw_get_option( 'txt_pay_deposit', 'Pay Deposit' ),
				'full_payment'   => cidw_get_option( 'txt_full_payment', 'Full Payment' ),
				'due_payment'    => cidw_get_option( 'txt_to_due_payment', 'Due Payment:' ),
				'deposit_amount' => cidw_get_option( 'txt_to_deposit', 'Deposit:' ),
			),
			'currency'       => array(
				'code'      => get_woocommerce_currency(),
				'symbol'    => get_woocommerce_currency_symbol(),
				'precision' => wc_get_price_decimals(),
			),
			'debug_mode'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
		);

		wp_localize_script( 'bayna-cart-block', 'baynaBlocksData', $localization_data );
		wp_localize_script( 'bayna-checkout-block', 'baynaBlocksData', $localization_data );

		if ( is_cart() && has_block( 'woocommerce/cart' ) ) {
			wp_enqueue_script( 'bayna-cart-block' );
		} elseif ( is_checkout() && has_block( 'woocommerce/checkout' ) ) {
			// Ensure script is loaded on classic checkout page as well
			wp_enqueue_script( 'bayna-checkout-block' );
		}
	}

	/**
	 * AJAX: Get cart deposit data with proper FREE version logic
	 */
	public function ajax_get_cart_deposit_data() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'deposits_blocks_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_error( 'Cart not available or empty' );
		}

		$cart_data = $this->get_complete_cart_deposit_data();
		wp_send_json_success( $cart_data );
	}

	/**
	 * Get complete cart deposit data with FREE version 4-scenario logic
	 */
	private function get_complete_cart_deposit_data() {
		if ( ! WC()->cart ) {
			return $this->get_empty_cart_response();
		}

		$cart_items    = WC()->cart->get_cart();
		$deposit_items = array();
		$regular_items = array();
		$total_deposit = 0;
		$total_due     = 0;

		// Get cart totals AFTER coupons
		$cart_subtotal_before_discount = WC()->cart->get_subtotal();
		$cart_subtotal_after_discount  = WC()->cart->get_subtotal() - WC()->cart->get_discount_total();
		$shipping_total                = WC()->cart->get_shipping_total();
		$tax_total                     = WC()->cart->get_taxes_total();
		$fee_total                     = WC()->cart->get_fee_total();

		// FREE VERSION: Get global settings
		$global_deposits_enabled  = cidw_get_option( 'global_deposits_mode', 0 ) == 1;
		$global_deposits_value    = cidw_get_option( 'global_deposits_value', 0 );
		$global_force_deposit     = cidw_get_option( 'global_force_deposit', 0 ) == 1;
		$global_excluded_products = cidw_get_option( 'global_deposits_exclude_products', array() );

		// Ensure excluded products is an array
		if ( ! is_array( $global_excluded_products ) ) {
			$global_excluded_products = array();
		}

		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			$product_id   = $cart_item['product_id'];
			$variation_id = $cart_item['variation_id'];
			$quantity     = $cart_item['quantity'];

			// Use variation ID if available
			$vProductId = $variation_id ? $variation_id : $product_id;

			$product      = $cart_item['data'];
			$product_name = $product->get_name();
			$unit_price   = $product->get_price();

			// Get line totals AFTER discounts (coupon handling)
			$line_total_before_discount = isset( $cart_item['line_subtotal'] ) ? $cart_item['line_subtotal'] : ( $unit_price * $quantity );
			$line_total_after_discount  = isset( $cart_item['line_total'] ) ? $cart_item['line_total'] : $line_total_before_discount;

			// CRITICAL: Apply FREE VERSION 4-scenario logic
			$deposit_decision = $this->evaluate_deposit_scenario( $vProductId, $global_deposits_enabled, $global_excluded_products );

			if ( $deposit_decision['should_have_deposit'] && isset( $cart_item['_deposit_mode'] ) && $cart_item['_deposit_mode'] === 'check_deposit' ) {
				// Calculate deposit amounts with coupon consideration
				$deposit_amounts = $this->calculate_product_deposit_with_coupons(
					$deposit_decision['settings'],
					$line_total_after_discount,
					$quantity,
					$line_total_before_discount
				);

				$deposit_items[] = array(
					'cart_item_key'       => $cart_item_key,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'product_name'        => $product_name,
					'quantity'            => $quantity,
					'unit_price'          => $unit_price,
					'line_total'          => $line_total_after_discount,
					'original_line_total' => $line_total_before_discount,
					'deposit_amount'      => $deposit_amounts['deposit'],
					'due_amount'          => $deposit_amounts['due'],
					'deposit_type'        => $deposit_decision['settings']['type'],
					'deposit_value'       => $deposit_decision['settings']['value'],
					'is_global_deposit'   => $deposit_decision['settings']['is_global'],
					'has_deposit'         => true,
					'scenario'            => $deposit_decision['scenario'], // For debugging
					'formatted_prices'    => array(
						'unit_price'     => wc_price( $unit_price ),
						'line_total'     => wc_price( $line_total_after_discount ),
						'deposit_amount' => wc_price( $deposit_amounts['deposit'] ),
						'due_amount'     => wc_price( $deposit_amounts['due'] ),
					),
				);

				$total_deposit += $deposit_amounts['deposit'];
				$total_due     += $deposit_amounts['due'];

			} else {
				// Regular item (no deposit)
				$regular_items[] = array(
					'cart_item_key'       => $cart_item_key,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'product_name'        => $product_name,
					'quantity'            => $quantity,
					'has_deposit'         => false,
					'unit_price'          => $unit_price,
					'line_total'          => $line_total_after_discount,
					'original_line_total' => $line_total_before_discount,
					'scenario'            => $deposit_decision['scenario'], // For debugging
				);

				$total_due += $line_total_after_discount;
			}
		}

		// Apply shipping handling (FREE VERSION)
		$has_deposit_items = ! empty( $deposit_items );
		if ( $has_deposit_items ) {
			if ( cidw_get_option( 'exclude_shipping_fee' ) == '0' ) {
				// With Deposit
				$total_deposit += $shipping_total;
			} else {
				// With Future Payment
				$total_due += $shipping_total;
			}
		}

		// Calculate final cart total
		$final_cart_total = $cart_subtotal_after_discount + $shipping_total + $tax_total + $fee_total;

		// Update WooCommerce session for cart display
		if ( $has_deposit_items ) {
			WC()->session->set( 'bayna_default_cart_total', $final_cart_total );
			WC()->session->set( 'bayna_cart_deposit_amount', $total_deposit );
			WC()->session->set( 'bayna_cart_due_amount', $total_due );

			// Set cart total to deposit amount for checkout
			WC()->cart->set_total( $total_deposit );
		}

		return array(
			'has_deposit_items' => $has_deposit_items,
			'items_details'     => $deposit_items,
			'regular_items'     => $regular_items,
			'deposit_total'     => $total_deposit,
			'due_total'         => $total_due,
			'original_total'    => $final_cart_total,
			'cart_summary'      => array(
				'subtotal'                => $cart_subtotal_before_discount,
				'discount_total'          => WC()->cart->get_discount_total(),
				'subtotal_after_discount' => $cart_subtotal_after_discount,
				'shipping'                => $shipping_total,
				'tax'                     => $tax_total,
				'fees'                    => $fee_total,
				'total'                   => $final_cart_total,
				'applied_coupons'         => WC()->cart->get_applied_coupons(),
			),
			'settings'          => array(
				'global_deposits_enabled' => $global_deposits_enabled,
				'global_force_deposit'    => $global_force_deposit,
				'shipping_with_deposit'   => cidw_get_option( 'exclude_shipping_fee' ) == '0',
				'select_mode'             => cidw_get_option( 'select_mode', 'allow_mix' ),
			),
		);
	}

	/**
	 * CRITICAL: FREE VERSION 4-scenario evaluation logic
	 */
	private function evaluate_deposit_scenario( $product_id, $global_deposits_enabled, $global_excluded_products ) {
		// Check individual product deposit settings
		$product_deposit_enabled    = get_post_meta( $product_id, '_enable_deposit', true ) === 'yes';
		$product_deposit_type       = get_post_meta( $product_id, '_deposits_type', true );
		$product_deposit_value      = get_post_meta( $product_id, '_deposits_value', true );
		$product_has_valid_settings = ! empty( $product_deposit_type ) && ! empty( $product_deposit_value );

		// Check if product is excluded from global deposits
		$product_excluded_from_global = in_array( $product_id, $global_excluded_products );

		// SCENARIO 1: Global OFF + Product OFF → No deposit
		if ( ! $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) ) {
			return array(
				'should_have_deposit' => false,
				'scenario'            => 'global_off_product_off',
				'settings'            => null,
			);
		}

		// SCENARIO 2: Global ON + Product ON → Product settings take priority
		if ( $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings && ! $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_on_product_on_use_product',
				'settings'            => array(
					'type'      => $product_deposit_type,
					'value'     => floatval( $product_deposit_value ),
					'is_global' => false,
				),
			);
		}

		// SCENARIO 3: Global ON + Product OFF → Global settings apply (if not excluded)
		if ( $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) && ! $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_on_product_off_use_global',
				'settings'            => array(
					'type'      => 'percent', // Global deposits are always percentage
					'value'     => floatval( cidw_get_option( 'global_deposits_value', 0 ) ),
					'is_global' => true,
				),
			);
		}

		// SCENARIO 4: Global OFF + Product ON → Product settings apply
		if ( ! $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_off_product_on_use_product',
				'settings'            => array(
					'type'      => $product_deposit_type,
					'value'     => floatval( $product_deposit_value ),
					'is_global' => false,
				),
			);
		}

		// EDGE CASE: Global ON but product is excluded → No deposit
		if ( $global_deposits_enabled && $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => false,
				'scenario'            => 'global_on_but_product_excluded',
				'settings'            => null,
			);
		}

		// Default fallback: No deposit
		return array(
			'should_have_deposit' => false,
			'scenario'            => 'fallback_no_deposit',
			'settings'            => null,
		);
	}

	/**
	 * Calculate product deposit with proper coupon handling
	 */
	private function calculate_product_deposit_with_coupons( $deposit_settings, $line_total_after_discount, $quantity, $line_total_before_discount ) {
		if ( ! $deposit_settings ) {
			return array(
				'deposit' => 0,
				'due'     => $line_total_after_discount,
			);
		}

		$deposit_amount = 0;

		if ( $deposit_settings['is_global'] ) {
			// Global deposits: always percentage of discounted amount
			$deposit_amount = ( $deposit_settings['value'] / 100 ) * $line_total_after_discount;
		} else {
			// Individual product deposits
			if ( $deposit_settings['type'] === 'percent' ) {
				$deposit_amount = ( $deposit_settings['value'] / 100 ) * $line_total_after_discount;
			} elseif ( $deposit_settings['type'] === 'fixed' ) {
				$deposit_amount = $deposit_settings['value'] * $quantity;
				// Don't let fixed deposit exceed the discounted line total
				$deposit_amount = min( $deposit_amount, $line_total_after_discount );
			}
		}

		$due_amount = $line_total_after_discount - $deposit_amount;

		return array(
			'deposit' => max( 0, $deposit_amount ),
			'due'     => max( 0, $due_amount ),
		);
	}

	/**
	 * Get empty cart response
	 */
	private function get_empty_cart_response() {
		return array(
			'has_deposit_items' => false,
			'items_details'     => array(),
			'regular_items'     => array(),
			'deposit_total'     => 0,
			'due_total'         => 0,
			'original_total'    => 0,
			'cart_summary'      => array(
				'subtotal'                => 0,
				'discount_total'          => 0,
				'subtotal_after_discount' => 0,
				'shipping'                => 0,
				'tax'                     => 0,
				'fees'                    => 0,
				'total'                   => 0,
				'applied_coupons'         => array(),
			),
			'settings'          => array(
				'global_deposits_enabled' => cidw_get_option( 'global_deposits_mode', 0 ) == 1,
				'global_force_deposit'    => cidw_get_option( 'global_force_deposit', 0 ) == 1,
				'shipping_with_deposit'   => cidw_get_option( 'exclude_shipping_fee' ) == '0',
				'select_mode'             => cidw_get_option( 'select_mode', 'allow_mix' ),
			),
		);
	}

	/**
	 * Process blocks checkout for FREE version with proper logic validation
	 */
	public function process_blocks_checkout( $order ) {
		// CRITICAL: Only process if we actually have deposit items according to our logic
		if ( ! $this->should_order_have_deposits() ) {
			// This is a regular order - no deposit processing
			return;
		}

		$deposit_value  = WC()->session->get( 'bayna_cart_deposit_amount', 0 );
		$due_amount     = WC()->session->get( 'bayna_cart_due_amount', 0 );
		$original_total = WC()->session->get( 'bayna_default_cart_total', $order->get_total() );

		if ( $deposit_value > 0 ) {
			$order->update_meta_data( '_deposit_value', $deposit_value );
			$order->update_meta_data( '_order_due_ammount', $due_amount );
			$order->update_meta_data( '_original_total', $original_total );
			$order->update_meta_data( '_processed_by_blocks', true );

			// Set order total to deposit amount
			$order->set_total( $deposit_value );
			$order->save();

			// Save individual product deposit data for order items
			$this->save_product_deposit_meta( $order );
		}
	}

	/**
	 * Determine if current cart should result in a deposit order
	 */
	private function should_order_have_deposits() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		$global_deposits_enabled  = cidw_get_option( 'global_deposits_mode', 0 ) == 1;
		$global_excluded_products = cidw_get_option( 'global_deposits_exclude_products', array() );

		if ( ! is_array( $global_excluded_products ) ) {
			$global_excluded_products = array();
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id   = $cart_item['product_id'];
			$variation_id = $cart_item['variation_id'];
			$vProductId   = $variation_id ? $variation_id : $product_id;

			$deposit_decision = $this->evaluate_deposit_scenario( $vProductId, $global_deposits_enabled, $global_excluded_products );

			if ( $deposit_decision['should_have_deposit'] &&
				isset( $cart_item['_deposit_mode'] ) &&
				$cart_item['_deposit_mode'] === 'check_deposit' ) {
				return true; // At least one item should have deposits
			}
		}

		return false; // No items should have deposits
	}

	/**
	 * Save product deposit meta to order items
	 */
	private function save_product_deposit_meta( $order ) {
		if ( ! WC()->cart ) {
			return;
		}

		$cart_items  = WC()->cart->get_cart();
		$order_items = $order->get_items();

		$cart_index = 0;
		foreach ( $order_items as $item_id => $item ) {
			$cart_item = array_values( $cart_items )[ $cart_index ] ?? null;

			if ( $cart_item && isset( $cart_item['_deposit_mode'] ) && $cart_item['_deposit_mode'] === 'check_deposit' ) {
				$item->add_meta_data( '_deposit', $cart_item['_deposit'] ?? 0, true );
				$item->add_meta_data( '_due_payment', $cart_item['_due_payment'] ?? 0, true );
				$item->save();
			}

			++$cart_index;
		}
	}

	/**
	 * Extend Store API with deposit data
	 */
	public function extend_store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			error_log( 'Bayna Blocks: Store API extension skipped - function not available' );
			return;
		}

		try {
			// Extend cart data
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => CartSchema::IDENTIFIER,
					'namespace'       => 'bayna-deposits',
					'data_callback'   => array( $this, 'extend_cart_data' ),
					'schema_callback' => array( $this, 'extend_cart_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);

			// Extend checkout data
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => CheckoutSchema::IDENTIFIER,
					'namespace'       => 'bayna-deposits',
					'data_callback'   => array( $this, 'extend_checkout_data' ),
					'schema_callback' => array( $this, 'extend_checkout_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'Bayna Blocks: Store API extension error: ' . $e->getMessage() );
		}
	}

	/**
	 * Extend cart data - only return data if deposits should apply
	 */
	public function extend_cart_data() {
		// Only return deposit data if deposits should actually apply
		if ( ! $this->should_order_have_deposits() ) {
			return array(
				'has_deposit_items' => false,
				'deposit_total'     => 0,
				'due_total'         => 0,
				'original_total'    => WC()->cart ? WC()->cart->total : 0,
			);
		}

		$has_deposit_items = cidw_cart_have_deposit_item();
		$deposit_total     = WC()->session ? WC()->session->get( 'bayna_cart_deposit_amount', 0 ) : 0;
		$due_total         = WC()->session ? WC()->session->get( 'bayna_cart_due_amount', 0 ) : 0;
		$original_total    = WC()->session ? WC()->session->get( 'bayna_default_cart_total', WC()->cart->total ) : ( WC()->cart ? WC()->cart->total : 0 );

		return array(
			'has_deposit_items' => $has_deposit_items,
			'deposit_total'     => $deposit_total,
			'due_total'         => $due_total,
			'original_total'    => $original_total,
		);
	}

	/**
	 * Extend cart schema
	 */
	public function extend_cart_schema() {
		return array(
			'has_deposit_items' => array(
				'description' => 'Whether cart has deposit items',
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'deposit_total'     => array(
				'description' => 'Total deposit amount',
				'type'        => 'number',
				'readonly'    => true,
			),
			'due_total'         => array(
				'description' => 'Total due amount',
				'type'        => 'number',
				'readonly'    => true,
			),
			'original_total'    => array(
				'description' => 'Original cart total',
				'type'        => 'number',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Extend checkout data - only enable deposits if logic says so
	 */
	public function extend_checkout_data() {
		// Critical: Only enable deposits if our logic evaluation says we should
		$should_have_deposits = $this->should_order_have_deposits();

		if ( ! $should_have_deposits ) {
			return array(
				'deposit_enabled' => false,
				'deposit_amount'  => 0,
				'due_amount'      => 0,
			);
		}

		return array(
			'deposit_enabled' => true,
			'deposit_amount'  => WC()->session ? WC()->session->get( 'bayna_cart_deposit_amount', 0 ) : 0,
			'due_amount'      => WC()->session ? WC()->session->get( 'bayna_cart_due_amount', 0 ) : 0,
		);
	}

	/**
	 * Extend checkout schema
	 */
	public function extend_checkout_schema() {
		return array(
			'deposit_enabled' => array(
				'description' => 'Whether deposit payment is enabled',
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'deposit_amount'  => array(
				'description' => 'Deposit amount to pay now',
				'type'        => 'number',
				'readonly'    => true,
			),
			'due_amount'      => array(
				'description' => 'Due amount to pay later',
				'type'        => 'number',
				'readonly'    => true,
			),
		);
	}
}
