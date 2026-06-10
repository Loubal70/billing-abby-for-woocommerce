<?php
/**
 * Income-book sync flow.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use Rankea\BillingAbby\Abby\Client;
use Rankea\BillingAbby\Abby\IncomeMapper;
use Rankea\BillingAbby\Abby\ProductType;
use Rankea\BillingAbby\Support\ApiKey;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Records a paid order in Abby's income book, one entry per product type, idempotently:
 * a retry never books the same type twice.
 */
final class IncomeSync {

	use ReportsFailure;

	private const PAID_SYNCED_META  = '_bafw_abby_paid_synced';
	private const INCOME_TYPES_META = '_bafw_abby_income_types';

	public function is_recorded( WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::PAID_SYNCED_META );
	}

	public function record( WC_Order $order ): void {
		$entries = ( new IncomeMapper() )->entries( $order, $this->product_type_resolver() );

		if ( array() === $entries ) {
			return;
		}

		$this->record_entries( $order, $entries );

		$order->update_meta_data( self::PAID_SYNCED_META, 'yes' );
		$order->save();
	}

	/**
	 * Post each entry, skipping types already recorded so a retry never books twice.
	 *
	 * @param WC_Order                         $order   The paid order.
	 * @param array<int, array<string, mixed>> $entries Income payloads keyed by their productType.
	 */
	private function record_entries( WC_Order $order, array $entries ): void {
		$client   = new Client( ApiKey::get() );
		$recorded = $this->recorded_types( $order );

		foreach ( $entries as $entry ) {
			$type = (int) $entry['productType'];

			if ( in_array( $type, $recorded, true ) ) {
				continue;
			}

			if ( null === $client->record_income( $entry ) ) {
				$this->fail( $order, 'income recording' );
			}

			$recorded[] = $type;
			$order->update_meta_data( self::INCOME_TYPES_META, $recorded );
			$order->save();
		}
	}

	private function product_type_resolver(): callable {
		$default = ProductType::tryFrom( (int) get_option( ProductType::OPTION, ProductType::GOODS->value ) )
			?? ProductType::GOODS;

		return static function ( WC_Order_Item_Product $item ) use ( $default ): ProductType {
			$product = $item->get_product();

			if ( $product instanceof WC_Product ) {
				$override = ProductType::tryFrom( (int) $product->get_meta( ProductType::META ) );

				if ( null !== $override ) {
					return $override;
				}
			}

			return $default;
		};
	}

	private function recorded_types( WC_Order $order ): array {
		$stored = $order->get_meta( self::INCOME_TYPES_META );

		return is_array( $stored ) ? array_map( 'intval', $stored ) : array();
	}
}
