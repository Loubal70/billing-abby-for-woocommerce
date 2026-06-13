/**
 * Maps an Abby key-status token (from the test-connection endpoint) to a user notice.
 */
import { __ } from '@wordpress/i18n';

export function connectionNotice( status ) {
	switch ( status ) {
		case 'valid':
			return {
				status: 'success',
				message: __(
					'Connection successful.',
					'billing-abby-for-woocommerce'
				),
			};
		case 'invalid':
			return {
				status: 'error',
				message: __(
					'Invalid API key.',
					'billing-abby-for-woocommerce'
				),
			};
		case 'forbidden':
			return {
				status: 'error',
				message: __(
					'Your Abby plan does not allow API access (Pro or higher required).',
					'billing-abby-for-woocommerce'
				),
			};
		default:
			return {
				status: 'error',
				message: __(
					'Could not reach Abby. Please try again.',
					'billing-abby-for-woocommerce'
				),
			};
	}
}
