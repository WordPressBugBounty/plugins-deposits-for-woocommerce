<?php
/**
 * Order details Summary
 *
 * This template displays a summary of Desposit payments for email template
 *
 * @version 1.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! $order ) {
	return;
}
$args = array(
	'type'   => 'shop_deposit',
	'parent' => $order->get_id(),
);

$depositList = wc_get_orders( $args );


?> <h3> <?php _e( 'Deposit payments summary', 'deposits-for-woocommerce' ); ?> </h3>

<table border="0" cellpadding="20" cellspacing="0" style="width:100%; margin-bottom:15px">

	<thead>
	<tr>

		<th class="td"><?php esc_html_e( 'Payment ID', 'deposits-for-woocommerce' ); ?> </th>
		<th class="td"><?php esc_html_e( 'Status', 'deposits-for-woocommerce' ); ?> </th>
		<th class="td"><?php esc_html_e( 'Amount', 'deposits-for-woocommerce' ); ?> </th>
		<th class="td"><?php esc_html_e( 'Actions', 'deposits-for-woocommerce' ); ?> </th>

	</tr>

	</thead>

	<tbody>
	<?php

	foreach ( $depositList as $key => $deposit ) {
		$depositOrder = new \Deposits_WooCommerce\ShopDeposit( $deposit->get_id() );
		?>

		<tr class="order_item">

			<td class="td" >
					<?php echo '<strong>#' . $deposit->get_meta( '_deposit_id' ) . '</strong>'; ?>

			</td>
			<td class="td" >
				<?php $depositStatus = $depositOrder->get_status(); // order status ?>
				<?php echo wc_get_order_status_name( $depositStatus ); ?>
			</td>
			<td class="td" >
				<?php echo wc_price( $depositOrder->get_total() ); ?>
			</td>


			<td class="td" >
				<?php
				if ( $depositOrder->get_status() == 'pending' ) {
					/* translators: %s: Customer first name */
					printf( '<a href="%s" class="woocommerce-button button deposit-pay-button">%s</a>', esc_url( $depositOrder->get_checkout_payment_url() ), __( 'Make Payment ', 'deposits-for-woocommerce' ) );

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
