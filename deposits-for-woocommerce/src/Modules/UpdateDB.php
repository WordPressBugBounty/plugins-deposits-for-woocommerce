<?php

// WC CRUD for replace old DB

namespace Deposits_WooCommerce\Modules;

use Deposits_WooCommerce\ShopDeposit;

class UpdateDB extends \WC_Background_Process {

	public $logger;
	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'bayna_update_db';
		$this->logger = new \WC_Logger();

		parent::__construct();
	}

	/**
	 * @param $item
	 */
	protected function task( $item ) {
		$this->update_orders( $item );

		$this->logger->add( 'bayna-update-db', 'Found Deposit Order ID #' . $item );
		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		update_option( 'bayna_update_completed', 1 );

		parent::complete();
		// Show notice to user or perform some other arbitrary task...
	}

	/**
	 * @param $order_id
	 */
	protected function update_orders( $order_id ) {
		// code...

		$order = wc_get_order( $order_id );

		$depositValue = get_post_meta( $order_id, 'deposit_value', true );
		$order->calculate_totals();

		if ( $order->get_total() != $depositValue && get_post_meta( $order_id, '_genarate_deposit_orders', true ) != 1 && $depositValue > 0 ) {

			// 2nd payment
			// Get order total

			$total    = $order->get_total();
			$DueOrder = new ShopDeposit();
			// Order details
			$DueOrder->set_customer_id( $order->get_user_id() );
			$DueOrder->set_parent_id( $order->get_id() );

			// Fee items
			$item = new \WC_Order_Item_Fee();
			$item->set_name( 'Due Payment for order #' . $order->get_id() . '-2' );
			$item->set_total( $total - $depositValue );
			$item->save();
			$DueOrder->add_item( $item );
			$DueOrder->calculate_totals();
			$DueOrder->update_meta_data( '_deposit_id', $DueOrder->get_id() . '-2', true );
			$DueOrder->set_date_created( $order->get_date_created() );
			$DueOrder->set_status( 'pending' );
			$DueOrder->save();

			// Create new deposit order

			$DepositOrder = new ShopDeposit();
			// Order details
			$DepositOrder->set_customer_id( $order->get_user_id() );
			$DepositOrder->set_payment_method( $order->get_payment_method() );
			$DepositOrder->set_parent_id( $order->get_id() );

			// Fee items
			$item = new \WC_Order_Item_Fee();
			$item->set_name( 'Deposit Payment for order #' . $order->get_id() . '-1' );
			$item->set_total( $depositValue );
			$item->save();

			$DepositOrder->add_item( $item );
			$DepositOrder->calculate_totals();
			$DepositOrder->update_meta_data( '_deposit_id', $DepositOrder->get_id() . '-1', true );
			$DepositOrder->update_meta_data( '_create_from_shop_order', '1', true );
			$DepositOrder->set_date_created( $order->get_date_created() );
			$DepositOrder->set_status( 'completed' );
			$DepositOrder->save();

			// add order meta data because deposit order genarate sucessfully
			$order->update_meta_data( '_genarate_deposit_orders', '1', true );
			$order->update_meta_data( '_deposit_value', $depositValue, true );

			if ( get_post_meta( $order_id, 'already_cancelled', true ) ) {
				$order->update_status( 'cancelled', '[Bayna - update database]' );
			} else {
				$order->update_status( 'deposit', '[Bayna - update database]' );
			}
		}
	}
}
