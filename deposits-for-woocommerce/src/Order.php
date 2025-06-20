<?php

namespace Deposits_WooCommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Order {
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

		add_filter( 'woocommerce_order_item_quantity_html', array( $this, 'display_item_deposit_data' ), 20, 2 );

		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'table_row_data' ), 10, 2 );
		add_action( 'woocommerce_admin_order_totals_after_tax', array( $this, 'deposit_data_dispaly_table_tr' ), 20, 1 );
		add_action( 'woocommerce_admin_order_preview_end', array( $this, 'preview_deposit_data' ) );
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'admin_order_items_headers' ), 10, 1 );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'admin_order_items_values' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'deposit_metabox' ), 20, 2 );
		add_action( 'woocommerce_after_order_details', array( $this, 'customer_deposit_details' ), 20, 1 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'woocommerce_order_again_button' ), 10, 1 );

		add_filter( 'wc_order_is_editable', array( $this, 'order_status_editable' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'add_deposit_details' ), 10, 2 );
		add_action( 'woocommerce_trash_order', array( $this, 'trash_deposit_orders' ), 20, 1 );
		add_action( 'woocommerce_untrash_order', array( $this, 'untrash_deposit_orders' ), 20, 1 );
	}
	/**
	 * add depsoit data into teh array for display
	 * as preview
	 *
	 * @param $data
	 */
	public function add_deposit_details( $data, $order ) {
		if ( bayna_is_deposit( $order->get_id() ) ) {
			$data['deposit_paid_amount']      = '<strong>' . cidw_get_option( 'txt_to_deposit_paid', 'Paid:' ) . '</strong>' . ( ! empty( $order->get_meta( '_deposit_value' ) ) ) ? wc_price( $order->get_meta( '_deposit_value' ) ) : '-';
			$data['deposit_due_amount']       = ( ! empty( $order->get_meta( '_deposit_value' ) ) ) ? wc_price( $order->get_total() - $order->get_meta( '_deposit_value' ) ) : '-';
			$data['deposit_preview_title']    = '<h2>' . __( 'Deposit Payment details', 'deposits-for-woocommerce' ) . '</h2>';
			$data['deposit_due_amount_title'] = '<strong>' . cidw_get_option( 'txt_to_due_payment', 'Due Payment:' ) . '</strong>';
			$data['deposit_paid_title']       = '<strong>' . cidw_get_option( 'txt_to_deposit_paid', 'Paid:' ) . '</strong>';

		} else {
			$data['deposit_paid_amount']   = '';
			$data['deposit_due_amount']    = '';
			$data['deposit_preview_title'] = '';
		}

		return $data;
	}

	public function preview_deposit_data() {
		?>
		<div class="wc-order-preview-deposit">
			{{{data.deposit_preview_title}}}
			<div>
				{{{data.deposit_paid_title}}}
				{{{ data.deposit_paid_amount }}}
			</div>
			<div>
				{{{data.deposit_due_amount_title}}}
				{{{ data.deposit_due_amount }}}
			</div>
		</div>

		<?php
	}

	/**
	 * make deposit order status editable as like the pending payment status
	 *
	 * @param  string $editable
	 * @param  object $order
	 * @return void
	 */
	public function order_status_editable( $editable, $order ) {
		if ( $order->get_status() == 'deposit' ) {
			$editable = true;
		}
		return $editable;
	}
	/**
	 * Display an 'order again' button on the view order page.
	 *
	 * @param object $order.
	 */
	public function woocommerce_order_again_button( $order ) {
		if ( ! $order || ! $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ) ) || ! is_user_logged_in() ) {
			return;
		}

		$depositId = $order->get_meta( '_deposit_id' );

		if ( ! empty( $depositId ) ) {
			return;
		}

		wc_get_template(
			'order/order-again.php',
			array(
				'order'           => $order,
				'order_again_url' => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			)
		);
	}

	/**
	 * @param  $order
	 * @return null
	 */
	public function customer_deposit_details( $order ) {

		if ( empty( $order->get_meta( '_deposit_value' ) ) ) {
			return; // hide summary for non deposit orders
		}
		wc_get_template( 'order/deposit-summary.php', array( 'order' => $order ), '', CIDW_TEMPLATE_PATH );
	}

	/**
	 * Add metabox in order post type
	 *
	 * @return void
	 */
	public function deposit_metabox( $post_type, $post_or_order_object ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : wc_get_order( get_the_id() );

		if ( $order && empty( $order->get_meta( '_deposit_value', true ) ) ) {
			return; // return if not deposit
		}

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box( 'deposit-orders', __( 'Deposit Payments', 'deposits-for-woocommerce' ), array( $this, 'depositMarkupBox' ), $screen );
	}
	/**
	 * Add markup to show depsoit orders
	 *
	 * @return void
	 */
	public function depositMarkupBox( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : wc_get_order( get_the_id() );

		$args = array(
			'type'   => 'shop_deposit',
			'parent' => $order->get_id(),
		);

		$depositList = wc_get_orders( $args );

		?>

		<table class="wp-list-table widefat fixed striped table-view-excerpt ">
		<thead>

			<tr>
			<th><?php esc_html_e( 'Order Number', 'deposits-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Relationship', 'deposits-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Date', 'deposits-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Payment', 'deposits-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Status', 'deposits-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Total', 'deposits-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $depositList as $key => $deposit ) {
			$depositOrder = new ShopDeposit( $deposit->get_id() );
			if ( $depositOrder->get_status() == 'completed' ) {
				$paymentStatus = __( 'Deposit', 'deposits-for-woocommerce' );
			} else {
				$paymentStatus = __( 'Due Payment', 'deposits-for-woocommerce' );
			}
			?>
					<tr>
					<td><?php echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $depositOrder->get_id() ) . '&action=edit' ) . '" class="order-view"><strong>#' . $depositOrder->get_id() . '</strong></a>'; ?></td>

					<td><?php esc_html_e( 'Deposit payment', 'deposits-for-woocommerce' ); ?></td>

					<td>
					<?php
					$depositDate = human_time_diff( get_the_date( 'U', $deposit->get_id() ), current_time( 'U' ) );
					if ( get_the_date( 'U', $deposit->get_id() ) > current_time( 'U' ) - 86400 ) {
						echo $depositDate;
					} else {
						echo get_the_date( 'F j Y', $deposit->get_id() );
					}
					?>
			</td>

					<td><?php echo esc_html( $paymentStatus ); ?></td>

					<td>
					<?php $depositStatus = $depositOrder->get_status(); // order status ?>
					<?php printf( '<mark class="order-status %s tips"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $depositStatus ) ), wc_get_order_status_name( $depositStatus ) ); ?>
					</td>

					<td><?php echo wc_price( $depositOrder->get_total() ); ?></td>

					</tr>

		<?php } ?>

		</tbody>
		</table>
		<?php
	}
	/**
	 * Add order backend table headers
	 *
	 * @param  object $order
	 * @return void
	 */
	public function admin_order_items_headers( $order ) {

		if ( bayna_is_deposit( $order->get_id() ) == false ) {
			return;
		}
		?>
		<th class="line-deposit sortable" data-sort="float"><?php esc_html_e( 'Deposit', 'deposits-for-woocommerce' ); ?></th>
		<th class="line-due-paymnet sortable" data-sort="float"><?php esc_html_e( 'Due', 'deposits-for-woocommerce' ); ?></th>
		<?php
	}

	/**
	 * Display Order backendend deposit value
	 *
	 * @param  object $product
	 * @param  array  $item
	 * @param  int    $item_id
	 * @return void
	 */
	public function admin_order_items_values( $product, $item, $item_id ) {

		if ( null == $product ) {
			// fix fatal error if order change to refund
			echo '<td class="item_cost" width="1%">&nbsp;</td><td class="quantity" width="1%">&nbsp;</td>';

			return;
		}
		$depositValue = $item['_deposit'];
		$dueValue     = $item['_due_payment'];

		if ( null == $depositValue ) {
			return;
		}
		?>

		<td class="line-deposit" width="1%">

			<?php if ( $product && $depositValue ) { ?>
				<div class="view">
					<?php echo wc_price( $depositValue ); ?>
				</div>
				<div class="edit" style="display: none;">
					<input type="text" disabled="disabled" name="deposit[<?php echo absint( $item_id ); ?>]"
							placeholder="<?php echo wc_format_localized_price( 0 ); ?>" value="<?php echo round( $depositValue, wc_get_price_decimals() ); ?>"
							class=" wc_input_price" data-total="<?php echo round( $depositValue, wc_get_price_decimals() ); ?>"/>
				</div>
			<?php } ?>
		</td>
		<td class="deposit-due-paymnet" width="1%">

			<?php if ( $product && $depositValue ) { ?>
				<div class="view">
					<?php echo wc_price( $dueValue ); ?>
				</div>
				<div class="edit" style="display: none;">
					<input type="text" disabled="disabled" name="due_payment[<?php echo absint( $item_id ); ?>]"
							placeholder="<?php echo wc_format_localized_price( 0 ); ?>" value="<?php echo round( $dueValue, wc_get_price_decimals() ); ?>"
							class="due_payment wc_input_price" data-total="<?php echo round( $dueValue, wc_get_price_decimals() ); ?>"/>
				</div>
			<?php } ?>
		</td>
		<?php
	}

	/**
	 * Add deposit Table data in order details / Email template
	 */
	public function table_row_data( $total_rows, $order ) {
		if ( bayna_is_deposit( $order->get_id() ) == false ) {
			return $total_rows;
		}

		// Deposit order no need to show 'order again' button
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );

		// Overrirde : default order tr
		$total_rows['order_total']  = array(
			'label' => apply_filters( 'label_order_total', __( 'Total:', 'deposits-for-woocommerce' ) ),
			'value' => apply_filters( 'woocommerce_deposit_to_pay_html', wc_price( $order->get_total() ) ),
		);
		$total_rows['deposit_paid'] = array(
			'label' => apply_filters( 'label_deposit_paid', __( 'Paid:', 'deposits-for-woocommerce' ) ),
			'value' => wc_price( $order->get_meta( '_deposit_value' ) ),
		);
		$total_rows['due_payment']  = array(
			'label' => apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ),
			'value' => wc_price( $order->get_meta( '_order_due_ammount' ) ) . ' <small>' . esc_html( apply_filters( 'dfwc_after_due_payment_label', null ) ) . '</small>',
		);

		return $total_rows;
	}

	/**
	 * Dispaly Due amount in order table after deposit - for admin
	 * Dispaly Total deposit amount in order table [for admin]
	 */
	public function deposit_data_dispaly_table_tr( $order_id ) {
		$order = wc_get_order( $order_id );

		$depositValue = $order->get_meta( '_deposit_value' );

		if ( empty( $depositValue ) ) {
			return;
		}

		$dueValue = $order->get_meta( '_order_due_ammount' );
		?>
		<tr>
			<td class="label"><?php esc_html_e( 'Deposit', 'deposits-for-woocommerce' ); ?>:</td>

			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price( $depositValue ); ?>
			</td>
		</tr>
		<tr>
			<td class="label"><?php esc_html_e( 'Due Amount', 'deposits-for-woocommerce' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price( $dueValue ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Display deposit data below the cart item in
	 * order review section
	 */
	public function display_item_deposit_data( $quantity, $item ) {
		if ( isset( $item['_deposit'] ) ) {
			$depositValue = $item['_deposit'];
			$dueValue     = $item['_due_payment'];
			$quantity    .= sprintf(
				'<p>' . apply_filters( 'label_deposit', __( 'Deposit:', 'deposits-for-woocommerce' ) ) . ' %s <br> ' . apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ) . '%s</p>',
				wc_price( $depositValue ),
				wc_price( $dueValue )
			);
		}

		return $quantity;
	}
	/**
	 * Trash deposit orders when the parent order is trashed.
	 *
	 * @param int $order_id The ID of the order being trashed.
	 * @return void
	 */
	public function trash_deposit_orders( $order_id ) {
		// Check if the order is a deposit order
		if ( bayna_is_deposit( $order_id ) ) {
			$args = array(
				'type'   => 'shop_deposit',
				'parent' => $order_id,
			);

			$deposits = wc_get_orders( $args );
			foreach ( $deposits as $key => $deposit ) {
				wp_trash_post( $deposit->get_id() );
			}
		}
	}
	/**
	 * Untrash deposit orders when the parent order is untrashed.
	 *
	 * @param  int $order_id
	 * @return void
	 */
	public function untrash_deposit_orders( $order_id ) {

		// Check if the order is a deposit order
		if ( bayna_is_deposit( $order_id ) ) {
			$args = array(
				'type'   => 'shop_deposit',
				'parent' => $order_id,
				'status' => 'trash',
			);

			$deposits = wc_get_orders( $args );
			foreach ( $deposits as $key => $deposit ) {
				wp_untrash_post( $deposit->get_id() );
			}
		}
	}
}
