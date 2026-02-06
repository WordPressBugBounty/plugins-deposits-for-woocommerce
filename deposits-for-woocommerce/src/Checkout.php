<?php
namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Checkout {
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
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'to_pay_html' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'manage_deposit_orders' ), 10, 1 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_status_to_deposit' ), 10, 3 );
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'offline_deposit_orders' ), 10, 2 );
		add_filter( 'woocommerce_bacs_process_payment_order_status', array( $this, 'offline_deposit_orders' ), 10, 2 );
		add_filter( 'woocommerce_cheque_process_payment_order_status', array( $this, 'offline_deposit_orders' ), 10, 2 );
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'display_item_deposit_data' ), 20, 3 );
		add_filter( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_cart_order_meta' ), 20, 4 );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'deposit_order_received_url' ), 10, 2 );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'conditional_payment_gateways' ), 20, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'manage_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'deposits_completed' ), 10, 1 );
		add_action( 'bayna_all_deposit_payments_paid', array( $this, 'update_parent_order_metadata' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'update_child_order_status' ), 10, 1 );

		// Add blocks checkout processing with proper logic validation
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'process_blocks_checkout' ), 10, 1 );

		do_action( 'wc_deposit_checkout', $this );
	}

	/**
	 * Process blocks checkout with FREE version 4-scenario validation
	 */
	public function process_blocks_checkout( $order ) {
		// CRITICAL: Apply FREE version logic validation before processing
		if ( ! $this->should_order_be_deposit_order() ) {
			// This should be a regular order - do not apply deposit processing
			return;
		}

		// Only process if we have actual deposit items
		if ( ! cidw_cart_have_deposit_item() ) {
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
		}
	}

	/**
	 * CRITICAL: Determine if order should be a deposit order using FREE version logic
	 */
	private function should_order_be_deposit_order() {
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

			// Apply the same 4-scenario logic as BlocksIntegration
			$deposit_decision = $this->evaluate_deposit_scenario( $vProductId, $global_deposits_enabled, $global_excluded_products );

			if ( $deposit_decision['should_have_deposit'] &&
				isset( $cart_item['_deposit_mode'] ) &&
				$cart_item['_deposit_mode'] === 'check_deposit' ) {
				return true; // At least one item qualifies for deposits
			}
		}

		return false; // No items qualify for deposits
	}

	/**
	 * FREE VERSION 4-scenario evaluation logic (matches BlocksIntegration)
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
			);
		}

		// SCENARIO 2: Global ON + Product ON → Product settings take priority
		if ( $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings && ! $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_on_product_on_use_product',
			);
		}

		// SCENARIO 3: Global ON + Product OFF → Global settings apply (if not excluded)
		if ( $global_deposits_enabled && ( ! $product_deposit_enabled || ! $product_has_valid_settings ) && ! $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_on_product_off_use_global',
			);
		}

		// SCENARIO 4: Global OFF + Product ON → Product settings apply
		if ( ! $global_deposits_enabled && $product_deposit_enabled && $product_has_valid_settings ) {
			return array(
				'should_have_deposit' => true,
				'scenario'            => 'global_off_product_on_use_product',
			);
		}

		// EDGE CASE: Global ON but product is excluded → No deposit
		if ( $global_deposits_enabled && $product_excluded_from_global ) {
			return array(
				'should_have_deposit' => false,
				'scenario'            => 'global_on_but_product_excluded',
			);
		}

		// Default fallback: No deposit
		return array(
			'should_have_deposit' => false,
			'scenario'            => 'fallback_no_deposit',
		);
	}

	/**
	 * Override default order return url for deposit
	 *
	 * @param $return_url
	 * @param $order
	 */
	public function deposit_order_received_url( $return_url, $order ) {

		if ( $order && $order->get_meta( '_deposit_id' ) ) {
			$args = apply_filters(
				'bayna_deposit_order_received_url_args',
				array(
					'order_type' => 'deposit',
				)
			);

			return add_query_arg( $args, $return_url );
		}
		return $return_url;
	}

	/**
	 * update order meta data (due Ammount)
	 * since all child orders are completed
	 *
	 * @param $order_id
	 */
	public function update_parent_order_metadata( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_order_due_ammount', 0 );
		$order->save();
	}

	/**
	 * @param  $status
	 * @param  $order_id
	 * @param  $order
	 * @return mixed
	 */
	public function offline_deposit_orders( $status, $order ) {
		$order_id = $order->get_id();

		// CRITICAL: Only process if this should be a deposit order according to our logic
		if ( bayna_is_deposit( $order_id ) ) {

			$due_ammount = $order->get_total( 'edit' ) - (float) $order->get_meta( '_deposit_value' );
			$order->update_meta_data( '_order_due_ammount', $due_ammount );
			$order->save();

			$order->calculate_shipping();
			$order->calculate_totals();

		}

		// Only create deposit orders if logic validation passes
		if ( bayna_is_deposit( $order_id ) && $order->get_meta( '_genarate_deposit_orders' ) != 1 ) {
			// create deposit orders based on parent order
			$this->genarate_deposit_order( $order );
			// Set main order status 'deposit' after payment complete
			return 'wc-deposit';
		}
		return $status;
	}

	/**
	 * Change the order status to 'wc-depsoit' after payment completed
	 *
	 * @param  [type] $status
	 * @param  [type] $order_id
	 * @param  [type] $order
	 *
	 * @return void
	 */
	public function change_status_to_deposit( $status, $order_id, $order ) {
		// CRITICAL: Only change status if this should be a deposit order according to our logic
		if ( bayna_is_deposit( $order_id ) && $order->get_meta( '_genarate_deposit_orders' ) != 1 ) {

			// Set main order status 'deposit' after payment complete
			return 'wc-deposit';
		}

		return $status;
	}

	/**
	 * Genarate child deposit orders
	 *
	 * @param  [type] $order_id
	 *
	 * @return void
	 */
	public function manage_deposit_orders( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( bayna_is_deposit( $order_id ) && $order->get_meta( '_genarate_deposit_orders' ) != 1 ) {

			$due_ammount = $order->get_total( 'edit' ) - (float) $order->get_meta( '_deposit_value' );
			$order->update_meta_data( '_order_due_ammount', $due_ammount );
			$order->save();

			$order->calculate_shipping();
			$order->calculate_totals();
			// create deposit orders based on parent order
			$this->genarate_deposit_order( $order );
		}
	}

	/**
	 * change parent order status based on deposit order
	 *
	 * @param  [int]    $order_id
	 * @param  [object] $depositOrder
	 * @return void
	 */
	private function change_parent_order_status( $order_id ) {
		// check the post type and set our cusrom function

		$order = wc_get_order( $order_id );
		// The get_type() method works with HPOS and legacy storage
		$order_type = $order->get_type();

		if ( $order_type == 'shop_deposit' ) {

			$depositOrder  = new ShopDeposit( $order_id );
			$parentId      = $depositOrder->get_parent_id();
			$completedArgs = array(
				'parent' => $parentId,
				'type'   => 'shop_deposit',
				'status' => 'wc-completed',
			);

			$args = array(
				'type'   => 'shop_deposit',
				'parent' => $parentId,
			);

			$depositList   = wc_get_orders( $args );
			$completedList = wc_get_orders( $completedArgs );

			if ( count( $completedList ) == count( $depositList ) ) {
				$parentOrder = wc_get_order( $parentId );

				$parentOrder->update_status( apply_filters( 'change_order_status_on_deposit_complete_payments', cidw_get_option( 'bayna_fully_paid_status' ) ) );

				do_action( 'bayna_all_deposit_payments_paid', $parentId, $depositOrder );

				$parentOrder->save();

			}
		}
		return;
	}

	/**
	 * must need to be all deposit status completed
	 *
	 * @param  int $order_id
	 * @return void
	 */
	public function deposits_completed( $order_id ) {
		$this->change_parent_order_status( $order_id );
	}

	/**
	 * Disbale Payment gateways
	 *
	 * @param  [array] $availableGateways
	 * @return void
	 */
	public function conditional_payment_gateways( $availableGateways ) {

		// Not in backend (admin)
		if ( is_admin() || ! is_checkout() ) {
			return $availableGateways;
		}

		if ( cidw_cart_have_deposit_item() && cidw_get_option( 'cidw_payment_gateway' ) ) {
			foreach ( cidw_get_option( 'cidw_payment_gateway' ) as $key => $gateway ) {
				unset( $availableGateways[ $gateway ] );
			}
		}

		return $availableGateways;
	}

	/**
	 * Adjust 'deposit parent order' based on cart with FREE version logic validation
	 *
	 * @param  int    $order_id
	 * @param  object $order
	 * @return void
	 */
	public function manage_order( $order_id, $data ) {
		$order = wc_get_order( $order_id );

		// Loop over $cart items
		$depositValue    = 0; // no value
		$dueValue        = 0; // no value
		$cart_item_total = 0; // no value

		// calculate amount of all deposit items
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$vProductId    = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$depositValue += ( isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_deposit'] * $cart_item['quantity'] : 0;

			$cart_item_total += $cart_item['data']->get_price() * $cart_item['quantity'];

			$dueValue += ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_due_payment'] * $cart_item['quantity'] : null;
		}

		if ( 0 == $depositValue ) {
			return; // because order don't have any deposit item.
		}

		$depositValue = WC()->cart->get_total('raw');
		$order->update_meta_data( '_deposit_value', $depositValue, true );

		do_action( 'banyna_update_checkout_order_meta', $order );

		$order->save();
	}

	/**
	 * Genarate 'shop_deposit' orders based on parent order
	 *
	 * @param  [int]    $order_id
	 * @param  [object] $order
	 * @return void
	 */
	private function genarate_deposit_order( $order ) {
		$order_id                   = $order->get_id();
		$depositValue               = (float) $order->get_meta( '_deposit_value' );
		$offline_payment_gatway_ids = array( 'bacs', 'cheque', 'cod' );
		$first_deposit_order_status = 'completed';
		if ( in_array( $order->get_payment_method(), $offline_payment_gatway_ids ) ) {
			$first_deposit_order_status = 'on-hold';
		}

		// 2nd payment
		// Get order total
		$total = $order->get_total();
		// Fee items
		$item = new \WC_Order_Item_Fee();
		$item->set_name( cidw_get_option( 'txt_to_due_payment_fee', 'Due Payment for order' ) . ' #' . $order_id . '-2' );
		$item->set_total( $total - $depositValue );
		$item->set_total_tax( 0 );
		$item->save();

		$DueOrder = new ShopDeposit();
		// Order details
		$DueOrder->set_customer_id( $order->get_user_id() );
		$DueOrder->set_parent_id( $order_id );
		$DueOrder->add_item( $item );
		$DueOrder->calculate_totals( apply_filters( 'bayna_apply_tax_calculate_totals', false ) );
		$DueOrder->update_meta_data( '_deposit_id', $order_id . '-2', true );
		$DueOrder->set_status( 'pending' );
		$DueOrder->save();

		// Create new deposit order

		// Fee items
		$item = new \WC_Order_Item_Fee();
		$item->set_name( cidw_get_option( 'txt_to_deposit_payment_fee', 'Deposit Payment for order' ) . ' #' . $order_id . '-1' );
		$item->set_total( $depositValue );
		$item->set_total_tax( 0 );
		$item->save();

		$DepositOrder = new ShopDeposit();
		// Order details
		$DepositOrder->set_customer_id( $order->get_user_id() );
		$DepositOrder->set_payment_method( $order->get_payment_method() );
		$DepositOrder->set_parent_id( $order_id );
		$DepositOrder->add_item( $item );
		$DepositOrder->calculate_totals( apply_filters( 'bayna_apply_tax_calculate_totals', false ) );
		$DepositOrder->update_meta_data( '_deposit_id', $order_id . '-1', true );
		$DepositOrder->set_status( $first_deposit_order_status );
		$DepositOrder->save();

		$due_ammount = $order->get_total() - (float) $order->get_meta( '_deposit_value' );

		// add order meta data because deposit order genarate sucessfully
		$order->update_meta_data( '_order_due_ammount', $due_ammount );
		$order->update_meta_data( '_genarate_deposit_orders', '1', true );
		$order->save();
	}

	/**
	 * Save cart item custom meta as order item meta data
	 * and display it everywhere on orders and email notifications.
	 */
	public function save_cart_order_meta( $item, $cart_item_key, $values, $order ) {
		foreach ( $item as $cart_item_key => $values ) {
			if ( isset( $values['_deposit'] ) && 'check_deposit' == $values['_deposit_mode'] ) {
				$deposit_amount = $values['_deposit'];
				$item->add_meta_data( '_deposit', $deposit_amount, true );
			}
			if ( isset( $values['_due_payment'] ) && 'check_deposit' == $values['_deposit_mode'] ) {
				$due_payment = $values['_due_payment'];
				$item->add_meta_data( '_due_payment', $due_payment, true );
			}
		}
	}

	/**
	 * Display deposit data below the cart item in
	 * order review section
	 */
	public function display_item_deposit_data( $order, $cart_item ) {
		if ( isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) {
			$cart_item['_deposit']     = $cart_item['_deposit'];
			$cart_item['_due_payment'] = $cart_item['_due_payment'];
			$order                    .= sprintf(
				'<p>' . apply_filters( 'label_deposit', __( 'Deposit:', 'deposits-for-woocommerce' ) ) . ' %s <br> ' . apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ) . '  %s</p>',
				wc_price( $cart_item['_deposit'] ),
				wc_price( $cart_item['_due_payment'] )
			);
		}

		return $order;
	}

	/**
	 * Dispaly Deposit amount to know user how much need to pay.
	 */
	public function to_pay_html() {
		if ( cidw_cart_have_deposit_item() ) {
			cidw_display_to_pay_html();
		}
	}

	/**
	 * Updates the status of a child order if the order type is 'shop_deposit'.
	 *
	 * This function retrieves the order by its ID and checks if the order exists.
	 * If the order type is 'shop_deposit', it updates the order status to 'pending'
	 * or a status specified by the 'bayna_update_failed_status_for_child_orders' filter.
	 *
	 * @hooks `woocommerce_order_status_failed`
	 * @param int $order_id The ID of the order to update.
	 */
	public function update_child_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}
		if ( 'shop_deposit' === $order->get_type() ) {

			$order->update_status( apply_filters( 'bayna_update_failed_status_for_child_orders', 'pending', $order ) );
			$order->save();

		}
	}
}
