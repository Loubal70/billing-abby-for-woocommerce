/**
 * Abby sync error log: a paginated table of recorded failures.
 */
import { useState, useEffect, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	Spinner,
	Flex,
	FlexItem,
	ExternalLink,
} from '@wordpress/components';
import { getLogs } from '../api';

const ISSUES_URL =
	'https://github.com/Loubal70/billing-abby-for-woocommerce/issues';

export default function ActivityLog() {
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let active = true;
		setLoading( true );

		getLogs( page )
			.then( ( result ) => {
				if ( active ) {
					setData( result );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );

		return () => {
			active = false;
		};
	}, [ page ] );

	const items = data ? data.items : [];
	const pages = data
		? Math.max( 1, Math.ceil( data.total / data.per_page ) )
		: 1;

	return (
		<Card className="bafw-settings__card">
			<CardHeader>
				<Flex justify="space-between" align="center">
					<FlexItem>
						{ __(
							'Abby sync errors',
							'billing-abby-for-woocommerce'
						) }
					</FlexItem>
					<FlexItem>
						<ExternalLink href={ ISSUES_URL }>
							{ __(
								'Report an issue',
								'billing-abby-for-woocommerce'
							) }
						</ExternalLink>
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				{ loading && ! data && <Spinner /> }

				{ data && items.length === 0 && (
					<p className="bafw-settings__intro">
						{ __(
							'No errors — every order synced cleanly. Successes are noted on each order.',
							'billing-abby-for-woocommerce'
						) }
					</p>
				) }

				{ items.length > 0 && (
					<Fragment>
						<table className="widefat striped bafw-log">
							<thead>
								<tr>
									<th>
										{ __(
											'Date',
											'billing-abby-for-woocommerce'
										) }
									</th>
									<th>
										{ __(
											'Order',
											'billing-abby-for-woocommerce'
										) }
									</th>
									<th>
										{ __(
											'Message',
											'billing-abby-for-woocommerce'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( row ) => (
									<tr key={ row.id }>
										<td>{ row.date }</td>
										<td>
											{ row.order_id
												? `#${ row.order_id }`
												: '—' }
										</td>
										<td>{ row.message }</td>
									</tr>
								) ) }
							</tbody>
						</table>

						{ pages > 1 && (
							<div className="bafw-log__nav">
								<Button
									variant="secondary"
									disabled={ page <= 1 }
									onClick={ () => setPage( page - 1 ) }
								>
									{ __(
										'Previous',
										'billing-abby-for-woocommerce'
									) }
								</Button>
								<span>
									{ sprintf(
										/* translators: 1: current page, 2: total pages. */
										__(
											'Page %1$d of %2$d',
											'billing-abby-for-woocommerce'
										),
										page,
										pages
									) }
								</span>
								<Button
									variant="secondary"
									disabled={ page >= pages }
									onClick={ () => setPage( page + 1 ) }
								>
									{ __(
										'Next',
										'billing-abby-for-woocommerce'
									) }
								</Button>
							</div>
						) }
					</Fragment>
				) }
			</CardBody>
		</Card>
	);
}
