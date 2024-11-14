<?php
namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings {

	public $jvmw_plugin_url;
	public $jvmw_title;
	public $jvmw_activate;
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
		$this->jvmw_data();
		$this->pluginOptions();
		add_action( 'csf_deposits_settings_save_after', array( $this, 'save_after' ) );
	}
	public function save_after( $data ) {

		Product::delete_transients();
	}
	/**
	 * Get data of wishlist plugin
	 *
	 * @return void
	 */
	public function jvmw_data() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php' ) ) {

			$this->jvmw_title      = __( 'Check Options', 'woo-product-gallery-slider' );
			$this->jvmw_activate   = true;
			$this->jvmw_plugin_url = apply_filters( 'cosm_admin_page', admin_url( 'admin.php?page=cixwishlist_settings' ) );

		} elseif ( file_exists( WP_PLUGIN_DIR . '/jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php' ) ) {

			$this->jvmw_title      = __( 'Activate Now', 'woo-product-gallery-slider' );
			$this->jvmw_activate   = false;
			$this->jvmw_plugin_url = wp_nonce_url( 'plugins.php?action=activate&plugin=jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php&plugin_status=all&paged=1', 'activate-plugin_jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php' );

		} else {

			$this->jvmw_title      = __( 'Install Now', 'woo-product-gallery-slider' );
			$this->jvmw_activate   = false;
			$this->jvmw_plugin_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=jvm-woocommerce-wishlist' ), 'install-plugin_jvm-woocommerce-wishlist' );

		}
	}
	public function pluginOptions() {

		// Set a unique slug-like ID
		$prefix = 'deposits_settings';

		//
		// Create options
		\CSF::createOptions(
			$prefix,
			array(
				'menu_title'      => 'Deposit Settings',
				'menu_slug'       => 'deposits_settings',
				'framework_title' => 'Bayna - Deposits & Partial Payments for WooCommerce <small>v' . CIDW_DEPOSITS_VERSION . '</small>',
				'menu_type'       => 'submenu',
				'menu_parent'     => 'codeixer',
				// 'nav'             => 'inline',
				// 'theme'           => 'light',
				'footer_text'     => '',
				// menu extras
				'show_bar_menu'   => false,
			)
		);

		// Create a section
		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'General Settings',
				'icon'   => 'fas fa-sliders-h',
				'fields' => array(
					// A Notice
					array(
						'type'    => 'submessage',
						'style'   => 'info',
						'content' => '<p style="font-size:15px">🎉  We\'re excited to share our new free plugin - <strong>WooCommerce Wishlist</strong>. It\'s a fantastic tool that lets your customers create wishlists and enhances their shopping experience. Give it a try! <a href="' . esc_url( $this->jvmw_plugin_url ) . '">' . esc_html( $this->jvmw_title ) . '</a></p>',
					),

					// A text field
					array(
						'id'      => 'select_mode',
						'type'    => 'radio',
						'title'   => __( 'Deposit Mode', 'deposits-for-woocommerce' ),
						'options' => array(
							'only_deposits' => __( 'Order only deposit products or regular ones', 'deposits-for-woocommerce' ),
							'allow_mix'     => __( 'Allow Deposits and regular items together into an order', 'deposits-for-woocommerce' ),

						),
						'default' => 'only_deposits',
					),
					array(
						'id'    => 'global_deposits_mode',
						'type'  => 'switcher',
						'title' => __( 'Deposits for All Products', 'deposits-for-woocommerce' ),
						'label' => __( 'Override shop products by percentage of amount.', 'deposits-for-woocommerce' ),

					),
					array(
						'id'         => 'global_force_deposit',
						'type'       => 'switcher',
						'title'      => __( 'Force Deposit', 'deposits-for-woocommerce' ),
						'label'      => __( 'Only deposit payment is allow for this shop.', 'deposits-for-woocommerce' ),
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),

					),
					array(
						'id'         => 'global_hide_deposit_input',
						'type'       => 'switcher',
						'title'      => __( 'Hide Checkbox', 'deposits-for-woocommerce' ),
						'label'      => __( 'Hide ( Full Payment & Pay Deposit ) Checkbox from single product page.', 'deposits-for-woocommerce' ),
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),

					),
					array(
						'id'         => 'global_deposits_value',
						'type'       => 'number',
						'title'      => __( 'Deposits Value', 'deposits-for-woocommerce' ),
						'desc'       => __( 'Enter the value for deposit.', 'deposits-for-woocommerce' ),
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),
					),
					array(
						'id'    => 'required_login',
						'type'  => 'switcher',
						'title' => __( 'Required Login', 'deposits-for-woocommerce' ),
						'desc'  => __( 'Deposit only be allowed after signing in.', 'deposits-for-woocommerce' ),
						'class' => 'cix-only-pro',

					),
					array(
						'id'          => 'global_deposits_exclude_products',
						'type'        => 'select',
						'title'       => __( 'Exclude products', 'deposits-for-woocommerce' ),

						'placeholder' => 'Search for a product...',
						'class'       => 'cix-only-pro',

						'options'     => array(),

						'subtitle'    => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',

					),
					array(

						'id'      => 'predefine_full_payment',
						'type'    => 'select',
						'title'   => __( 'Default Selection', 'deposits-for-woocommerce' ),
						'default' => '0',
						'options' => array(
							'0' => __( 'Pay Deposit', 'deposits-for-woocommerce' ),
							'1' => __( 'Full Payment', 'deposits-for-woocommerce' ),
						),

					),
					array(
						'id'       => 'variable_product_mode',
						'type'     => 'select',
						'title'    => __( 'Deposit for Variable Products', 'deposits-for-woocommerce' ),
						'default'  => 'parent',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'options'  => array(
							'child'  => __( 'Deposit options are move under each variables', 'deposits-for-woocommerce' ),
							'parent' => __( 'All variations are overridden with parent deposit options', 'deposits-for-woocommerce' ),
						),
					),
					array(
						'id'      => 'bayna_fully_paid_status',
						'type'    => 'select',
						'default' => 'wc-completed',
						'options' => 'bayna_order_status',
						'title'   => __( 'Deposit Paid Status', 'deposits-for-woocommerce' ),
						'desc'    => __( 'set order status when deposits are paid', 'deposits-for-woocommerce' ),
					),
					array(
						'id'      => 'cidw_payment_gateway',
						'type'    => 'checkbox',
						'title'   => __( 'Disable Payment Methods	', 'deposits-for-woocommerce' ),
						'options' => 'cidw_payment_gateway_list',
					),

				),
			)
		);

		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'Advanced Settings',
				'icon'   => 'fas fa-sliders-h',
				'fields' => array(

					array(
						'id'       => 'cidw_deposit_reminder_type',
						'type'     => 'select',
						'default'  => 'dynamic',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'options'  => array(
							'dynamic' => __( 'Days after the first payment', 'deposits-for-woocommerce' ),
							'status'  => __( 'Based on Order Status', 'deposits-for-woocommerce' ),
							// 'fixed' => __('Fixed Date', 'deposits-for-woocommerce'),

						),
						'title'    => 'Send Due Deposit Payment Reminder',
						'desc'     => __( 'The due date is the date you want your customer to pay you. <a href="https://www.codeixer.com/docs/how-to-set-up-deposit-payment-reminders-for-customers/" target="_blank">Learn More</a>.', 'deposits-for-woocommerce' ),
						// <br>Note: if you select fixed that then it\'s must need update montly'
					),
					array(
						'id'       => 'cancel_deposits_order',
						'type'     => 'number',
						'default'  => '0',

						'title'    => 'Cancel Order',
						'desc'     => 'Cancel Due deposit order(s) after a certain days of order placed.<br> Default: 0 = orders will not cancel.',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
					),
					array(
						'id'         => 'cidw_deposit_reminder_dynamic_date',
						'type'       => 'number',
						'default'    => '',
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'      => __( 'Set Remainder Date', 'deposits-for-woocommerce' ),
						'desc'       => __( 'Days after the first payment', 'deposits-for-woocommerce' ),
						'dependency' => array( 'cidw_deposit_reminder_type', '==', 'dynamic' ),

					),

					array(
						'id'       => 'cidw_payment_subsequent_gateways',
						'type'     => 'radio',
						'title'    => __( 'Subsequent Payment Methods', 'deposits-for-woocommerce' ),
						'default'  => '0',
						'inline'   => true,
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'class'    => 'cix-only-pro',
						'options'  => array(
							'0'      => __( 'Disable', 'deposits-for-woocommerce' ),
							'1'      => __( 'Enable', 'deposits-for-woocommerce' ),
							'custom' => __( 'Specific Payment Methods', 'deposits-for-woocommerce' ),
						),

						'desc'     => __(
							'- If the option is disabled, all payment methods will be presented on the deposit checkout page.<br> 
						- If enabled, payment methods for deposit orders will be deactivated in the same manner as the initial order. "General Settings > Disable Payment Methods"<br> 
						- When "Specific Payment Methods" is chosen, it allows for the selective activation of specific gateways for future payments.<br>
						<i> Default: Disable</i>',
							'deposits-for-woocommerce'
						),
					),

				),
			)
		);
		\CSF::createSection(
			$prefix,
			array(

				'title'  => 'Checkout Mode',
				'icon'   => 'fas fa-money-check',
				'fields' => array(
					// A Notice
					array(
						'type'       => 'subheading',
						'content'    => 'Product-level deposit calculation is disabled during checkout mode. <a href="https://www.codeixer.com/docs/enable-cart-based-deposit/" target="_blank">Learn More</a>',
						'class'      => 'cix-only-pro',
						'dependency' => array( 'checkout_mode', '==', 'true' ),
					),

					array(
						'id'       => 'checkout_mode',
						'type'     => 'switcher',
						'title'    => __( 'Enable Checkout Mode', 'deposits-for-woocommerce' ),
						'default'  => '1',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => __( 'Activate the checkout mode to adjust deposit calculations based on the total amount at checkout rather than on a per-product basis.', 'deposits-for-woocommerce' ),

					),
					array(
						'id'         => 'checkout_force_deposit',
						'type'       => 'switcher',
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'      => __( 'Force Deposit', 'deposits-for-woocommerce' ),
						'desc'       => __( 'By activating "Force Deposit" customers will be restricted from making full payments during checkout.', 'deposits-for-woocommerce' ),
						'dependency' => array( 'checkout_mode', '==', 'true' ),

					),
					// add selete option for fixed and percentage
					array(
						'id'         => 'checkout_deposits_type',
						'type'       => 'select',
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'      => __( 'Deposit Type', 'deposits-for-woocommerce' ),
						'options'    => array(
							'fixed'      => __( 'Fixed', 'deposits-for-woocommerce' ),
							'percentage' => __( 'Percentage', 'deposits-for-woocommerce' ),
						),
						'dependency' => array( 'checkout_mode', '==', 'true' ),
					),
					array(
						'id'         => 'checkout_deposits_value',
						'type'       => 'number',
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'      => __( 'Deposits Value', 'deposits-for-woocommerce' ),
						'default'    => '50',
						'desc'       => __( 'The deposit amount should not exceed 99% for percentage deposits or surpass the total order amount for fixed deposits.', 'deposits-for-woocommerce' ),
						'dependency' => array( 'checkout_mode', '==', 'true' ),
					),

				),
			)
		);
		\CSF::createSection(
			$prefix,
			array(

				'title'  => 'Collection Settings',
				'icon'   => 'fas fa-hand-holding-usd',
				'fields' => array(

					array(
						'id'       => 'exclude_shipping_fee',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'    => __( 'Shipping Handling', 'deposits-for-woocommerce' ),
						'desc'     => __( 'Choose how to handle shipping.', 'deposits-for-woocommerce' ),
						'options'  => array(
							0 => __( 'With Deposit', 'deposits-for-woocommerce' ),
							1 => __( 'With Future Payment', 'deposits-for-woocommerce' ),
							// 'split' => __( 'Split', 'deposits-for-woocommerce' ),

						),

					),
					array(
						'id'       => 'deposit_tax',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => __( 'Choose how to handle taxes.', 'deposits-for-woocommerce' ),
						'title'    => __( 'Tax Collection', 'deposits-for-woocommerce' ),
						'options'  => array(
							'wdp' => __( 'With Deposit', 'deposits-for-woocommerce' ),
							'wfp' => __( 'With Future Payment', 'deposits-for-woocommerce' ),
							// 'split' => __( 'Split', 'deposits-for-woocommerce' ),

						),

					),
					array(
						'id'       => 'deposit_fees',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => __( 'Choose how to Fees.', 'deposits-for-woocommerce' ),
						'title'    => __( 'Fees Collection', 'deposits-for-woocommerce' ),
						'options'  => array(
							'wdp' => __( 'With Deposit', 'deposits-for-woocommerce' ),
							'wfp' => __( 'With Future Payment', 'deposits-for-woocommerce' ),
							// 'split' => __( 'Split', 'deposits-for-woocommerce' ),

						),

					),

				),
			)
		);
		// Create a section
		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'Text & Labels',
				'icon'   => 'fas fa-sliders-h',
				'fields' => array(
					array(
						'id'      => 'regular_notice',
						'type'    => 'text',
						'title'   => __( 'Regular Products Notice', 'deposits-for-woocommerce' ),

						'default' => 'We detected that your cart has Regular products. Please remove them before being able to add this product.',
					),
					array(
						'id'      => 'deposit_notice',
						'type'    => 'text',
						'title'   => __( 'Deposit Products Notice', 'deposits-for-woocommerce' ),

						'default' => 'We detected that your cart has Deposit products. Please remove them before being able to add this product.',
					),
					array(
						'id'      => 'txt_pay_deposit',
						'type'    => 'text',
						'title'   => __( 'Pay Deposit', 'deposits-for-woocommerce' ),
						'default' => 'Pay Deposit',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_full_payment',
						'type'    => 'text',
						'title'   => __( 'Full Payment', 'deposits-for-woocommerce' ),
						'default' => 'Full Payment',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'          => 'txt_deposit_msg',
						'type'        => 'text',
						'title'       => __( 'Deposit Text', 'deposits-for-woocommerce' ),
						'default'     => 'Deposit : {price} Per item',
						'placeholder' => 'Deposit : {price} Per item',
						'class'       => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_to_deposit_paid',
						'type'    => 'text',
						'title'   => __( 'Paid', 'deposits-for-woocommerce' ),
						'default' => 'Paid:',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_to_pay',
						'type'    => 'text',
						'title'   => __( 'To Pay', 'deposits-for-woocommerce' ),
						'default' => 'To Pay:',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_deposit',
						'type'    => 'text',
						'title'   => __( 'Deposit', 'deposits-for-woocommerce' ),
						'default' => 'Deposit',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_due_payment',
						'type'    => 'text',
						'title'   => __( 'Due Payment', 'deposits-for-woocommerce' ),
						'default' => 'Due Payment:',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_deposit_payment_fee',
						'type'    => 'text',
						'title'   => __( 'Deposit Payment Fee', 'deposits-for-woocommerce' ),
						'default' => 'Deposit Payment for order ',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_due_payment_fee',
						'type'    => 'text',
						'title'   => __( 'Due Payment Fee', 'deposits-for-woocommerce' ),
						'default' => 'Due Payment for order ',
						'class'   => 'dfwc-text-field',
					),

				),
			)
		);

		// Create a section
		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'Radio Style',
				'icon'   => 'fas fa-sliders-h',
				'fields' => array(

					array(
						'id'      => 'cidw_box_active_color',
						'type'    => 'color',
						'title'   => 'Active Color',
						'default' => '#5cb85c',
					),
					array(
						'id'      => 'cidw_radio_theme',
						'type'    => 'radio',
						'default' => 'p-default p-round p-thick',
						'title'   => __( 'Radio Style', 'deposits-for-woocommerce' ),
						'options' => array(
							'p-default p-round p-thick' => 'Round & Thick & Outline',
							'p-default p-round p-fill'  => 'Round & Fill',
							'p-default p-round'         => 'Round',
							'p-default p-curve p-thick' => 'Curve & Thick & Outline',
							'p-default p-curve'         => 'Curve & Outline',
							'p-default p-thick'         => 'Square & Thick & Outline',
							'p-default p-fill'          => 'Square',
							'p-image p-plain'           => 'Image',
						),
					),
					array(
						'id'           => 'cidw_radio_theme_image',
						'type'         => 'upload',
						'title'        => 'Add Radio Image',
						'library'      => 'image',
						'button_title' => 'Add Image',
						'default'      => CIDW_DEPOSITS_ASSETS . '/img/004.png',
						'remove_title' => 'Remove Image',
						'dependency'   => array( 'cidw_radio_theme', '==', 'p-image p-plain' ),
					),

				),
			)
		);
		// add backup
		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'Backup',
				'icon'   => 'fas fa-sliders-h',
				'fields' => array(
					array(
						'id'    => 'backup',
						'type'  => 'backup',
						'title' => 'Backup and Restore',
					),
				),
			)
		);
	}
}
