/**
 * Settings panel: loads the saved settings and composes the cards under a shared notice.
 */
import { useState, useEffect, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Spinner } from '@wordpress/components';
import { getSettings } from './api';
import ConnectionCard from './components/ConnectionCard';
import IncomeCard from './components/IncomeCard';
import ActivityLog from './components/ActivityLog';
import './style.scss';

export default function SettingsApp() {
	const [ loading, setLoading ] = useState( true );
	const [ settings, setSettings ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ keyIsSet, setKeyIsSet ] = useState( false );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setSettings( data );
				setKeyIsSet( !! data.api_key_set );
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

	if ( loading ) {
		return <Spinner />;
	}

	if ( ! settings ) {
		return (
			<div className="bafw-settings__notice">
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Could not load the settings. Please reload the page.',
						'billing-abby-for-woocommerce'
					) }
				</Notice>
			</div>
		);
	}

	return (
		<Fragment>
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
			<ConnectionCard
				settings={ settings }
				keyIsSet={ keyIsSet }
				onKeyIsSet={ setKeyIsSet }
				onNotice={ setNotice }
			/>
			{ keyIsSet ? (
				<div className="bafw-grid">
					<IncomeCard settings={ settings } onNotice={ setNotice } />
					<ActivityLog />
				</div>
			) : (
				<ActivityLog />
			) }
		</Fragment>
	);
}
