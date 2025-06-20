<?php
namespace Deposits_WooCommerce\Modules;

use Deposits_WooCommerce\Bootstrap;

/**
 * This admin function is loaded from this class
 */

class Admin {

	/**
	 * @return null
	 */
	public function __construct() {

		if ( apply_filters( 'bayna_admin_class_conflict', false ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'adminScripts' ) );
		add_action( 'csf_options_before', array( $this, 'update_notice_option' ) );
		add_action( 'admin_notices', array( $this, 'baynaReview' ) );
		add_action( 'admin_init', array( $this, 'urlParamCheck' ) );
	}

	// notice for option page
	/**
	 * @return null
	 */
	function update_notice_option() {
		$currentScreen = get_current_screen();

		if ( 'codeixer_page_deposits_settings' != $currentScreen->id ) {
			return;
		}
	
		echo '<a class="cit-admin-pro-notice" target="_" href="https://www.codeixer.com/woocommerce-deposits-plugin/?utm_source=settings_page&utm_medium=top_banner&utm_campaign=ltd" target="_blank"><div><p>Is something missing? Uncover even more powerful features by upgrading to the premium version today!</p><small>✨ Secure the lifetime deal at a discounted price before it\'s too late! (save up to $240.00)</small> </div><span>I\'m interested</span></a>';

		if ( Bootstrap::is_checkout_block() || Bootstrap::is_cart_block() ) {
			echo '<div class="notice notice-error notice-alt"><p><strong>Conflict Detected:</strong> We have detected that your store is using the block-based checkout, which is currently incompatible with the Deposit feature. Please switch back to the classic checkout to use the deposit functionality. <a target="_" href="https://www.codeixer.com/docs/switch-back-to-woocommerce-classic-cart-checkout/">Learn more</a></p></div>';

		}
	}

	/**
	 * @return mixed
	 */
	public function get_order_list_before_ver_2() {
		$args      = array(
			'posts_per_page' => -1,
			'meta_key'       => 'deposit_value',
			'meta_value'     => '0',
			'meta_compare'   => '!=',
		);
		$query     = wc_get_orders( $args );
		$order_ids = array();
		foreach ( $query as $obj ) {
			$order_ids[] = $obj->get_id();

		}
		return $order_ids;
	}

	/**
	 * Initializes a singleton instance
	 *
	 * @return $instance
	 */
	public static function init() {

		/**
		 * @var mixed
		 */
		static $instance = false;
		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Admin js and css files
	 *
	 * @return void
	 */
	public function adminScripts() {
		wp_enqueue_style( 'dfwc-admin-fw', CIDW_DEPOSITS_ASSETS . '/css/fw.css', null, CIDW_DEPOSITS_VERSION );
		wp_enqueue_style( 'dfwc-admin', CIDW_DEPOSITS_ASSETS . '/css/dfwc-admin.css', null, CIDW_DEPOSITS_VERSION );
		wp_enqueue_script( 'dfwc-admin', CIDW_DEPOSITS_ASSETS . '/js/admin.js', array( 'jquery' ), CIDW_DEPOSITS_VERSION, true );
	}


	/**
	 * Leave Review Notice
	 *
	 * @return void
	 */
	public function baynaReview() {
		$dismiss_parm = array( 'dfwc-review-dismiss' => '1' );
		$temp_dismiss = array( 'dfwc-review-dismiss-temp' => '1' );

		$datetime1     = new \DateTime( date( 'Y-m-d h:i:s', get_option( 'ci_woo_deposits_installed' ) ) );
		$datetime2     = new \DateTime( date( 'Y-m-d h:i:s' ) );
		$diff_interval = $this->get_days( $datetime1, $datetime2 );

		if ( get_option( 'dfwc_plugin_review' ) || get_transient( 'bayna_review_later' ) ) {
			return;
		} elseif ( $diff_interval > 7 ) {

			?>
		<div class="notice notice-info bayna-review-notice">
		<p><img draggable="false" class="emoji" alt="🎉" src="https://s.w.org/images/core/emoji/11/svg/1f389.svg">  Hi, you're using <strong>Bayna</strong> plugin more than 1 week - that’s awesome! Could you please do me a BIG favor and give the plugin a 5-star rating on WordPress to help us spread the word and boost our motivation.</p>
		<p><strong>~ Niloy, Codeixer</strong></p>
		<p class="dfwc-message-actions">
			<a style="margin-right:8px;" href="https://wordpress.org/support/plugin/deposits-for-woocommerce/reviews/?filter=5#new-post" target="_blank" class="button button-primary">Ok, you deserve it</a>
			<a style="margin-right:8px;" href="<?php echo esc_url( add_query_arg( $temp_dismiss ) ); ?>"  class="button button-primary">Nope, maybe later</a>
			<a href="<?php echo wp_nonce_url( add_query_arg( $dismiss_parm ) ); ?>" class="button">Hide notification</a>
		</p>
		</div>
			<?php
		}
	}
	/**
	 * @param $from_date
	 * @param $to_date
	 */
	public function get_days( $from_date, $to_date ) {
		return round( ( $to_date->format( 'U' ) - $from_date->format( 'U' ) ) / ( 60 * 60 * 24 ) );
	}
	/**
	 * simple dismissable logic
	 *
	 * @return void
	 */
	public function urlParamCheck() {
		if ( isset( $_GET['dfwc-review-dismiss'] ) && 1 == $_GET['dfwc-review-dismiss'] ) {
			update_option( 'dfwc_plugin_review', 1 );
		}
		if ( isset( $_GET['dfwc-review-dismiss-temp'] ) && 1 == $_GET['dfwc-review-dismiss-temp'] ) {
			set_transient( 'bayna_review_later', 1, 2 * WEEK_IN_SECONDS );
		}
	}
}
