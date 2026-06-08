/**
 * Billing Abby for WooCommerce — admin settings panel.
 */
import { createRoot, useState, useEffect } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	TextControl,
	Button,
	Notice,
	Modal,
	Flex,
	FlexItem,
	Spinner,
	ExternalLink,
} from '@wordpress/components';
import { Icon, check } from '@wordpress/icons';
import './style.scss';

const SETTINGS_ROUTE = '/bafw/v1/settings';
const DOCS_URL = 'https://docs.abby.fr/api/authentification';

function SettingsApp() {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ keyIsSet, setKeyIsSet ] = useState( false );
	const [ maskedKey, setMaskedKey ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ confirmOpen, setConfirmOpen ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: SETTINGS_ROUTE } )
			.then( ( data ) => {
				setKeyIsSet( !! data.api_key_set );
				setMaskedKey( data.api_key_masked || '' );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Could not load the settings.',
						'billing-abby-for-woocommerce'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	// Auto-dismiss the notice after 10 seconds.
	useEffect( () => {
		if ( ! notice ) {
			return undefined;
		}

		const timer = setTimeout( () => setNotice( null ), 10000 );

		return () => clearTimeout( timer );
	}, [ notice ] );

	const hasInput = '' !== apiKey.trim();

	const performSave = () => {
		setConfirmOpen( false );

		if ( ! hasInput ) {
			return;
		}

		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: SETTINGS_ROUTE,
			method: 'POST',
			data: { api_key: apiKey },
		} )
			.then( ( data ) => {
				setKeyIsSet( !! data.api_key_set );
				setMaskedKey( data.api_key_masked || '' );
				setApiKey( '' );
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'billing-abby-for-woocommerce'
					),
				} );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Could not save the settings.',
						'billing-abby-for-woocommerce'
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	// TODO: confirm the Abby key-validation endpoint on docs.abby.fr before wiring this up.
	const testConnection = () => {
		setNotice( {
			status: 'warning',
			message: __(
				'Connection testing is not available yet.',
				'billing-abby-for-woocommerce'
			),
		} );
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<Card className="bafw-settings__card">
			<CardHeader>
				{ __( 'Abby API connection', 'billing-abby-for-woocommerce' ) }
			</CardHeader>
			<CardBody>
				{ notice && (
					<div className="bafw-settings__notice">
						<Notice
							status={ notice.status }
							onRemove={ () => setNotice( null ) }
						>
							{ notice.message }
						</Notice>
					</div>
				) }

				{ keyIsSet && (
					<Flex
						className="bafw-settings__status"
						justify="flex-start"
						align="center"
						gap={ 2 }
						expanded={ false }
					>
						<FlexItem>
							<span
								className="bafw-settings__status-icon"
								aria-hidden="true"
							>
								<Icon icon={ check } size={ 20 } />
							</span>
						</FlexItem>
						<FlexItem>
							{ __(
								'API key saved',
								'billing-abby-for-woocommerce'
							) }
						</FlexItem>
						<FlexItem>
							<code>{ maskedKey }</code>
						</FlexItem>
					</Flex>
				) }

				<TextControl
					type="password"
					label={ __(
						'Abby API key',
						'billing-abby-for-woocommerce'
					) }
					help={ __(
						'Stored encrypted. Requires an Abby paid plan (Pro or higher).',
						'billing-abby-for-woocommerce'
					) }
					value={ apiKey }
					onChange={ setApiKey }
					autoComplete="off"
					__nextHasNoMarginBottom
				/>

				<Flex
					className="bafw-settings__actions"
					justify="space-between"
					align="center"
					gap={ 4 }
				>
					<Flex justify="flex-start" gap={ 3 } expanded={ false }>
						<Button
							variant="primary"
							onClick={ () => setConfirmOpen( true ) }
							disabled={ saving || ! hasInput }
							isBusy={ saving }
						>
							{ __( 'Save', 'billing-abby-for-woocommerce' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ testConnection }
							disabled={ ! keyIsSet }
						>
							{ __(
								'Test connection',
								'billing-abby-for-woocommerce'
							) }
						</Button>
					</Flex>

					<span>
						{ __(
							'No API key yet?',
							'billing-abby-for-woocommerce'
						) }{ ' ' }
						<ExternalLink href={ DOCS_URL }>
							{ __(
								'How to create an Abby API key',
								'billing-abby-for-woocommerce'
							) }
						</ExternalLink>
					</span>
				</Flex>

				{ confirmOpen && (
					<Modal
						title={ __(
							'Save the API key?',
							'billing-abby-for-woocommerce'
						) }
						onRequestClose={ () => setConfirmOpen( false ) }
					>
						<p>
							{ keyIsSet
								? __(
										'This replaces the API key currently saved. Continue?',
										'billing-abby-for-woocommerce'
								  )
								: __(
										'Do you want to save this API key?',
										'billing-abby-for-woocommerce'
								  ) }
						</p>
						<Flex justify="flex-end" gap={ 2 }>
							<Button
								variant="tertiary"
								onClick={ () => setConfirmOpen( false ) }
							>
								{ __(
									'Cancel',
									'billing-abby-for-woocommerce'
								) }
							</Button>
							<Button variant="primary" onClick={ performSave }>
								{ __( 'Save', 'billing-abby-for-woocommerce' ) }
							</Button>
						</Flex>
					</Modal>
				) }
			</CardBody>
		</Card>
	);
}

domReady( () => {
	const root = document.getElementById( 'bafw-settings-root' );

	if ( root ) {
		createRoot( root ).render( <SettingsApp /> );
	}
} );
