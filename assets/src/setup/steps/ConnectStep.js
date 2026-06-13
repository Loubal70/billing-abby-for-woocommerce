/**
 * Connect step: save the Abby API key and optionally test the connection.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, Button, Flex, ExternalLink } from '@wordpress/components';
import { Icon, check } from '@wordpress/icons';
import { saveApiKey, testConnection } from '../../settings/api';
import { connectionNotice } from '../../settings/connection-status';
import { DOCS_API_KEY_URL } from '../../settings/links';

export default function ConnectStep( {
	settings,
	keyIsSet,
	onKeyIsSet,
	onNotice,
	onConnected,
} ) {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ maskedKey, setMaskedKey ] = useState(
		settings.api_key_masked || ''
	);
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );

	const hasInput = '' !== apiKey.trim();

	const reportConnection = ( result ) => {
		onConnected( 'valid' === result.status );
		onNotice( connectionNotice( result.status ) );
	};

	const reportFailure = () => {
		onConnected( false );
		onNotice( connectionNotice( 'error' ) );
	};

	const save = () => {
		if ( ! hasInput ) {
			return;
		}

		setSaving( true );

		saveApiKey( apiKey )
			.then( ( data ) => {
				onKeyIsSet( !! data.api_key_set );
				setMaskedKey( data.api_key_masked || '' );

				return testConnection();
			} )
			.then( reportConnection )
			.catch( reportFailure )
			.finally( () => setSaving( false ) );
	};

	const test = () => {
		setTesting( true );

		testConnection()
			.then( reportConnection )
			.catch( reportFailure )
			.finally( () => setTesting( false ) );
	};

	const onApiKeyChange = ( value ) => {
		setApiKey( value );
		onConnected( false );
	};

	return (
		<div className="bafw-setup__step">
			<h1 className="bafw-setup__title">
				{ __(
					'Connect your Abby account',
					'billing-abby-for-woocommerce'
				) }
			</h1>
			<p className="bafw-setup__subtitle">
				{ __(
					'Paste your Abby API key. It is stored encrypted and only ever sent to Abby.',
					'billing-abby-for-woocommerce'
				) }
			</p>

			{ keyIsSet && (
				<p className="bafw-setup__saved">
					<span className="bafw-setup__saved-icon" aria-hidden="true">
						<Icon icon={ check } size={ 20 } />
					</span>
					{ __( 'API key saved', 'billing-abby-for-woocommerce' ) }{ ' ' }
					<code>{ maskedKey }</code>
				</p>
			) }

			<TextControl
				type="password"
				label={ __( 'Abby API key', 'billing-abby-for-woocommerce' ) }
				help={ __(
					'Requires an Abby paid plan (Pro or higher).',
					'billing-abby-for-woocommerce'
				) }
				value={ apiKey }
				onChange={ onApiKeyChange }
				autoComplete="off"
				__nextHasNoMarginBottom
			/>

			<Flex
				className="bafw-setup__actions"
				justify="space-between"
				align="center"
				gap={ 4 }
			>
				<Flex justify="flex-start" gap={ 3 } expanded={ false }>
					<Button
						variant="primary"
						onClick={ save }
						isBusy={ saving }
						disabled={ saving || ! hasInput }
					>
						{ __( 'Save key', 'billing-abby-for-woocommerce' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ test }
						isBusy={ testing }
						disabled={ ! keyIsSet || testing }
					>
						{ __(
							'Test connection',
							'billing-abby-for-woocommerce'
						) }
					</Button>
				</Flex>

				<ExternalLink href={ DOCS_API_KEY_URL }>
					{ __(
						'How to create an Abby API key',
						'billing-abby-for-woocommerce'
					) }
				</ExternalLink>
			</Flex>
		</div>
	);
}
