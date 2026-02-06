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
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_deposit_data' ), 20, 2 );
		add_action( 'add_meta_boxes', array( $this, 'deposit_metabox' ), 20, 2 );
		add_action( 'woocommerce_after_order_details', array( $this, 'customer_deposit_details' ), 20, 1 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'woocommerce_order_again_button' ), 10, 1 );

		add_filter( 'wc_order_is_editable', array( $this, 'order_status_editable' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'add_deposit_details' ), 10, 2 );
		add_action( 'woocommerce_trash_order', array( $this, 'trash_deposit_orders' ), 20, 1 );
		add_action( 'woocommerce_untrash_order', array( $this, 'untrash_deposit_orders' ), 20, 1 );
		add_action( 'wp_ajax_bayna_remove_deposit', array( $this, 'bayna_remove_deposit_callback' ) );
		add_action( 'wp_ajax_bayna_update_deposit_order', array( $this, 'bayna_update_deposit_order_callback' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_action_buttons' ), 10, 1 );
	}
	/**
	 * add deposit data into the array for display
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
	 * Format order item deposit data.
	 *
	 * @param  array                 $formatted_meta
	 * @param  WC_Order_Item_Product $item
	 *
	 * @return array
	 */
	public function format_order_item_deposit_data( $formatted_meta, $item ) {

		foreach ( $formatted_meta as $key => $meta ) {

			if ( $meta->key == '_deposit' ) {
				$meta->display_value = wc_price( $meta->value, array( 'currency' => $item->get_order()->get_currency() ) );
				$meta->display_key   = __( 'Deposit Amount', 'deposits-for-woocommerce' );

			} elseif ( $meta->key == '_due_payment' ) {
				$meta->display_key   = __( 'Due Amount', 'deposits-for-woocommerce' );
				$meta->display_value = wc_price( $meta->value, array( 'currency' => $item->get_order()->get_currency() ) );
			}
		}
			return $formatted_meta;
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
	/**
	 * Add deposit action buttons for the order.
	 *
	 * @param  WC_Order $order
	 */
	public function add_action_buttons( $order ) {
		if ( $order->is_editable() ) :

			?>
			<div id="deposit-edit-wrapper" class="modal" style="max-width:500px">
				<?php
				\CSF::$enqueue = true;
				\CSF::add_admin_enqueue_scripts();
				echo '<div class="csf-onload">';

				/**
				 *  @field
				 *  @value
				 *  @unique
				*/

				\CSF::field(
					array(
						'id'       => 'order_deposits_type',
						'type'     => 'select',
						'title'    => 'Deposit Type',
						'options'  => array(
							'fixed'      => 'Fixed',
							'percentage' => 'Percentage',
							'plans'      => 'Payment Plans',

						),
						'settings' => array(
							'width' => '50%',
						),

					),
					'fixed'
				);
					\CSF::field(
						array(
							'id'         => 'order_deposit_value',
							'type'       => 'number',
							'title'      => 'Deposits Value',

							'dependency' => array( 'order_deposits_type', '!=', 'plans' ),

							'desc'       => 'The deposit amount should not exceed 99% for percentage deposits or surpass the total order amount for fixed deposits.',
						),
						'',
					);
					\CSF::field(
						array(
							'type'       => 'notice',
							'style'      => 'danger',
							'content'    => __( 'Payment Plans are only available for Premium Version.', 'deposits-for-woocommerce' ),
							'dependency' => array( 'order_deposits_type', '==', 'plans' ),

						),
						'',
					);

				echo '<button type="button" id="update-deposit-order" data-order-id="' . esc_attr( $order->get_id() ) . '" class="button button-primary">Update Order</button></div>';
				?>
				
			</div>
			<a href="#deposit-edit-wrapper" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" class="button button-primary calculate-deposit-action"><?php esc_html_e( 'Recalculate Deposit', 'deposits-for-woocommerce' ); ?></a>
			
			<?php
		endif;
		if ( $order->get_meta( '_deposit_value' ) ) {
			?>
			<button type="button" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" class="button remove-deposit-action"><?php esc_html_e( 'Remove Deposit', 'deposits-for-woocommerce' ); ?></button>
			<?php
		}
	}
	public function bayna_remove_deposit_callback() {
		if ( ! DOING_AJAX ) {
			wp_die();
		} // Not Ajax

		// Check for nonce security
		$nonce = $_POST['nonce'];

		$order_id = absint( $_POST['order_id'] );

		if ( ! wp_verify_nonce( $nonce, 'deposit_admin_nonce' ) ) {
			wp_die( 'oops! nonce error' );
		}
		$order = wc_get_order( $order_id );

		if ( $order->get_meta( '_deposit_value' ) ) {

			$order->delete_meta_data( '_deposit_value' );
			$order->delete_meta_data( '_order_due_ammount' );
			$order->delete_meta_data( '_payment_plan_id' );
			$order->delete_meta_data( '_genarate_deposit_orders' );
			$order->delete_meta_data( '_checkout_mode' );
			$order->calculate_totals();
			foreach ( $order->get_items() as $item_id => $item ) {
				$item->delete_meta_data( '_deposit' );
				$item->delete_meta_data( '_due_payment' );
				$item->delete_meta_data( '_payment_plan_id' );
				$item->save();
			}
			$args         = array(
				'type'   => 'shop_deposit',
				'parent' => $order_id,
			);
			$child_orders = wc_get_orders( $args );
			foreach ( $child_orders as $child_order ) {
				$child_order->delete( true ); // Force delete child deposit orders
			}
			$order->add_order_note( __( 'Deposit removed.', 'deposits-for-woocommerce' ), false, true );
			$order->save();
		}
		$data = array(
			'order_id' => $order_id,
		);

		wp_send_json_success( $data );
		// RIP
		wp_die();
	}
	public function bayna_update_deposit_order_callback() {
		if ( ! DOING_AJAX ) {
			wp_die();
		} // Not Ajax

		// Check for nonce security
		$nonce = $_POST['nonce'];

		$order_id      = absint( $_POST['order_id'] );
		$deposit_type  = sanitize_text_field( $_POST['deposit_type'] );
		$deposit_value = sanitize_text_field( $_POST['deposit_value'] );

		if ( ! wp_verify_nonce( $nonce, 'deposit_admin_nonce' ) ) {
			wp_die( 'oops! nonce error' );
		}
		$order = wc_get_order( $order_id );

		if ( $deposit_type == 'fixed' && $deposit_value > 0 ) {

			$order->update_meta_data( '_deposit_value', $deposit_value );
			$due_ammount = $order->get_total() - $order->get_meta( '_deposit_value' );
			$order->update_meta_data( '_order_due_ammount', $due_ammount, true );

		} elseif ( $deposit_type == 'percentage' && $deposit_value > 0 ) {

			$due_ammount   = $order->get_total() - ( $order->get_total() * ( $deposit_value / 100 ) );
			$deposit_value = $order->get_total() * ( $deposit_value / 100 );
			$order->update_meta_data( '_deposit_value', $deposit_value );
			$order->update_meta_data( '_order_due_ammount', $due_ammount, true );

		} else {
			wp_send_json_error( __( 'Invalid deposit update request.', 'deposits-for-woocommerce' ) );
		}
		// Add order note for deposit updates

			$order->add_order_note(
				sprintf(
					/* translators: 1: deposit type 2: deposit amount 3: due amount */
					__( 'Deposit updated - Type: %1$s, Deposit amount: %2$s, Due amount: %3$s', 'deposits-for-woocommerce' ),
					ucfirst( $deposit_type ),
					wc_price( $deposit_value ),
					wc_price( $due_ammount )
				),
				false,
				true
			);

		$order->save();
		Checkout::init()->manage_deposit_orders( $order_id );

		wp_send_json_success(
			sprintf(
				/* translators: 1: deposit amount 2: due amount */
				__( 'Deposit updated - Deposit amount: %1$s, Due amount: %2$s. Page will reload in 3 seconds.', 'deposits-for-woocommerce' ),
				wc_price( $deposit_value ),
				wc_price( $due_ammount )
			),
		);

		// RIP
		wp_die(); // lol, json call it by default
	}
}
