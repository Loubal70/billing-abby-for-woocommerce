/**
 * Abby API connection card: save the key, show its status, test the connection.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	TextControl,
	Button,
	Modal,
	Flex,
	FlexItem,
	ExternalLink,
} from '@wordpress/components';
import { Icon, check } from '@wordpress/icons';
import { saveApiKey, testConnection } from '../api';

const DOCS_URL = 'https://docs.abby.fr/api/authentification';

function connectionNotice( status ) {
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

export default function ConnectionCard( {
	settings,
	keyIsSet,
	onKeyIsSet,
	onNotice,
} ) {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ maskedKey, setMaskedKey ] = useState(
		settings.api_key_masked || ''
	);
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ confirmOpen, setConfirmOpen ] = useState( false );

	const hasInput = '' !== apiKey.trim();

	const performSave = () => {
		setConfirmOpen( false );

		if ( ! hasInput ) {
			return;
		}

		setSaving( true );

		saveApiKey( apiKey )
			.then( ( data ) => {
				onKeyIsSet( !! data.api_key_set );
				setMaskedKey( data.api_key_masked || '' );
				setApiKey( '' );
				onNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'billing-abby-for-woocommerce'
					),
				} );
			} )
			.catch( () =>
				onNotice( {
					status: 'error',
					message: __(
						'Could not save the settings.',
						'billing-abby-for-woocommerce'
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const runTest = () => {
		setTesting( true );

		testConnection()
			.then( ( data ) => onNotice( connectionNotice( data.status ) ) )
			.catch( () => onNotice( connectionNotice( 'error' ) ) )
			.finally( () => setTesting( false ) );
	};

	return (
		<Card className="bafw-settings__card">
			<CardHeader>
				{ __( 'Abby API connection', 'billing-abby-for-woocommerce' ) }
			</CardHeader>
			<CardBody>
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
							onClick={ runTest }
							isBusy={ testing }
							disabled={ ! keyIsSet || testing }
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
