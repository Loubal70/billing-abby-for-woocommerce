/**
 * One-shot confetti burst, fired from the success check on the final step.
 */
import { useState } from '@wordpress/element';

const COLORS = [
	'#0075eb',
	'#00a32a',
	'#f0b849',
	'#e0556b',
	'#7b61ff',
	'#19c37d',
];
const COUNT = 70;

function makePieces() {
	return Array.from( { length: COUNT }, ( _, index ) => ( {
		id: index,
		x: Math.round( ( Math.random() - 0.5 ) * 540 ),
		peak: -Math.round( 90 + Math.random() * 130 ),
		fall: Math.round( 160 + Math.random() * 240 ),
		rotate: Math.round( Math.random() * 720 - 360 ),
		delay: Math.random() * 0.1,
		duration: 1.3 + Math.random() * 0.9,
		color: COLORS[ index % COLORS.length ],
		width: Math.round( 5 + Math.random() * 4 ),
		height: Math.round( 9 + Math.random() * 6 ),
	} ) );
}

export default function Confetti() {
	const [ pieces ] = useState( makePieces );

	return (
		<div className="bafw-setup__confetti" aria-hidden="true">
			{ pieces.map( ( piece ) => (
				<span
					key={ piece.id }
					className="bafw-setup__confetti-piece"
					style={ {
						'--x': `${ piece.x }px`,
						animationDelay: `${ piece.delay }s`,
						animationDuration: `${ piece.duration }s`,
					} }
				>
					<span
						className="bafw-setup__confetti-shard"
						style={ {
							'--peak': `${ piece.peak }px`,
							'--fall': `${ piece.fall }px`,
							'--r': `${ piece.rotate }deg`,
							width: `${ piece.width }px`,
							height: `${ piece.height }px`,
							background: piece.color,
							animationDelay: `${ piece.delay }s`,
							animationDuration: `${ piece.duration }s`,
						} }
					/>
				</span>
			) ) }
		</div>
	);
}
