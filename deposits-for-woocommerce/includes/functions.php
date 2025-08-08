<?php

if ( ! function_exists( 'cidw_deposit_to_pay' ) ) {
	/**
	 *  Amount to pay for now html
	 */
	function cidw_deposit_to_pay() {

		$depositValue = WC()->cart->get_total( 'f' );

		echo apply_filters( 'woocommerce_deposit_to_pay_html', wc_price( $depositValue ) ); // WPCS: XSS ok.
	}
}

if ( ! function_exists( 'cidw_due_to_pay' ) ) {
	/**
	 *  Due Amount html
	 */
	function cidw_due_to_pay() {

		$duePayment = WC()->session->get( 'bayna_cart_due_amount' );

		echo apply_filters( 'bayna_due_payment_html', wc_price( $duePayment ) ); // WPCS: XSS ok.
	}
}

if ( ! function_exists( 'cidw_display_to_pay_html' ) ) {
	/**
	 * Cart & checkout page hook for
	 * display deposit table
	 */
	function cidw_display_to_pay_html() {
		?>

		<tr class="order-topay">
			<th> <?php echo esc_html( cidw_get_option( 'txt_to_pay', 'To Pay' ) ); ?></th>
			<td data-title="<?php echo esc_html( cidw_get_option( 'txt_to_pay', 'To Pay' ) ); ?>"><?php cidw_deposit_to_pay(); ?></td>
		</tr>
		<tr class="order-duepay">
			<th><?php echo apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ); ?></th>
			<td data-title="<?php echo apply_filters( 'label_due_payment', __( 'Due Payment:', 'deposits-for-woocommerce' ) ); ?>">
				<?php cidw_due_to_pay(); ?>

			</td>
		</tr>
		<?php
	}
}

if ( ! function_exists( 'cidw_cart_have_deposit_item' ) ) {
	/**
	 * Check if cart have deposit item
	 *
	 * @return boolen
	 */
	function cidw_cart_have_deposit_item() {
		$cart_item_deposit = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$vProductId          = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$cart_item_deposit[] = ( cidw_is_product_type_deposit( $vProductId ) && isset( $cart_item['_deposit_mode'] ) && 'check_deposit' == $cart_item['_deposit_mode'] ) ? $cart_item['_deposit_mode'] : null;
		}

		if ( ! array_filter( $cart_item_deposit ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'cidw_is_product_type_deposit' ) ) {
	/**
	 * check for if product type is deposit/partial
	 *
	 * @return boolen
	 */
	function cidw_is_product_type_deposit( $product_id ) {
		$ed = get_post_meta( $product_id, '_enable_deposit', true );

		if ( 'yes' == $ed || apply_filters( 'global_product_type_deposit', false ) ) {
			return true;
		}
		return false;
	}
}
/**
 * @param $value
 */
function bayna_only_pro( $value ) {
	if ( 'only_pro' == $value ) {
		return esc_html__( 'Available in PRO', 'deposits-for-woocommerce' );
	}
}

/**
 * Get the value of a settings field
 *
 * @param  string $option  settings field name
 * @param  string $default default text if it's not found
 * @return mixed
 */
function cidw_get_option( $option = '', $default = '', $section = 'deposits_settings' ) {
	$options = get_option( $section );
	return ( isset( $options[ $option ] ) ) ? $options[ $option ] : $default;
}

/**
 * List of all active payment gateway
 *
 * @return array
 */
function cidw_payment_gateway_list() {
	$active_gateways = array();
	$gateways        = WC()->payment_gateways->payment_gateways();
	foreach ( $gateways as $id => $gateway ) {
		if ( 'yes' == $gateway->enabled ) {
			$active_gateways[ $id ] = $gateway->title;
		}
	}
	return $active_gateways;
}
/**
 * Get all order status
 *
 * @return arrary
 */
function bayna_order_status() {
	$allStatus = array();
	$status    = wc_get_order_statuses();

	foreach ( $status as $id => $s ) {
		$allStatus[ $id ] = $s;
	}
	return $allStatus;
}

/**
 * @param $string
 * @param $price
 */
function bayna_replace_deposit_text( $string = '', $price = '' ) {
	$patterns     = array( '{price}', '%s' );
	$replacements = array( $price, $price );
	return str_replace( $patterns, $replacements, $string );
}

/**
 * Check if order have deposit enable
 *
 * @param  $order_id
 * @return boolen
 */
function bayna_is_deposit( $order_id ) {

	if ( $order_id == 12345 ) {
		// this condition is for solving the fatal error when using email preview template
		// currenly Woocommerce is not providing any hook to check the order type of dummy order
		return false;
	}
	$order = wc_get_order( $order_id );

	if ( ! empty( $order->get_meta( '_deposit_value' ) ) ) {
		return true;
	}
	return false;
}
