/**
 * Welcome step: pitch and a timeline preview of what the wizard sets up.
 */
import { __, sprintf } from '@wordpress/i18n';

const STEPS = [
	{
		title: __(
			'Connect your Abby account',
			'billing-abby-for-woocommerce'
		),
		description: __(
			'Add your Abby API key so the plugin can reach your account.',
			'billing-abby-for-woocommerce'
		),
	},
	{
		title: __(
			'Choose your income category',
			'billing-abby-for-woocommerce'
		),
		description: __(
			'Pick the accounting category Abby records when an order is paid.',
			'billing-abby-for-woocommerce'
		),
	},
	{
		title: __(
			'Start syncing your orders',
			'billing-abby-for-woocommerce'
		),
		description: __(
			'Paid orders are sent to Abby as draft invoices, automatically.',
			'billing-abby-for-woocommerce'
		),
	},
];

export default function WelcomeStep() {
	return (
		<div className="bafw-setup__welcome">
			<h1 className="bafw-setup__title">
				{ __(
					'Welcome to Billing Abby',
					'billing-abby-for-woocommerce'
				) }
			</h1>
			<p className="bafw-setup__subtitle">
				{ __(
					'Sync your WooCommerce orders to Abby as draft invoices — sent straight to Abby, with no third-party service in between.',
					'billing-abby-for-woocommerce'
				) }
			</p>
			<ol className="bafw-setup__timeline">
				{ STEPS.map( ( step, index ) => (
					<li key={ step.title } className="bafw-setup__tl-item">
						<span
							className="bafw-setup__tl-badge"
							aria-hidden="true"
						>
							{ index + 1 }
						</span>
						<span className="bafw-setup__tl-step">
							{ sprintf(
								/* translators: %d: step number. */
								__( 'Step %d', 'billing-abby-for-woocommerce' ),
								index + 1
							) }
						</span>
						<h3 className="bafw-setup__tl-title">{ step.title }</h3>
						<p className="bafw-setup__tl-desc">
							{ step.description }
						</p>
					</li>
				) ) }
			</ol>
		</div>
	);
}
