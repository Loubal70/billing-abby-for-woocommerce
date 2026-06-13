<?php
/**
 * Per-product Abby income type field.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Rankea\BillingAbby\Abby\ProductType;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "Abby income type" select on the product edit screen, overriding the shop default.
 */
final class ProductFields {

	public function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_field' ) );
	}

	public function render_field(): void {
		$choices = array( '' => __( 'Use the shop default', 'billing-abby-for-woocommerce' ) );

		foreach ( ProductType::options() as $option ) {
			$choices[ (string) $option['value'] ] = $option['label'];
		}

		woocommerce_wp_select(
			array(
				'id'          => ProductType::META,
				'label'       => __( 'Abby income type', 'billing-abby-for-woocommerce' ),
				'options'     => $choices,
				'desc_tip'    => true,
				'description' => __( 'Accounting category recorded in Abby when an order with this product is paid.', 'billing-abby-for-woocommerce' ),
			)
		);
	}

	public function save_field( WC_Product $product ): void {
		// WooCommerce verifies the product-edit nonce before this hook fires.
		$value = isset( $_POST[ ProductType::META ] ) ? absint( wp_unslash( $_POST[ ProductType::META ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( null !== ProductType::tryFrom( $value ) ) {
			$product->update_meta_data( ProductType::META, $value );
		} else {
			$product->delete_meta_data( ProductType::META );
		}
	}
}
