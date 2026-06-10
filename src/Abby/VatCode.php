<?php
/**
 * Abby VAT codes.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

defined( 'ABSPATH' ) || exit;

/**
 * Abby billing-line VAT codes (confirmed on docs.abby.fr). The token is FR_<rate×100>;
 * the three 0% variants distinguish a domestic exemption, an intra-EU supply and an export.
 */
enum VatCode: string {

	case RATE_20  = 'FR_2000';
	case RATE_10  = 'FR_1000';
	case RATE_8_5 = 'FR_850';
	case RATE_5_5 = 'FR_550';
	case RATE_2_1 = 'FR_210';
	case EXEMPT   = 'FR_00HT';
	case INTRA_EU = 'FR_00UE';
	case EXPORT   = 'FR_0HUE';

	public static function from_rate( float $rate ): self {
		// INTRA_EU / EXPORT (0%) need order context we don't derive yet, so they stay unused here.
		return match ( $rate ) {
			20.0    => self::RATE_20,
			10.0    => self::RATE_10,
			8.5     => self::RATE_8_5,
			5.5     => self::RATE_5_5,
			2.1     => self::RATE_2_1,
			0.0     => self::EXEMPT,
			default => throw new \DomainException(
				esc_html( sprintf( 'Unsupported VAT rate %s%% for an Abby invoice line.', $rate ) )
			),
		};
	}
}
