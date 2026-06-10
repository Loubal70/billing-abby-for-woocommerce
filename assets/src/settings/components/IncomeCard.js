/**
 * Abby income card: the shop-wide default income type.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	SelectControl,
} from '@wordpress/components';
import { saveProductType } from '../api';

export default function IncomeCard( { settings, onNotice } ) {
	const [ productType, setProductType ] = useState(
		String( settings.product_type || '' )
	);

	const options = ( settings.product_type_options || [] ).map(
		( option ) => ( {
			label: option.label,
			value: String( option.value ),
		} )
	);

	const onChange = ( value ) => {
		setProductType( value );

		saveProductType( parseInt( value, 10 ) )
			.then( () =>
				onNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'billing-abby-for-woocommerce'
					),
				} )
			)
			.catch( () =>
				onNotice( {
					status: 'error',
					message: __(
						'Could not save the settings.',
						'billing-abby-for-woocommerce'
					),
				} )
			);
	};

	return (
		<Card className="bafw-settings__card">
			<CardHeader>
				{ __( 'Abby income', 'billing-abby-for-woocommerce' ) }
			</CardHeader>
			<CardBody>
				<p className="bafw-settings__intro">
					{ __(
						'These entries feed your Abby income book. Choose the category recorded by default when an order is paid.',
						'billing-abby-for-woocommerce'
					) }
				</p>
				<SelectControl
					label={ __(
						'Default income type',
						'billing-abby-for-woocommerce'
					) }
					help={ __(
						'Can be overridden per product.',
						'billing-abby-for-woocommerce'
					) }
					value={ productType }
					options={ options }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			</CardBody>
		</Card>
	);
}
