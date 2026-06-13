/**
 * Full-screen onboarding wizard: a guided welcome → connect → income → done flow.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import { getSettings, completeSetup } from '../settings/api';
import Confetti from './Confetti';
import WelcomeStep from './steps/WelcomeStep';
import ConnectStep from './steps/ConnectStep';
import IncomeStep from './steps/IncomeStep';
import DoneStep from './steps/DoneStep';
import abbyLogo from '../img/abby.webp';
import './style.scss';

const SETTINGS_URL = 'admin.php?page=bafw-settings';
const STEPS = [ 'welcome', 'connect', 'income', 'done' ];

export default function SetupWizard() {
	const [ loading, setLoading ] = useState( true );
	const [ settings, setSettings ] = useState( null );
	const [ keyIsSet, setKeyIsSet ] = useState( false );
	const [ connected, setConnected ] = useState( false );
	const [ step, setStep ] = useState( 0 );
	const [ notice, setNotice ] = useState( null );
	const [ finishing, setFinishing ] = useState( false );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setSettings( data );
				setKeyIsSet( !! data.api_key_set );

				if ( data.api_key_set ) {
					setStep( STEPS.indexOf( 'income' ) );
				}
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Could not load your settings.',
						'billing-abby-for-woocommerce'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	// Once the connection is validated, move on after 5s unless the user advances or edits first.
	useEffect( () => {
		if ( 'connect' !== STEPS[ step ] || ! connected ) {
			return undefined;
		}

		const timer = setTimeout( () => {
			setNotice( null );
			setStep( ( s ) => Math.min( s + 1, STEPS.length - 1 ) );
		}, 5000 );

		return () => clearTimeout( timer );
	}, [ connected, step ] );

	const finish = () => {
		setFinishing( true );
		completeSetup().finally( () => {
			window.location.href = SETTINGS_URL;
		} );
	};

	const goNext = () => {
		setNotice( null );
		setStep( ( current ) => Math.min( current + 1, STEPS.length - 1 ) );
	};

	const goBack = () => {
		setNotice( null );
		setStep( ( current ) => Math.max( current - 1, 0 ) );
	};

	if ( loading ) {
		return (
			<div className="bafw-setup__loading">
				<Spinner />
			</div>
		);
	}

	const current = STEPS[ step ];
	const isLast = step === STEPS.length - 1;
	const progress = ( step / ( STEPS.length - 1 ) ) * 100;

	return (
		<div className="bafw-setup">
			<header className="bafw-setup__header">
				<span className="bafw-setup__logo">
					<img
						className="bafw-setup__logo-mark"
						src={ abbyLogo }
						alt=""
						width="28"
						height="28"
					/>
					{ __( 'Billing Abby', 'billing-abby-for-woocommerce' ) }
				</span>
				{ ! isLast && (
					<button
						type="button"
						className="bafw-setup__exit"
						onClick={ finish }
					>
						{ __( 'Skip setup', 'billing-abby-for-woocommerce' ) }
					</button>
				) }
			</header>

			<div className="bafw-setup__progress">
				<div
					className="bafw-setup__progress-fill"
					style={ { transform: `scaleX(${ progress / 100 })` } }
				/>
			</div>

			{ 'done' === current && <Confetti /> }

			<main className="bafw-setup__content">
				<div className="bafw-setup__inner">
					{ notice && (
						<div className="bafw-setup__notice">
							<Notice
								status={ notice.status }
								onRemove={ () => setNotice( null ) }
							>
								{ notice.message }
							</Notice>
						</div>
					) }

					<div className="bafw-setup__step-enter" key={ current }>
						{ 'welcome' === current && <WelcomeStep /> }
						{ 'connect' === current && (
							<ConnectStep
								settings={ settings }
								keyIsSet={ keyIsSet }
								onKeyIsSet={ setKeyIsSet }
								onNotice={ setNotice }
								onConnected={ setConnected }
							/>
						) }
						{ 'income' === current && (
							<IncomeStep
								settings={ settings }
								onNotice={ setNotice }
							/>
						) }
						{ 'done' === current && <DoneStep /> }
					</div>
				</div>
			</main>

			<footer className="bafw-setup__footer">
				<div className="bafw-setup__footer-inner">
					{ step > 0 && ! isLast ? (
						<Button variant="tertiary" onClick={ goBack }>
							{ __( 'Back', 'billing-abby-for-woocommerce' ) }
						</Button>
					) : (
						<span />
					) }

					{ isLast ? (
						<Button
							variant="primary"
							onClick={ finish }
							isBusy={ finishing }
						>
							{ __(
								'Go to settings',
								'billing-abby-for-woocommerce'
							) }
						</Button>
					) : (
						<Button
							variant="primary"
							onClick={ goNext }
							disabled={ 'connect' === current && ! connected }
						>
							{ 0 === step
								? __(
										'Get started',
										'billing-abby-for-woocommerce'
								  )
								: __(
										'Continue',
										'billing-abby-for-woocommerce'
								  ) }
						</Button>
					) }
				</div>
			</footer>
		</div>
	);
}
