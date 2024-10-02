<?php

namespace Deposits_WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SubMenu {

	/**
	 * Autoload method
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'codeixer_sub_menu', array( $this, 'register_sub_menu' ), 100 );
	}

	/**
	 * Register submenu
	 *
	 * @return void
	 */
	public function register_sub_menu() {
	}

	/**
	 * Render submenu
	 *
	 * @return void
	 */
	public function submenu_page_callback() {

		if ( isset( $_GET['subpage'] ) && 'bayna' == $_GET['subpage'] ) {
			?>
		<div class="cit-upgrade-sticky">
			<div class="fw-row">
				<div class="fw-col-md-6">
					<img class="fw-pull-left" src="https://ps.w.org/deposits-for-woocommerce/assets/icon-256x256.png?rev=2408683"/>
					<h3>Bayna - Deposits & Partial Payments for WooCommerce</h3>
				</div>
				<div class="fw-col-md-6 buttons-area">
					<a href="https://www.codeixer.com/contact-us/" target="_blank" class="cit-button-2"><span class="dashicons dashicons-email-alt"></span>Support</a>

					<a href="https://www.codeixer.com/woocommerce-deposits-plugin/" target="_blank" class="cit-button-1"><span class="dashicons dashicons dashicons-upload"></span>Upgrade to PRO</a>

				</div>
			</div>
		</div>
		<h2 class="wp-cit-heading">Upgrade to Premium</h3>
		<div class="fw-container">
			<div class="fw-row">
				<div class="fw-col-md-4">
					<div class="cit-item-feature">
						<h4>Allow to force users to pay a deposit during the purchase.</h4>
						<p>Customers not having to pay the total product price while they buy. What’s more, it allows you to give an amount they need to pay upfront and then collect the rest of the amount at predefined intervals.</p>
					</div>
					<div class="cit-item-feature">
						<h4>Fixed Payment Gateways option for deposit orders.</h4>
						<p>Allow customers to pay only with the payment gateways which are you predefined in deposit settings. </p>
					</div>
					<div class="cit-item-feature">
						<ul class="ha-feature-list-wrap">

							<li class="ha-list-item">
							Sortable Deposit Report

							</li>
							<li class="ha-list-item">

							Payment Reminder Email
							</li>
							<li class="ha-list-item">

							Global Deposit Option
							</li>
							<li class="ha-list-item">

							Cancel Due Order(s) after a certain days of order placed
							</li>

							</ul>
					</div>
				</div>
				<div class="fw-col-md-4">
					<div class="cit-item-feature">
						<h4>Automatic/Manual email reminders to pay the remaining amount.</h4>
						<p>Predefined deposit due date option is available in the settings for automatically send the invoice to the customers. “Deposit Reminder” option is also available under the order action so it’s easy to send reminder easy at anytime.</p>
					</div>

					<div class="cit-item-feature">
						<h4>Allow Email notifications for both users and admin.</h4>
						<p>Both will get an email when a Deposit order will placed.
						Customers are also get email notification for all Deposit related actions.</p>
					</div>

					<div class="cit-item-feature">
						<ul class="ha-feature-list-wrap">

							<li class="ha-list-item">

							Translation Ready

							</li>
							<li class="ha-list-item">

							2 Deposit Mode for Add Products Into Cart

							</li>
							<li class="ha-list-item">

							Exclude Shipping Fee
							</li>
							<li class="ha-list-item">

							Enable/Disable Tooltip on Cart &amp; Checkout page
							</li>

						</ul>
					</div>
				</div>
				<div class="fw-col-md-4">
					<div class="cit-item-action">
						<h3>Ready to take full advantage of the premium version?</h3>
						<img style="width:100%" src="https://www.codeixer.com/wp-content/uploads/2021/11/1744_banner_02.png" />
						<a target="_blank" href="https://www.codeixer.com/woocommerce-deposits-plugin/#pricing" class="cit-action-btn">Get started</a>
						<p>Prices starting from $49</p>
					</div>
				</div>
			</div>
		</div>
			<?php
		}
		echo '<div class="wrap cit-wrap">';

		echo '</div>';
	}
}
