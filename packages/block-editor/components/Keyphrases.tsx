import type { KeyboardEvent } from 'react';
import { __ } from '@wordpress/i18n';
import { getNoLongTailKeyphrasesAddedYetLabel } from '../../shared/i18nPhrases';
import { Button, TextControl, Popover } from '@wordpress/components';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import usePostDataField from '../hooks/usePostDataField';

const MAX_LONG_TAIL = 5;

const addTagToList = ( list: string[], value: string ): string[] => {
	const normalized = value.trim();
	if ( ! normalized ) {
		return list;
	}

	if ( list.includes( normalized ) ) {
		return list;
	}

	if ( list.length >= MAX_LONG_TAIL ) {
		return list;
	}

	return [ ...list, normalized ];
};

const Keyphrases = () => {
	const [ keyphrase, setKeyphrase ] = usePostDataField( 'focusKeyphrase' );
	const [ longTailRaw, setLongTailRaw ] = usePostDataField( 'focusLongTail' );
	const [ activeTab, setActiveTab ] = useState< 'focus' | 'longtail' | 'preview' >(
		'preview',
	);
	const [ pending, setPending ] = useState( '' );
	const [ showLongTailTip, setShowLongTailTip ] = useState( false );
	const { content } = useSelect( ( select ) => {
		const editor = select( 'core/editor' ) as {
			getEditedPostAttribute?: ( key: string ) => unknown;
		};
		const raw = editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'content' ) : '';
		return {
			content: ( raw as string ) || '',
		};
	}, [] );

	const tags = useMemo( () => {
		return ( longTailRaw || '' )
			.split( ',' )
			.map( ( tag ) => tag.trim() )
			.filter( Boolean )
			.slice( 0, MAX_LONG_TAIL );
	}, [ longTailRaw ] );

	const persistTags = ( next: string[] ) => {
		setLongTailRaw( next.join( ', ' ) );
	};

	const removeTag = ( index: number ) => {
		const next = tags.filter( ( _, i ) => i !== index );
		persistTags( next );
	};

	const handleInputChange = ( value: string ) => {
		if ( value.includes( ',' ) ) {
			const parts = value.split( ',' );
			const last = parts.pop() ?? '';
			let next = [ ...tags ];
			parts.forEach( ( part ) => {
				next = addTagToList( next, part );
			} );
			persistTags( next );
			setPending( last );
			return;
		}

		setPending( value );
	};

	const handleKeyDown = ( event: KeyboardEvent< HTMLInputElement > ) => {
		if ( event.key === 'Enter' || event.key === ',' ) {
			event.preventDefault();
			const next = addTagToList( tags, pending );
			persistTags( next );
			setPending( '' );
		}
	};

	const handleBlur = () => {
		const next = addTagToList( tags, pending );
		persistTags( next );
		setPending( '' );
	};

	const containsCjk = ( text: string ): boolean =>
		/[\p{Script=Han}\p{Script=Hiragana}\p{Script=Katakana}\p{Script=Hangul}]/u.test( text );

	const stripTags = ( html: string ): string => {
		if ( ! html ) {
			return '';
		}
		const div = document.createElement( 'div' );
		div.innerHTML = html;
		return div.textContent || '';
	};

	const tokenizeWords = ( text: string ): string[] =>
		text
			.trim()
			.split( /\s+/ )
			.map( ( token ) => token.trim() )
			.filter( Boolean );

	const findPhrasePositions = ( tokens: string[], phraseTokens: string[] ): number[] => {
		if ( phraseTokens.length === 0 || tokens.length === 0 ) {
			return [];
		}

		const positions: number[] = [];
		for ( let i = 0; i <= tokens.length - phraseTokens.length; i += 1 ) {
			let match = true;
			for ( let j = 0; j < phraseTokens.length; j += 1 ) {
				if ( tokens[ i + j ] !== phraseTokens[ j ] ) {
					match = false;
					break;
				}
			}
			if ( match ) {
				positions.push( i );
			}
		}
		return positions;
	};

	const findPhrasePositionsChars = ( text: string, phrase: string ): number[] => {
		const compact = text.replace( /\s+/g, '' ).toLowerCase();
		const needle = phrase.replace( /\s+/g, '' ).toLowerCase();
		if ( ! compact || ! needle ) {
			return [];
		}
		const positions: number[] = [];
		let idx = compact.indexOf( needle );
		while ( idx !== -1 ) {
			positions.push( idx );
			idx = compact.indexOf( needle, idx + needle.length );
		}
		return positions;
	};

	const isCjkContent = useMemo( () => containsCjk( content || '' ), [ content ] );
	const plainText = useMemo( () => stripTags( content || '' ), [ content ] );

	const computeSpacingOk = (): { status: 'good' | 'warn' | 'bad'; message: string } => {
		if ( tags.length === 0 ) {
			return {
				status: 'warn',
				message: __( 'Add long-tail keyphrases to check spacing.', 'airygen-seo' ),
			};
		}

		if ( ! keyphrase?.trim() ) {
			return {
				status: 'warn',
				message: __( 'Set a focus keyphrase to evaluate spacing.', 'airygen-seo' ),
			};
		}

		const lowerFocus = keyphrase.toLowerCase().trim();
		const lowerLongTails = tags.map( ( t ) => t.toLowerCase().trim() ).filter( Boolean );

		if ( isCjkContent ) {
			const positionsFocus = findPhrasePositionsChars( plainText, lowerFocus );
			const longTailPositions = lowerLongTails.map( ( phrase ) =>
				findPhrasePositionsChars( plainText, phrase ),
			);

			for ( let i = 0; i < lowerLongTails.length; i += 1 ) {
				for ( const pos of longTailPositions[ i ] ) {
					for ( const fPos of positionsFocus ) {
						if ( Math.abs( pos - fPos ) < 50 ) {
							return {
								status: 'bad',
								message: __(
									'Long-tail keyphrases should be at least 50 characters away from other keyphrases.',
									'airygen-seo',
								),
							};
						}
					}
					for ( let j = 0; j < lowerLongTails.length; j += 1 ) {
						if ( i === j ) {
							continue;
						}
						for ( const otherPos of longTailPositions[ j ] ) {
							if ( Math.abs( pos - otherPos ) < 50 ) {
								return {
									status: 'bad',
									message: __(
										'Long-tail keyphrases should be at least 50 characters away from other keyphrases.',
										'airygen-seo',
									),
								};
							}
						}
					}
				}
			}

			return {
				status: 'good',
				message: __( 'Long-tail spacing looks good.', 'airygen-seo' ),
			};
		}

		const tokens = tokenizeWords( plainText.toLowerCase() );
		if ( tokens.length === 0 ) {
			return {
				status: 'warn',
				message: __( 'Add content to evaluate spacing.', 'airygen-seo' ),
			};
		}

		const focusTokens = tokenizeWords( lowerFocus );
		const focusPositions = findPhrasePositions( tokens, focusTokens );

		const longTailPositions = lowerLongTails.map( ( phrase ) =>
			findPhrasePositions( tokens, tokenizeWords( phrase ) ),
		);

		for ( let i = 0; i < lowerLongTails.length; i += 1 ) {
			for ( const pos of longTailPositions[ i ] ) {
				for ( const fPos of focusPositions ) {
					if ( Math.abs( pos - fPos ) < 50 ) {
						return {
							status: 'bad',
							message: __(
								'Long-tail keyphrases should be at least 50 words away from other keyphrases.',
								'airygen-seo',
							),
						};
					}
				}

				for ( let j = 0; j < lowerLongTails.length; j += 1 ) {
					if ( i === j ) {
						continue;
					}
					for ( const otherPos of longTailPositions[ j ] ) {
						if ( Math.abs( pos - otherPos ) < 50 ) {
							return {
								status: 'bad',
								message: __(
									'Long-tail keyphrases should be at least 50 words away from other keyphrases.',
									'airygen-seo',
								),
							};
						}
					}
				}
			}
		}

		return {
			status: 'good',
			message: __( 'Long-tail spacing looks good.', 'airygen-seo' ),
		};
	};

	const computeDensity = useCallback(
		( phrase: string ) => {
			const unitCount = isCjkContent
				? plainText.replace( /\s+/g, '' ).length
				: tokenizeWords( plainText ).length;
			if ( ! phrase?.trim() || unitCount === 0 ) {
				return { occurrences: 0, density: 0 };
			}

			if ( isCjkContent ) {
				const positions = findPhrasePositionsChars( plainText, phrase );
				return {
					occurrences: positions.length,
					density: positions.length > 0 ? ( positions.length / unitCount ) * 100 : 0,
				};
			}

			const tokens = tokenizeWords( plainText.toLowerCase() );
			const phraseTokens = tokenizeWords( phrase.toLowerCase() );
			const positions = findPhrasePositions( tokens, phraseTokens );

			return {
				occurrences: positions.length,
				density: positions.length > 0 ? ( positions.length / unitCount ) * 100 : 0,
			};
		},
		[ isCjkContent, plainText ],
	);

	const spacingStatus = useMemo( computeSpacingOk, [ tags, keyphrase, plainText, isCjkContent ] );

	const focusStats = useMemo(
		() => computeDensity( keyphrase || '' ),
		[ keyphrase, computeDensity ],
	);

	const longTailStats = useMemo(
		() =>
			tags.map( ( tag ) => ( {
				tag,
				...computeDensity( tag ),
			} ) ),
		[ tags, computeDensity ],
	);

	const focusDensityStatus = useMemo( () => {
		if ( ! keyphrase?.trim() ) {
			return {
				status: 'warn' as const,
				message: __( 'Set a focus keyphrase to check density.', 'airygen-seo' ),
			};
		}

		const density = focusStats.density;
		if ( density >= 0.5 && density <= 2 ) {
			return {
				status: 'good' as const,
				message: __(
					/* translators: percentage range for focus keyphrase density. */
					'Focus keyphrase density is in range (0.5%–2%).',
					'airygen',
				),
			};
		}

		if ( density === 0 ) {
			return {
				status: 'bad' as const,
				message: __( 'Focus keyphrase density is 0%. Add it naturally to the content.', 'airygen-seo' ),
			};
		}

		return {
			status: 'bad' as const,
			message: __(
				/* translators: percentage range for focus keyphrase density. */
				'Adjust focus keyphrase density to 0.5%–2%.',
				'airygen-seo',
			),
		};
	}, [ keyphrase, focusStats.density ] );

	const focusInHeadingsStatus = useMemo( () => {
		if ( ! keyphrase?.trim() ) {
			return {
				status: 'warn' as const,
				message: __( 'Set a focus keyphrase to check headings.', 'airygen-seo' ),
			};
		}

		const container = document.createElement( 'div' );
		container.innerHTML = content || '';
		const headings = Array.from( container.querySelectorAll( 'h2, h3' ) );

		if ( headings.length === 0 ) {
			return {
				status: 'warn' as const,
				message: __( 'Add an H2 or H3 to check focus placement.', 'airygen-seo' ),
			};
		}

		const focusLower = keyphrase.toLowerCase();
		const match = headings.some( ( node ) =>
			( node.textContent || '' ).toLowerCase().includes( focusLower ),
		);

		if ( match ) {
			return {
				status: 'good' as const,
				message: __( 'At least one H2/H3 includes the focus keyphrase.', 'airygen-seo' ),
			};
		}

		return {
			status: 'bad' as const,
			message: __( 'Add the focus keyphrase to at least one H2 or H3.', 'airygen-seo' ),
		};
	}, [ content, keyphrase ] );

	const longTailDensityStatus = useMemo( () => {
		if ( tags.length === 0 ) {
			return {
				status: 'warn' as const,
				message: __( 'Add long-tail keyphrases to check density.', 'airygen-seo' ),
			};
		}

		const totalDensity = longTailStats.reduce( ( sum, stat ) => sum + stat.density, 0 );
		const perRangeOk = longTailStats.every(
			( stat ) => stat.density >= 0.1 && stat.density <= 0.5,
		);
		const totalOk = totalDensity <= 2;

		if ( perRangeOk && totalOk ) {
			return {
				status: 'good' as const,
				/* translators: percentage ranges for long-tail keyphrase density. */
				message: __(
					'Long-tail density is in range (0.1%%–0.5%% each, total ≤ 2%).',
					'airygen',
				),
			};
		}

		return {
			status: 'bad' as const,
			/* translators: percentage ranges for long-tail keyphrase density. */
			message: __(
				'Adjust long-tail density (0.1%%–0.5%% each, total ≤ 2%).',
				'airygen',
			),
		};
	}, [ tags.length, longTailStats ] );

	const FocusIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_focus)">
				<path
					d="M3.05421 0.554688V1.12947C2.04904 1.25719 1.25768 2.04856 1.12995 3.05372H0.555176V3.60906H1.12995C1.25768 4.61423 2.04904 5.40559 3.05421 5.53332V6.1081H3.60955V5.53332C4.61472 5.40559 5.40608 4.61423 5.53381 3.60906H6.10859V3.05372H5.53381C5.40608 2.04856 4.61472 1.25719 3.60955 1.12947V0.554688H3.05421ZM3.05421 1.68758V2.22071H3.60955V1.69036C4.30373 1.80421 4.85907 2.35955 4.97569 3.05372H4.44256V3.60906H4.97292C4.85907 4.30324 4.30373 4.85858 3.60955 4.9752V4.44208H3.05421V4.97243C2.36004 4.85858 1.80469 4.30324 1.68807 3.60906H2.2212V3.05372H1.69085C1.80469 2.35955 2.36004 1.80421 3.05421 1.68758ZM3.33188 3.05372C3.25824 3.05372 3.18761 3.08298 3.13554 3.13505C3.08347 3.18712 3.05421 3.25775 3.05421 3.33139C3.05421 3.40504 3.08347 3.47566 3.13554 3.52774C3.18761 3.57981 3.25824 3.60906 3.33188 3.60906C3.40553 3.60906 3.47615 3.57981 3.52822 3.52774C3.5803 3.47566 3.60955 3.40504 3.60955 3.33139C3.60955 3.25775 3.5803 3.18712 3.52822 3.13505C3.47615 3.08298 3.40553 3.05372 3.33188 3.05372Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_focus">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const PreviewTabIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_preview_keyphrase)">
				<path
					d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_preview_keyphrase">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const LongTailIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_longtail)">
				<path
					d="M4.74814 2.38813V1.72172C4.91475 1.63842 5.05358 1.47182 5.05358 1.27745V1.24968C5.05358 0.97201 4.83144 0.749874 4.55377 0.749874H4.52601C4.24834 0.749874 4.0262 0.97201 4.0262 1.24968V1.27745C4.0262 1.47182 4.13727 1.63842 4.33164 1.72172V2.38813C4.08173 2.4159 3.83183 2.52697 3.63746 2.69357L1.80483 1.27745C1.88814 0.97201 1.69377 0.638806 1.38833 0.583271C1.08289 0.527737 0.777453 0.666573 0.694152 0.97201C0.61085 1.27745 0.80522 1.61065 1.11066 1.69395C1.24949 1.72172 1.4161 1.72172 1.55493 1.63842L3.33202 3.02677C2.99882 3.52658 2.99882 4.16522 3.35979 4.66503L2.80445 5.22037C2.74891 5.22037 2.72115 5.1926 2.66561 5.1926C2.41571 5.1926 2.19357 5.41474 2.19357 5.66464C2.19357 5.91455 2.41571 6.10892 2.66561 6.10892C2.91552 6.10892 3.13765 5.88678 3.13765 5.63688C3.13765 5.58134 3.13765 5.55358 3.10989 5.49804L3.63746 4.97047C4.2761 5.44251 5.19242 5.33144 5.66446 4.6928C6.1365 4.05415 6.02543 3.13784 5.38679 2.6658C5.22018 2.52697 4.99805 2.4159 4.74814 2.38813ZM4.52601 4.55396C4.1095 4.55396 3.7763 4.22076 3.7763 3.80425C3.7763 3.38774 4.1095 3.05454 4.52601 3.05454C4.94251 3.05454 5.27572 3.38774 5.27572 3.80425C5.27572 4.22076 4.94251 4.55396 4.52601 4.55396Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_longtail">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const getCheckClass = ( status: 'good' | 'warn' | 'bad' ) => {
		if ( status === 'good' ) {
			return 'airygen-preview-check airygen-preview-check--good';
		}
		if ( status === 'warn' ) {
			return 'airygen-preview-check airygen-preview-check--warn';
		}
		return 'airygen-preview-check airygen-preview-check--bad';
	};

	const renderChecks = () => {
		const checks = [
			focusInHeadingsStatus,
			focusDensityStatus,
			spacingStatus,
			longTailDensityStatus,
		];
		const allGood = checks.every( ( item ) => item.status === 'good' );

		if ( allGood ) {
			return (
				<p className="airygen-preview-check airygen-preview-check--good">
					<span
						className="dashicons dashicons-yes"
						aria-hidden="true"
						style={ { marginRight: '6px' } }
					/>
					<span style={ { color: '#6b7280' } }>
						{ __( 'All keyphrase checks look good.', 'airygen-seo' ) }
					</span>
				</p>
			);
		}

		return checks.map( ( item, idx ) => {
			const iconClass =
				item.status === 'good' ? 'dashicons dashicons-yes' : 'dashicons dashicons-no-alt';

			return (
				<p
					// eslint-disable-next-line react/no-array-index-key
					key={ idx }
					className={ getCheckClass( item.status ) }
				>
					<span className={ iconClass } aria-hidden="true" style={ { marginRight: '6px' } } />
					<span style={ { color: '#6b7280' } }>{ item.message }</span>
				</p>
			);
		} );
	};

	const renderTabContent = () => {
		if ( activeTab === 'focus' ) {
			return (
				<TextControl
					label={ __( 'Focus Keyphrase', 'airygen-seo' ) }
					value={ keyphrase }
					onChange={ setKeyphrase }
				/>
			);
		}

		if ( activeTab === 'longtail' ) {
			return (
				<div>
					<div className="airygen-tip-positioner">
						<Button
							onClick={ () => setShowLongTailTip( ( prev ) => ! prev ) }
							aria-expanded={ showLongTailTip }
							className="airygen-component-button airygen-tip-button"
						>
							?
						</Button>
						{ showLongTailTip && (
							<Popover
								noArrow
								position="bottom left"
								onClose={ () => setShowLongTailTip( false ) }
							>
								<div className="airygen-panel-popover">
									{ __(
										'To avoid keyword stuffing, each long-tail keyphrase should be supported by at least 150 words of explanatory content; do not repeat the keyphrase on its own without meaningful text.',
										'airygen-seo',
									) }
									<div style={ { marginTop: '8px', textAlign: 'center' } }>
										<Button
											variant="primary"
											size="small"
											onClick={ () => setShowLongTailTip( false ) }
											className="airygen-component-button"
										>
											{ __( 'Got it', 'airygen-seo' ) }
										</Button>
									</div>
								</div>
							</Popover>
						) }
					</div>
					<TextControl
						label={ __( 'Long-tail keyphrases', 'airygen-seo' ) }
						help={ __( 'Press Enter or comma to save each keyphrase (max 5).', 'airygen-seo' ) }
						value={ pending }
						onChange={ handleInputChange }
						onKeyDown={ handleKeyDown }
						onBlur={ handleBlur }
					/>
					<div className="airygen-tag-list">
						{ tags.map( ( tag, index ) => (
							<span className="airygen-tag" key={ `${ tag }-${ index }` }>
								{ tag }
								<button
									type="button"
									onClick={ () => removeTag( index ) }
									aria-label={ __( 'Remove keyphrase', 'airygen-seo' ) }
								>
									×
								</button>
							</span>
						) ) }
						{ tags.length === 0 && (
							<span className="airygen-preview-check airygen-preview-check--warn">
								{ getNoLongTailKeyphrasesAddedYetLabel() }
							</span>
						) }
					</div>
				</div>
			);
		}

		return (
			<div className="airygen-preview-checklist">
				<div className="airygen-keyphrase-list">
					<div className="airygen-keyphrase-list__row">
						<div className="airygen-keyphrase-list__item">
							<span className="airygen-keyphrase-list__badge" aria-hidden="true"></span>
							<div className="airygen-keyphrase-list__keyword">
								<strong>{ keyphrase || __( '(Not set)', 'airygen-seo' ) }</strong>
							</div>
						</div>
						<div className="airygen-keyphrase-list__meta">
							<div className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--count">
								<span
									className="airygen-keyphrase-list__dot"
									aria-hidden="true"
									title={ __( 'Count', 'airygen-seo' ) }
								/>
								<span className="airygen-keyphrase-list__value">
									{ focusStats.occurrences }
								</span>
							</div>
							<div className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--density">
								<span
									className="airygen-keyphrase-list__dot"
									aria-hidden="true"
									title={ __( 'Density', 'airygen-seo' ) }
								/>
								<span className="airygen-keyphrase-list__value">
									{ focusStats.density.toFixed( 2 ) }%
								</span>
							</div>
						</div>
					</div>
					{ longTailStats.map( ( stat ) => (
						<div className="airygen-keyphrase-list__row" key={ stat.tag }>
							<div className="airygen-keyphrase-list__item">
								<div className="airygen-keyphrase-list__keyword">{ stat.tag }</div>
							</div>
							<div className="airygen-keyphrase-list__meta">
								<div className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--count">
									<span
										className="airygen-keyphrase-list__dot"
										aria-hidden="true"
										title={ __( 'Count', 'airygen-seo' ) }
									/>
									<span className="airygen-keyphrase-list__value">
										{ stat.occurrences }
									</span>
								</div>
								<div className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--density">
									<span
										className="airygen-keyphrase-list__dot"
										aria-hidden="true"
										title={ __( 'Density', 'airygen-seo' ) }
									/>
									<span className="airygen-keyphrase-list__value">
										{ stat.density.toFixed( 2 ) }%
									</span>
								</div>
							</div>
						</div>
					) ) }
				</div>
				<legend className="components-base-control__label airygen-preview-check__legend">
					{ __( 'Checks', 'airygen-seo' ) }
				</legend>
				{ renderChecks() }
			</div>
		);
	};

	return (
		<div className="airygen-panel-layout">
			<div className="airygen-panel-tabs">
				<Button
					variant={ activeTab === 'preview' ? 'primary' : 'secondary' }
					onClick={ () => setActiveTab( 'preview' ) }
					aria-label={ __( 'Keyphrase preview', 'airygen-seo' ) }
					title={ __( 'Keyphrase preview', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<PreviewTabIcon />
				</Button>
				<Button
					variant={ activeTab === 'focus' ? 'primary' : 'secondary' }
					onClick={ () => setActiveTab( 'focus' ) }
					aria-label={ __( 'Focus keyphrase', 'airygen-seo' ) }
					title={ __( 'Focus keyphrase', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<FocusIcon />
				</Button>
				<Button
					variant={ activeTab === 'longtail' ? 'primary' : 'secondary' }
					onClick={ () => setActiveTab( 'longtail' ) }
					aria-label={ __( 'Long-tail keyphrases', 'airygen-seo' ) }
					title={ __( 'Long-tail keyphrases', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<LongTailIcon />
				</Button>
			</div>

			{ renderTabContent() }
		</div>
	);
};

export default Keyphrases;
