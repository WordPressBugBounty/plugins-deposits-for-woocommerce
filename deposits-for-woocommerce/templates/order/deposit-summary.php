<?php
/**
 * Order details Summary on Tahn you page
 *
 * This template displays a summary of Desposit payments
 *
 * @version 1.5
 */

use Deposits_WooCommerce\ShopDeposit as Deposit;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}
if ( !$order ) {
	return;
}
$args = array(
    'type'   => 'shop_deposit',
    'parent' => $order->get_id(),
);

$depositList = wc_get_orders( $args );

?>
<h4><?php _e( 'Deposit payments summary', 'deposits-for-woocommerce' )?> </h4>

<table class="woocommerce-table  woocommerce_deposits_parent_order_summary">
    <thead>
        <tr>
            <th ><?php esc_html_e( 'Payment', 'deposits-for-woocommerce' );?> </th>
            <th ><?php esc_html_e( 'Payment ID', 'deposits-for-woocommerce' );?> </th>
            <th><?php esc_html_e( 'Status', 'deposits-for-woocommerce' );?> </th>
            <th><?php esc_html_e( 'Amount', 'deposits-for-woocommerce' );?> </th>
            <th><?php esc_html_e( 'Actions', 'deposits-for-woocommerce' );?> </th>
        </tr>
    </thead>

    <tbody>
    <?php
    foreach ( $depositList as $key => $deposit ) {
        $depositOrder = new Deposit( $deposit->get_id() );

        if ( $depositOrder->get_status() == 'completed' ) {
            //todo: make staring editable from settings
            $paymentStatus = __( 'Deposit', 'deposits-for-woocommerce' );
        } else {
            $paymentStatus = __( 'Due Payment', 'deposits-for-woocommerce' );
        }
        ?>

            <tr class="order_item">
                <td>
                <?php echo esc_html( $paymentStatus ); ?>
                </td>
                <td>
                <?php echo '<strong>#' . $deposit->get_meta('_deposit_id') . '</strong>'; ?>
                </td>
                <td>
                <?php
        $depositStatus = $depositOrder->get_status();
        echo wc_get_order_status_name( $depositStatus );?>
                </td>
                <td>
                <?php echo wc_price( $depositOrder->get_total() ); ?>
                </td>

                <td>

                <?php
        if ( $depositOrder->get_status() == 'pending' ) {
            echo '<a href="' . esc_url( $depositOrder->get_checkout_payment_url() ) . '" class="woocommerce-button button deposit-pay-button"> ' . __( 'Make Payment', 'deposits-for-woocommerce' ) . ' </a>';
        } else {
            echo '-';
        }
        ?>
                </td>
            </tr>
            <?php
    }
    ?>

    </tbody>
    <tfoot>
    </tfoot>
</table>
