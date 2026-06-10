<?php
/**
 * Abby income product types.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

defined( 'ABSPATH' ) || exit;

/**
 * Abby income-book product types (the BIC/BNC accounting category), confirmed against the
 * Abby UI. OPTION is the shop-wide default; META is the per-product override key.
 */
enum ProductType: int {

	public const OPTION = 'bafw_product_type';
	public const META   = '_bafw_product_type';

	case GOODS              = 1;
	case SERVICES           = 2;
	case CRAFT_SERVICES     = 3;
	case MANUFACTURED_GOODS = 4;
	case DISBURSEMENT       = 5;

	/**
	 * Value/label pairs for a settings dropdown.
	 *
	 * @return array<int, array{value: int, label: string}>
	 */
	public static function options(): array {
		return array_map(
			static fn ( ProductType $type ): array => array(
				'value' => $type->value,
				'label' => self::label( $type ),
			),
			self::cases()
		);
	}

	private static function label( ProductType $type ): string {
		return match ( $type ) {
			self::GOODS              => __( 'Sale of goods (BIC)', 'billing-abby-for-woocommerce' ),
			self::SERVICES           => __( 'Provision of services (BNC)', 'billing-abby-for-woocommerce' ),
			self::CRAFT_SERVICES     => __( 'Craft or commercial services (BIC)', 'billing-abby-for-woocommerce' ),
			self::MANUFACTURED_GOODS => __( 'Sale of manufactured goods (BIC)', 'billing-abby-for-woocommerce' ),
			self::DISBURSEMENT       => __( 'Disbursement', 'billing-abby-for-woocommerce' ),
		};
	}
}
