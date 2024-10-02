<?php
/**
 * WCM integration module.
 *
 * @package Deposits_WooCommerce\Integrations
 *
 * @link https://wordpress.org/plugins/dc-woocommerce-multi-vendor/
 * @link https://wc-marketplace.com/
 * @since 2.0.3
 */
namespace Deposits_WooCommerce\Integrations;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Wcmp {

	/**
	 * if WCM plugin not found
	 *
	 * @return null
	 */
	public function __construct() {
		if ( ! defined( 'WCMp_PLUGIN_TOKEN' ) ) {
			return; // WCM not active
		}

		add_filter( 'wcmp_product_data_tabs', array( $this, 'productDataTabs' ) );
		add_action( 'wcmp_product_tabs_content', array( $this, 'productDataContent' ), 10, 3 );
		add_action( 'wcmp_process_product_object', array( $this, 'saveProductData' ), 10, 2 );
	}
	/**
	 * @param $product
	 * @param $postData
	 */
	public function saveProductData( $product, $postData ) {
		// var_dump( $postData );

		if ( isset( $postData['post_ID'] ) && isset( $postData['_enable_deposit'] ) ) {
			update_post_meta( absint( $postData['post_ID'] ), '_enable_deposit', $postData['_enable_deposit'] );
		} // enable deposit checkbox

		if ( isset( $postData['post_ID'] ) && isset( $postData['_deposits_value'] ) ) {
			update_post_meta( absint( $postData['post_ID'] ), '_deposits_value', $postData['_deposits_value'] );
		}

		if ( isset( $postData['post_ID'] ) && isset( $postData['_deposits_type'] ) ) {
			update_post_meta( absint( $postData['post_ID'] ), '_deposits_type', $postData['_deposits_type'] );
		}

		if ( isset( $postData['post_ID'] ) && isset( $postData['_enable_deposit'] ) ) {
			update_post_meta( absint( $postData['post_ID'] ), '_enable_deposit', $postData['_enable_deposit'] );
		}
	}
	/**
	 * @param $pro_class_obj
	 * @param $product
	 * @param $post
	 */
	public function productDataContent( $pro_class_obj, $product, $post ) {
		$ed = get_post_meta( $product->get_id(), '_enable_deposit', true );
		$dt = get_post_meta( $product->get_id(), '_deposits_type', true );
		$dv = get_post_meta( $product->get_id(), '_deposits_value', true );

		$dtOptions = array(
			'fixed'   => __( 'Fixed Amount', 'deposits-for-woocommerce' ),
			'percent' => __( 'Percentage of Amount', 'deposits-for-woocommerce' ),
		); // select items for deposit type ?>

			<div role="tabpanel" class="tab-pane fade" id="woo_desposits_options">

				<div class="row-padding">
					<!-- Enable deposit area Start -->
					<div class="form-group">
						<label class="control-label col-sm-3 col-md-3" for="_enable_deposit"><?php esc_html_e( 'Enable Deposit', 'deposits-for-woocommerce' ); ?></label>
						<div class="col-md-6 col-sm-9">
							<input type="hidden" name="_enable_deposit" value="">
							<input type="checkbox" id="enable_deposit" name="_enable_deposit" value="yes">
							<span class="form-text"><?php esc_html_e( 'Enable deposits feature for this product', 'deposits-for-woocommerce' ); ?></span>
						</div>
					</div>
					<!-- Enable deposit area End -->


					<div class="form-group">
						<label class="control-label col-sm-3 col-md-3">Deposit type</label>
						<div class="col-md-6 col-sm-9">
							<select id="deposits_type" name="_deposits_type">
								<?php foreach ( $dtOptions as $key => $value ) { ?>
								<option value="<?php echo $key; ?>" <?php selected( $dt, $key ); ?> ><?php echo $value; ?></option>
								<?php } ?>
							</select>
						</div>
					</div>

					<div  class="form-group">
					<label class="control-label col-sm-3 col-md-3">Deposit Value *</label>
						<div class="col-md-6 col-sm-9">
							<input type="text" name="_deposits_value" class="form-control" value="<?php echo esc_attr( $dv ); ?>" />

						</div>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * @param  $tabs
	 * @return mixed
	 */
	public function productDataTabs( $tabs ) {

		$tabs['deposits'] = array(
			'label'  => __( 'Deposit', 'deposits-for-woocommerce' ),
			'target' => 'woo_desposits_options',
			'class'  => array( 'show_if_simple' ),
		);
		return $tabs;
	}
}
