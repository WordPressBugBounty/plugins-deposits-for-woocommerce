<?php

namespace Deposits_WooCommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DepositColums {
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
		add_action( 'manage_shop_deposit_posts_custom_column', array( $this, 'shop_deposit_column' ), 10, 2 );
		add_action( 'woocommerce_shop_deposit_list_table_custom_column', array( $this, 'shop_deposit_column' ), 10, 2 );

		add_action( 'pre_get_posts', array( $this, 'show_all_orders' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'order_by_columns' ), 10, 1 );
		add_filter( 'views_edit-shop_deposit', array( $this, 'remove_status' ) );
		add_filter( 'manage_shop_deposit_posts_columns', array( $this, 'render_shop_deposit_columns' ) );
		add_filter( 'manage_woocommerce_page_wc-orders--shop_deposit_columns', array( $this, 'render_shop_deposit_columns' ) );
		add_filter( 'manage_edit-shop_deposit_sortable_columns', array( $this, 'sortable_columns' ) );

		add_filter( 'post_row_actions', array( $this, 'remove_row_actions' ), 10, 1 );
		add_filter( 'admin_body_class', array( $this, 'deposit_body_class' ) );
	}
	/**
	 * Columns sorting Query
	 *
	 * @param  object $query
	 * @return void
	 */
	public function order_by_columns( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'deposit' == $orderby ) {
			$query->set( 'meta_key', '_deposit_id' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'parent_order' == $orderby ) {
			$query->set( 'orderby', 'parent' );
		} elseif ( 'deposit_date' == $orderby ) {
			$query->set( 'orderby', 'date' );
		}
	}
	/**
	 * Add sortable option to columns
	 *
	 * @param  array $columns
	 * @return void
	 */
	public function sortable_columns( $columns ) {
		$columns['deposit']      = 'deposit';
		$columns['parent_order'] = 'parent_order';
		$columns['deposit_date'] = 'deposit_date';

		return $columns;
	}
	/**
	 * Add shop order style to depost page
	 *
	 * @param  string $classes
	 * @return void
	 */
	public function deposit_body_class( $classes ) {

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop_deposit' ) : get_post_type();

		if ( 'shop_deposit' === $screen || 'woocommerce_page_wc-orders--shop_deposit' === $screen ) {
			$classes .= ' post-type-shop_order';
		}
		return $classes;
	}

	/**
	 * Remove unwanted or default columns
	 *
	 * @param  array $actions
	 * @return void
	 */
	public function remove_row_actions( $actions ) {
		if ( get_post_type() === 'shop_deposit' ) {
			unset( $actions['edit'] );
			unset( $actions['trash'] );
			unset( $actions['view'] );
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Modify the main query to dispaly every status in 'all' tab
	 *
	 * @param  object $query
	 * @return void
	 */
	public function show_all_orders( $query ) {
		// We have to check if we are in admin and check if current query is the main query and check if you are looking for 'shop_deposit' post type

		if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) == 'shop_deposit' ) {
			if ( ! isset( $_GET['post_status'] ) ) {
				$query->set( 'post_status', 'any' );
			}
		}
	}

	/**
	 * remove status from shop_deposit table
	 *
	 * @param  array $views
	 * @return void
	 */
	public function remove_status( $views ) {
		unset( $views['draft'] );
		unset( $views['publish'] );
		return $views;
	}

	/**
	 * Add the custom columns to the shop_deposit post type:
	 *
	 * @param  array $columns
	 * @return void
	 */
	public function render_shop_deposit_columns( $columns ) {

		$columns['deposit']        = __( 'Deposit Payment', 'deposits-for-woocommerce' );
		$columns['deposit_date']   = __( 'Date', 'deposits-for-woocommerce' );
		$columns['deposit_status'] = __( 'Status', 'deposits-for-woocommerce' );
		$columns['total']          = __( 'Total', 'deposits-for-woocommerce' );
		$columns['parent_order']   = __( 'Parent Order', 'deposits-for-woocommerce' );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			unset( $columns['order_number'] );
			unset( $columns['shipping_address'] );
			unset( $columns['order_date'] );
			unset( $columns['order_status'] );
			unset( $columns['order_total'] );
			unset( $columns['order_total'] );
			unset( $columns['wc_actions'] );

		} else {
			// Traditional CPT-based orders are in use.
			unset( $columns['title'] );
			unset( $columns['date'] );

		}

		return $columns;
	}
	/**
	 * Add the data to the custom columns for the shop_deposit post type:
	 *
	 * @param  loop $column
	 * @param  int  $post_id
	 * @return void
	 */
	public function shop_deposit_column( $column, $post_or_order_object ) {

		$order   = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		$post_id = ( $order instanceof \WC_Order ) ? $order->get_id() : $post_or_order_object;

		$depositOrder = new ShopDeposit( $post_id );
		switch ( $column ) {

			case 'deposit':
				$billing_name      = $depositOrder->get_billing_first_name() . ' ' . $depositOrder->get_billing_last_name();
				$deposit_id_string = '<a href="' . esc_url( admin_url( 'post.php?post=' . $post_id ) . '&action=edit' ) . '" class="order-view"><strong>#' . $depositOrder->get_meta( '_deposit_id' ) . ' ' . $billing_name . '</strong></a>';

				echo apply_filters( 'bayna_deposit_payment_column_id', $deposit_id_string, $depositOrder, $post_id );

				break;
			case 'total':
				$payment_method        = $depositOrder->get_payment_method();
				$payment_method_string = '';
				if ( WC()->payment_gateways() ) {
					$payment_gateways = WC()->payment_gateways->payment_gateways();
				} else {
					$payment_gateways = array();
				}
				if ( $payment_method && 'other' !== $payment_method ) {
					$payment_method_string = sprintf(
					/* translators: %s: payment method */
						__( 'Via %s', 'deposits-for-woocommerce' ),
						esc_html( isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : $payment_method )
					);
				}
				echo wc_price( $depositOrder->get_total() ) . '<small class="meta">' . $payment_method_string . '</small>';

				break;

			case 'deposit_status':
				$depositStatus = $depositOrder->get_status(); // order status
				printf( '<mark class="order-status %s tips"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $depositStatus ) ), wc_get_order_status_name( $depositStatus ) );

				break;

			case 'parent_order':
				$parentId = $depositOrder->get_parent_id(); // order parent

				echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $parentId ) . '&action=edit' ) . '" class="order-view">#' . $parentId . '</a>';

				break;

			case 'deposit_date':
				$order_timestamp = $depositOrder->get_date_created() ? $depositOrder->get_date_created()->getTimestamp() : '';

				if ( ! $order_timestamp ) {
					echo '&ndash;';
					return;
				}

				// Check if the order was created within the last 24 hours, and not in the future.
				if ( $order_timestamp > strtotime( '-1 day', time() ) && $order_timestamp <= time() ) {
					$show_date = sprintf(
					/* translators: %s: human-readable time difference */
						_x( '%s ago', '%s = human-readable time difference', 'deposits-for-woocommerce' ),
						human_time_diff( $depositOrder->get_date_created()->getTimestamp(), time() )
					);
				} else {
					$show_date = $depositOrder->get_date_created()->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', __( 'M j, Y', 'deposits-for-woocommerce' ) ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
				}
				printf(
					'<time datetime="%1$s" title="%2$s">%3$s</time>',
					esc_attr( $depositOrder->get_date_created()->date( 'c' ) ),
					esc_html( $depositOrder->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
					esc_html( $show_date )
				);
				// TODO : replace all depsoit date based on date_paid like this case
				break;

		}
	}
}
