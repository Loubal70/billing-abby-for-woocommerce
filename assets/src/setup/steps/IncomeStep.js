/**
 * Income step: the shop-wide default Abby income category.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { saveProductType } from '../../settings/api';

export default function IncomeStep( { settings, onNotice } ) {
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
					message: __( 'Saved.', 'billing-abby-for-woocommerce' ),
				} )
			)
			.catch( () =>
				onNotice( {
					status: 'error',
					message: __(
						'Could not save the income type.',
						'billing-abby-for-woocommerce'
					),
				} )
			);
	};

	return (
		<div className="bafw-setup__step">
			<h1 className="bafw-setup__title">
				{ __(
					'Choose your income category',
					'billing-abby-for-woocommerce'
				) }
			</h1>
			<p className="bafw-setup__subtitle">
				{ __(
					'This is the accounting category Abby records when an order is paid. You can override it per product later.',
					'billing-abby-for-woocommerce'
				) }
			</p>
			<SelectControl
				label={ __(
					'Default income type',
					'billing-abby-for-woocommerce'
				) }
				value={ productType }
				options={ options }
				onChange={ onChange }
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
