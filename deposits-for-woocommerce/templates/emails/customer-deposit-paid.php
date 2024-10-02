<?php
/**
 * Customer deposit paid email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-deposit-paid.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 3.7.0
 * @see https://docs.woocommerce.com/document/template-structure/
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes the e-mail header.
 *
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );?>

<?php /* translators: %s: Customer first name */?>
<p><?php printf( esc_html__( 'Hello %s,', 'deposits-for-woocommerce' ), esc_html( $order->get_billing_first_name() ) );?></p>

<p><?php echo $email_text; ?></p>
<?php

/**
 * Hook for the woocommerce_email_order_meta.
 *
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * Hook for the show depsoit details
 */
do_action( 'bayna_email_show_deposit_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * Executes the email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
