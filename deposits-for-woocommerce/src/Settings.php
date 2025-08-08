<?php
namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings {

	public $jvmw_plugin_url;
	public $jvmw_title;
	public $jvmw_activate;
	protected $text_disabled = '__disabled';
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
	/**
	 * Generates premiun row disabled for email templates tab
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function generate_premium_row_disabled_for_email_templates_tab( $template_name ) {

		?><div class="csf-email-templates  cix-only-pro" style="color: black;cursor: not-allowed;" href='#'>
			<div><?php echo $template_name; ?></div>
			<div class="csf-subtitle-text">
				Available in <a style="text-decoration: none;" href='https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro'>Pro Version! <i class='fas fa-lock'></i></a>
			</div>
		</div>
		<?php
	}
	/**
	 * This function generates all the content for the Email Templates tab within the plugin settings
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function email_list() {

		$templates = array(
			'wc_new_deposit_alert'      => 'New Deposit - Admin',
			'wc_customer_deposit_alert' => 'Deposit Order - Customer',
			'wc_customer_deposit_order' => 'Deposit Paid - Customer',
			'wc_deposit_full_paid'      => 'Deposit Full Paid - Admin',
			'wc_customer_deposit_reminder' . $this->text_disabled => 'Deposit Reminder - Customers',

		);
		foreach ( $templates as $key => $template_name ) {

			if ( strpos( $key, $this->text_disabled ) !== false ) {
				$this->generate_premium_row_disabled_for_email_templates_tab( $template_name );
				continue;
			}

			echo '<a class="csf-email-templates" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . $key ) ) . '">' . $template_name . '</a>';
		}
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

			$this->jvmw_title      = 'Check Options';
			$this->jvmw_activate   = true;
			$this->jvmw_plugin_url = apply_filters( 'cosm_admin_page', admin_url( 'admin.php?page=cixwishlist_settings' ) );

		} elseif ( file_exists( WP_PLUGIN_DIR . '/jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php' ) ) {

			$this->jvmw_title      = 'Activate Now';
			$this->jvmw_activate   = false;
			$this->jvmw_plugin_url = wp_nonce_url( 'plugins.php?action=activate&plugin=jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php&plugin_status=all&paged=1', 'activate-plugin_jvm-woocommerce-wishlist/jvm-woocommerce-wishlist.php' );

		} else {

			$this->jvmw_title      = 'Install Now';
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
				'framework_title' => 'Bayna - Deposits & Partial Payments for WooCommerce <small>v' . CIDW_DEPOSITS_VERSION . '</small><br><a href="https://www.codeixer.com/docs-category/bayna-woocommerce-deposit/" target="_" class="button">Docs</a><a href="https://codeixer.com/contact-us/" target="_" class="button button-primary" style="margin-left:7px">Help & Support</a>',
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

					// A text field
					array(
						'id'      => 'select_mode',
						'type'    => 'radio',
						'title'   => 'Deposit Mode',
						'options' => array(
							'only_deposits' => 'Order only deposit products or regular ones',
							'allow_mix'     => 'Allow Deposits and regular items together into an order',

						),
						'default' => 'only_deposits',
					),

					array(
						'id'    => 'global_deposits_mode',
						'type'  => 'switcher',
						'title' => 'Deposits for All Products',
						'label' => 'Override shop products by percentage of amount.',

					),

					array(
						'id'         => 'global_deposits_value',
						'type'       => 'number',
						'title'      => 'Deposits Value',
						'desc'       => 'Enter the percentage value for deposit.',
						'default'    => '50',
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),
					),
					array(
						'id'         => 'global_force_deposit',
						'type'       => 'switcher',
						'title'      => 'Force Deposit',
						'label'      => 'Only deposit payment is allow for this shop.',
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),

					),
					array(
						'id'         => 'global_hide_deposit_input',
						'type'       => 'switcher',
						'title'      => 'Hide Checkbox',
						'label'      => 'Hide ( Full Payment & Pay Deposit ) Checkbox from single product page.',
						'class'      => 'cix-only-pro',
						'subtitle'   => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'dependency' => array( 'global_deposits_mode', '==', 'true' ),

					),

					array(
						'id'      => 'required_login',
						'type'    => 'switcher',
						'title'   => 'Required Login',
						'desc'    => 'Deposit only be allowed after signing in.',
						'default' => '0',

					),

					array(

						'id'      => 'predefine_full_payment',
						'type'    => 'select',
						'title'   => 'Default Selection',
						'default' => '0',
						'options' => array(
							'0' => 'Pay Deposit',
							'1' => 'Full Payment',
						),

					),

					array(
						'id'      => 'bayna_fully_paid_status',
						'type'    => 'select',
						'default' => 'wc-completed',
						'options' => 'bayna_order_status',
						'title'   => 'Deposit Paid Status',
						'desc'    => 'set order status when deposits are paid',
					),
					array(
						'id'      => 'cidw_payment_gateway',
						'type'    => 'checkbox',
						'title'   => 'Disable Payment Methods	',
						'options' => 'cidw_payment_gateway_list',
					),
					array(

						'id'       => 'deposit_charge_method',
						'type'     => 'select',
						'title'    => 'Deposit Charge Method',
						'default'  => 'only_deposit',
						'options'  => array(
							'only_deposit'    => 'Only deposit product amount',
							'full_cart_total' => 'Full cart total amount',
						),
						'desc'     => 'If you choose "Only deposit product amount," the deposit will be determined solely by the product\'s deposit amount, while the amounts for regular items will be included in future deposit orders.<br> Conversely, if you select "Full cart total amount," the deposit will include both the product deposit amount and the prices of other regular items.',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',

					),
					array(
						'id'          => 'global_deposits_exclude_products',
						'type'        => 'select',
						'title'       => 'Exclude products',

						'placeholder' => 'Search for a product...',
						'class'       => 'cix-only-pro',

						'options'     => array(),

						'subtitle'    => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',

					),
					array(
						'id'       => 'variable_product_mode',
						'type'     => 'select',
						'title'    => 'Deposit Options for Variable Products',
						'default'  => 'parent',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'options'  => array(
							'child'  => 'Deposit options are move under each variables',
							'parent' => 'All variations are overridden with parent deposit options',
						),
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
							'dynamic' => 'Days after the first payment',
							'status'  => 'Based on Order Status',
							// 'fixed' => 'Fixed Date',

						),
						'title'    => 'Send Due Deposit Payment Reminder',
						'desc'     => 'The due date is the date you want your customer to pay you. <a href="https://www.codeixer.com/docs/how-to-set-up-deposit-payment-reminders-for-customers/" target="_blank">Learn More</a>.',
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
						'title'      => 'Set Remainder Date',
						'desc'       => 'Days after the first payment',
						'dependency' => array( 'cidw_deposit_reminder_type', '==', 'dynamic' ),

					),

					array(
						'id'       => 'cidw_payment_subsequent_gateways',
						'type'     => 'radio',
						'title'    => 'Subsequent Payment Methods',
						'default'  => '0',
						'inline'   => true,
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'class'    => 'cix-only-pro',
						'options'  => array(
							'0'      => 'Disable',
							'1'      => 'Enable',
							'custom' => 'Specific Payment Methods',
						),

						'desc'     =>
							'- If the option is disabled, all payment methods will be presented on the deposit checkout page.<br> 
						- If enabled, payment methods for deposit orders will be deactivated in the same manner as the initial order. "General Settings > Disable Payment Methods"<br> 
						- When "Specific Payment Methods" is chosen, it allows for the selective activation of specific gateways for future payments.<br>
						<i> Default: Disable</i>',
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
						'type'    => 'submessage',
						'style'   => 'warning',
						'content' => 'Enable deposit only for checkout page instead of product page. <a href="https://www.codeixer.com/docs/enable-cart-based-deposit?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro" target="_blank">Learn More</a>',
					),

					array(
						'id'       => 'checkout_mode',
						'type'     => 'switcher',
						'title'    => 'Enable Checkout Mode',
						'default'  => '0',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => 'Activate the checkout mode to adjust deposit calculations based on the total amount at checkout rather than on a per-product basis.',

					),
					array(
						'id'       => 'checkout_force_deposit',
						'type'     => 'switcher',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'    => 'Force Deposit',
						'desc'     => 'By activating "Force Deposit" customers will be restricted from making full payments during checkout.',

					),
					// add selete option for fixed and percentage
					array(
						'id'       => 'checkout_deposits_type',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'    => 'Deposit Type',
						'options'  => array(
							'fixed'      => 'Fixed',
							'percentage' => 'Percentage',
						),

					),
					array(
						'id'       => 'checkout_deposits_value',
						'type'     => 'number',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'title'    => 'Deposits Value',
						'default'  => '50',
						'desc'     => 'The deposit amount should not exceed 99% for percentage deposits or surpass the total order amount for fixed deposits.',

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
						
						'title'    => 'Shipping Handling',
						'desc'     => 'Choose how to handle shipping.',
						'options'  => array(
							0 => 'With Deposit',
							1 => 'With Future Payment',
							// 'split' => 'Split',

						),

					),
					array(
						'id'       => 'deposit_tax',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => 'Choose how to handle taxes.',
						'title'    => 'Tax Collection',
						'options'  => array(
							'wdp' => 'With Deposit',
							'wfp' => 'With Future Payment',
							// 'split' => 'Split',

						),

					),
					array(
						'id'       => 'deposit_fees',
						'type'     => 'select',
						'class'    => 'cix-only-pro',
						'subtitle' => 'Available in <a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro">Pro Version!</a>',
						'desc'     => 'Choose how to Fees.',
						'title'    => 'Fees Collection',
						'options'  => array(
							'wdp' => 'With Deposit',
							'wfp' => 'With Future Payment',
							// 'split' => 'Split',

						),

					),

				),
			)
		);
		// Email Tempaltes
		\CSF::createSection(
			$prefix,
			array(
				'title'  => 'Email Templates',
				'icon'   => 'fas fa-envelope',
				'fields' => array(

					// A Callback Field Example
					array(
						'type'     => 'callback',
						'function' => array( $this, 'email_list' ),
					),
					array(
						'type'    => 'submessage',
						'style'   => 'warning',
						'content' => 'Need to translate email templates in a different language? <a href="https://www.codeixer.com/docs/translation-using-loco-translate?utm_source=freemium&utm_medium=settings_page&utm_campaign=upgrade_pro" target="_blank">Learn More</a>',
					),
				),
			),
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
						'title'   => 'Regular Products Notice',

						'default' => 'We detected that your cart has Regular products. Please remove them before being able to add this product.',
					),
					array(
						'id'      => 'deposit_notice',
						'type'    => 'text',
						'title'   => 'Deposit Products Notice',

						'default' => 'We detected that your cart has Deposit products. Please remove them before being able to add this product.',
					),
					array(
						'id'      => 'txt_pay_deposit',
						'type'    => 'text',
						'title'   => 'Pay Deposit',
						'default' => 'Pay Deposit',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_full_payment',
						'type'    => 'text',
						'title'   => 'Full Payment',
						'default' => 'Full Payment',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'          => 'txt_deposit_msg',
						'type'        => 'text',
						'title'       => 'Deposit Text',
						'default'     => 'Deposit : {price} Per item',
						'placeholder' => 'Deposit : {price} Per item',
						'class'       => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_to_deposit_paid',
						'type'    => 'text',
						'title'   => 'Paid',
						'default' => 'Paid:',
						'class'   => 'dfwc-text-field',
					),

					array(
						'id'      => 'txt_to_pay',
						'type'    => 'text',
						'title'   => 'To Pay',
						'default' => 'To Pay:',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_deposit',
						'type'    => 'text',
						'title'   => 'Deposit',
						'default' => 'Deposit',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_due_payment',
						'type'    => 'text',
						'title'   => 'Due Payment',
						'default' => 'Due Payment:',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_deposit_payment_fee',
						'type'    => 'text',
						'title'   => 'Deposit Payment Fee',
						'default' => 'Deposit Payment for order ',
						'class'   => 'dfwc-text-field',
					),
					array(
						'id'      => 'txt_to_due_payment_fee',
						'type'    => 'text',
						'title'   => 'Due Payment Fee',
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
						'title'   => 'Radio Style',
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
