<?php
namespace Deposits_WooCommerce\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'DepositPaid', false ) ) :

	/**
	 * Customer Completed Order Email.
	 *
	 * Order complete emails are sent to the customer when the order is marked complete and usual indicates that the order has been shipped.
	 *
	 * @class       WC_Email_Customer_Completed_Order
	 * @version     2.0.0
	 * @extends     WC_Email
	 * @package     WooCommerce\Classes\Emails
	 */
	class DepositPaid extends \WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'customer_completed_deposit_order';
			$this->customer_email = true;
			$this->title          = __( 'Deposit paid', 'deposits-for-woocommerce' );
			$this->description    = __( 'Deposit order paid emails are sent to customers when their deposit are marked completed.', 'deposits-for-woocommerce' );
			$this->template_html  = 'emails/customer-deposit-paid.php';
			$this->template_plain = 'emails/plain/customer-deposit-paid.php';
			$this->placeholders   = array(
				'{order_date}'          => '',
				'{site_title}'          => '',
				'{order_parent_number}' => '',
				'{deposit_payment_id}'  => '',
			);

			// Call parent constructor.
			parent::__construct();
			$this->template_base = CIDW_TEMPLATE_PATH;
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int            $order_id The order ID.
		 * @param WC_Order|false $order    Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                                = $order;
				$this->recipient                             = $this->object->get_billing_email();
				$this->placeholders['{order_date}']          = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_parent_number}'] = $this->object->get_parent_id();
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Your {site_title} Deposit receipt for order #{order_parent_number}', 'deposits-for-woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Thank you for your payment', 'deposits-for-woocommerce' );
		}
		/**
		 * Get email Text.
		 *
		 * @since  3.1.0
		 *
		 * @return string
		 */
		public function get_default_email_text() {
			return __( 'Your payment has been received. Your order details are shown below for your reference:', 'deposits-for-woocommerce' );
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'email_text'         => $this->get_default_email_text(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'email_text'         => $this->get_default_email_text(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}
		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			/* translators: %s: list of placeholders */
			$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'deposits-for-woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'deposits-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'deposits-for-woocommerce' ),
					'default' => 'yes',
				),

				'subject'            => array(
					'title'       => __( 'Subject', 'deposits-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'deposits-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_text'         => array(
					'title'       => __( 'Email Text', 'deposits-for-woocommerce' ),
					'description' => __( 'Text to appear before order details', 'deposits-for-woocommerce' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'Your payment has been received. Your order details are shown below for your reference:', 'deposits-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_email_text(),
					'desc_tip'    => true,
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'deposits-for-woocommerce' ),
					'description' => __( 'Text to appear below the main email content.', 'deposits-for-woocommerce' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'deposits-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
					'title'       => __( 'Email type', 'deposits-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'deposits-for-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
		/**
		 * Default content to show below main email content.
		 *
		 * @since 3.7.0
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return;
		}
	}

endif;
