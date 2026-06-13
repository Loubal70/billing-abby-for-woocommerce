/**
 * Done step: confirmation and what happens next.
 */
import { __ } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';

export default function DoneStep() {
	return (
		<div className="bafw-setup__done">
			<span className="bafw-setup__done-check" aria-hidden="true">
				<Icon icon={ check } size={ 44 } />
			</span>
			<h1 className="bafw-setup__title">
				{ __( "You're all set", 'billing-abby-for-woocommerce' ) }
			</h1>
			<p className="bafw-setup__subtitle">
				{ __(
					'Each paid WooCommerce order now creates a draft invoice in Abby. Review and finalize your invoices anytime from your Abby account.',
					'billing-abby-for-woocommerce'
				) }
			</p>
		</div>
	);
}
