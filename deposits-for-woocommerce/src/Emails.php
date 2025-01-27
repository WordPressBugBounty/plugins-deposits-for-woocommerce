<?php

namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Emails {
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
		add_filter( 'woocommerce_email_classes', array( $this, 'email_classes' ) );
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'new_deposit_email' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'deposit_completed_email' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( $this, 'deposit_notification' ), 20, 1 );
		add_action( 'woocommerce_thankyou', array( $this, 'deposit_offline_notification' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'email_notifications' ), 10, 4 );
		add_action( 'woocommerce_email_order_meta', array( $this, 'customer_deposit_details' ), 10, 1 );

		add_action( 'bayna_email_show_deposit_details', array( $this, 'deposit_table' ), 20, 1 );
		add_action( 'bayna_all_deposit_payments_paid', array( $this, 'deposit_full_paid_notification' ), 10, 2 );
	}
	/**
	 * Send email notification when all deposit payment will paid /completed
	 *
	 * @param  int $parentId
	 * @return void
	 */
	public function deposit_full_paid_notification( $parentId, $depositOrder ) {
		WC()->mailer()->emails['WC_Deposit_Full_Paid']->trigger( $parentId );
	}
	/**
	 * Table data is dispLay on Deposit paid and reminder email notfication
	 *
	 * @param  object $order
	 * @return void
	 */
	public function deposit_table( $order ) {
		?>
			<table border="0" cellpadding="20" cellspacing="0" style="width:100%; margin-bottom:15px">
				<thead>
				<tr>
					<th class="td"><?php esc_html_e( 'Payment ID', 'deposits-for-woocommerce' ); ?> </th>
					<th class="td"><?php esc_html_e( 'Status', 'deposits-for-woocommerce' ); ?> </th>
					<th class="td"><?php esc_html_e( 'Amount', 'deposits-for-woocommerce' ); ?> </th>
					<th class="td"><?php esc_html_e( 'Order', 'deposits-for-woocommerce' ); ?> </th>
				</tr>
				</thead>
				<tbody>
				<?php $depositOrder = new ShopDeposit( $order->get_id() ); ?>
					<tr class="order_item">
						<td class="td">
						<?php echo '<strong>#' . $order->get_meta( '_deposit_id' ) . '</strong>'; ?>
						</td>
						<td class="td">
						<?php $depositStatus = $depositOrder->get_status(); // order status ?>
						<?php echo wc_get_order_status_name( $depositStatus ); ?>
						</td>
						<td class="td">
						<?php echo wc_price( $depositOrder->get_total() ); ?>
						</td>
						<td class="td">
						<?php $parentOrder = wc_get_order( $depositOrder->get_parent_id() ); ?>
						<?php echo '<a href="' . $parentOrder->get_view_order_url() . '">' . $depositOrder->get_parent_id() . '</a>'; ?>
						</td>
					</tr>
				</tbody>
				<tfoot>
				</tfoot>
			</table>
		<?php
	}

	/**
	 * Add deposit summary on below email order meta
	 *
	 * @param  object $order
	 * @return void
	 */
	public function customer_deposit_details( $order ) {
		if ( ! bayna_is_deposit( $order->get_id() ) ) {
			return; // hide summary for non deposit orders
		}
		wc_get_template( 'emails/deposit-summary.php', array( 'order' => $order ), '', CIDW_TEMPLATE_PATH );
	}

	/**
	 * Prevent 'shop_deposit'  order Complete email and send deposit order email
	 * new_order , customer_on_hold_order ,customer_processing_order,customer_completed_order
	 *
	 * @param  boolen $enabled
	 * @param  [type] $object
	 * @return void
	 */
	public function deposit_completed_email( $enabled, $object ) {
		if ( null == $object ) {
			return $enabled;
		}

		$orderId      = $object->get_order_number();
		$autoGenarate = get_post_meta( $orderId, '_create_from_shop_order', true ); // order genarate by updateDB class. so we don't need to send emails for exsiting orders
		if ( get_post_type( $orderId ) == 'shop_deposit' ) {
			if ( 1 == $autoGenarate ) {
				// check the auto genarate order by updateDB class
				return false; // no need to to send email for exisiting orders
			}
			WC()->mailer()->emails['WC_Customer_Deposit_Order']->trigger( $orderId );
			return false;
		}

		return $enabled;
	}

	/**
	 * Prevent 'shop_deposit' new depsoit email
	 *
	 * @param  string $enabled
	 * @param  object $object
	 * @return void
	 */
	public function new_deposit_email( $enabled, $object ) {
		if ( null == $object ) {
			return $enabled;
		}

		$orderId   = $object->get_order_number();
		$depositID = $object->get_meta( '_deposit_id' );
		if ( ! empty( $depositID ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Send deposit order email
	 *
	 * @param $orderId
	 */
	public function deposit_notification( $orderId ) {

		if ( bayna_is_deposit( $orderId ) ) {
			self::depositMailer( $orderId );
		}
	}
	/**
	 * @param $orderId
	 */
	public static function depositMailer( $orderId ): void {
		// Sending  admin Order email notification
		WC()->mailer()->emails['WC_New_deposit_Alert']->trigger( $orderId );
		// Sending Customer Order email notification
		WC()->mailer()->emails['WC_Customer_Deposit_Alert']->trigger( $orderId );
	}
	/**
	 * send deposit order email if it's a offline pay,ent gatway
	 * cod,bacs,cheque
	 *
	 * @param $orderId
	 */
	public function deposit_offline_notification( $orderId ) {
		$order                      = wc_get_order( $orderId );
		$payment_method             = $order->get_payment_method();
		$offline_payment_gatway_ids = array( 'bacs', 'cheque', 'cod' ); // TODO: add_filter for overiide 2rd party gatways

		if ( bayna_is_deposit( $orderId ) && in_array( $payment_method, $offline_payment_gatway_ids ) ) {
			self::depositMailer( $orderId );
		}
	}
	/**
	 * send deposit-status to new status related emails
	 *
	 * @param  int    $order_id
	 * @param  string $old_status
	 * @param  string $new_status
	 * @param  object $order
	 * @return void
	 */
	public function email_notifications( $order_id, $old_status, $new_status, $order ) {

		if ( 'deposit' == $old_status && 'on-hold' == $new_status ) {

			// Sending Customer On Hold Order email notification
			WC()->mailer()->emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );

		} elseif ( 'deposit' == $old_status && 'completed' == $new_status ) {

			// Sending Customer Completed Order email notification
			// WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger( $order_id );

		} elseif ( 'deposit' == $old_status && 'processing' == $new_status ) {

			// Sending Customer Processing Order email notification
			WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );

		} elseif ( 'deposit' == $old_status && 'cancelled' == $new_status ) {

			// Sending Customer Cancelled Order email notification
			WC()->mailer()->emails['WC_Email_Cancelled_Order']->trigger( $order_id );

		}
	}

	/**
	 * register new email class
	 *
	 * @param  array $Classes
	 * @return void
	 */
	public function email_classes( $Classes ) {
		$Classes['WC_New_deposit_Alert']      = new Emails\NewDeposit();
		$Classes['WC_Customer_Deposit_Alert'] = new Emails\DepositOrder();
		$Classes['WC_Customer_Deposit_Order'] = new Emails\DepositPaid();
		$Classes['WC_Deposit_Full_Paid']      = new Emails\FullPaid();

		return $Classes;
	}
	/**
	 * Email Action
	 *
	 * @param  array $actions
	 * @return void
	 */
	public function email_actions( $actions ) {
		$actions[] = 'woocommerce_order_status_wc-deposit';
		return $actions;
	}
}
