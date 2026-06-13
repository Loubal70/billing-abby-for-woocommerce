<?php
/**
 * Abby sync state shown on the order screen.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

defined( 'ABSPATH' ) || exit;

enum OrderSyncState {

	case Synced;
	case InProgress;
	case Failed;
	case NotSynced;

	public static function label( OrderSyncState $state ): string {
		return match ( $state ) {
			self::Synced     => __( 'Synced with Abby', 'billing-abby-for-woocommerce' ),
			self::InProgress => __( 'Sync in progress', 'billing-abby-for-woocommerce' ),
			self::Failed     => __( 'Last sync failed', 'billing-abby-for-woocommerce' ),
			self::NotSynced  => __( 'Not synced yet', 'billing-abby-for-woocommerce' ),
		};
	}

	public static function notice_type( OrderSyncState $state ): string {
		return match ( $state ) {
			self::Synced     => 'success',
			self::InProgress => 'warning',
			self::Failed     => 'error',
			self::NotSynced  => 'info',
		};
	}
}
