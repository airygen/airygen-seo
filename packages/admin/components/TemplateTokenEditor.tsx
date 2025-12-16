import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { DragEvent } from 'react';

export type TemplateToken = {
	value: string;
	label: string;
	description: string;
};

type TemplateTokenEditorProps = {
	label: string;
	description: string;
	value: string;
	availableTokens: TemplateToken[];
	onChange: ( next: string ) => void;
	e2eName?: string;
};

const parseTemplateTokens = ( value: string, allowed: Set<string> ) => {
	const matches = value.match( /%[^%]+%/g ) ?? [];
	return matches.filter( ( token ) => allowed.has( token ) );
};

const serializeTemplateTokens = ( tokens: string[] ) =>
	tokens.length > 0 ? tokens.join( ' ' ).trim() : '';

const formatTokenLabel = ( token: string, lookup: Map<string, string> ) =>
	lookup.get( token ) ?? token.replace( /%/g, '' );

const formatTokenLocator = ( token: string, lookup: Map<string, string> ) =>
	formatTokenLabel( token, lookup ).replace( /[^a-zA-Z0-9_-]+/g, '-' );

const TemplateTokenEditor = ( {
	label,
	description,
	value,
	availableTokens,
	onChange,
	e2eName,
}: TemplateTokenEditorProps ) => {
	const allowedTokens = useMemo(
		() => new Set( availableTokens.map( ( token ) => token.value ) ),
		[ availableTokens ],
	);
	const tokenLabelMap = useMemo(
		() =>
			new Map(
				availableTokens.map( ( token ) => [ token.value, token.label ] ),
			),
		[ availableTokens ],
	);
	const tokens = parseTemplateTokens( value, allowedTokens );
	const updateTokens = ( nextTokens: string[] ) => {
		onChange( serializeTemplateTokens( nextTokens ) );
	};

	const handleAddToken = ( token: string ) => {
		updateTokens( [ ...tokens, token ] );
	};

	const moveTokenTo = ( fromIndex: number, toIndex: number ) => {
		if ( fromIndex === toIndex || fromIndex + 1 === toIndex ) {
			return;
		}

		const nextTokens = [ ...tokens ];
		const [ moved ] = nextTokens.splice( fromIndex, 1 );
		const normalizedIndex = fromIndex < toIndex ? toIndex - 1 : toIndex;
		nextTokens.splice( normalizedIndex, 0, moved );
		updateTokens( nextTokens );
	};

	const handleRemoveToken = ( index: number ) => {
		const nextTokens = [ ...tokens ];
		nextTokens.splice( index, 1 );
		updateTokens( nextTokens );
	};

	const handleDrop = (
		event: DragEvent<HTMLElement>,
		insertIndex?: number,
	) => {
		event.preventDefault();
		const payload = event.dataTransfer.getData( 'text/plain' );

		if ( payload.startsWith( 'index:' ) ) {
			const index = Number( payload.slice( 6 ) );
			if ( Number.isNaN( index ) ) {
				return;
			}
			const destination =
				typeof insertIndex === 'number' ? insertIndex : tokens.length;
			moveTokenTo( index, destination );
			return;
		}

		if ( allowedTokens.has( payload ) ) {
			const nextTokens = [ ...tokens ];
			if ( typeof insertIndex === 'number' ) {
				nextTokens.splice( insertIndex, 0, payload );
			} else {
				nextTokens.push( payload );
			}
			updateTokens( nextTokens );
		}
	};

	const handleTokenDrop = (
		event: DragEvent<HTMLElement>,
		targetIndex: number,
	) => {
		const rect = event.currentTarget.getBoundingClientRect();
		const centerX = rect.left + ( rect.width / 2 );
		const dropAfter = event.clientX > centerX;
		const insertIndex = dropAfter ? targetIndex + 1 : targetIndex;
		handleDrop( event, insertIndex );
	};

	const handleDragOver = ( event: DragEvent<HTMLElement> ) => {
		event.preventDefault();
	};

	return (
		<div
			className="rounded-lg border border-slate-200 bg-white p-4"
			data-airygen-e2e={ e2eName ? `template-token-editor-${ e2eName }` : undefined }
		>
			<div className="space-y-2">
				<div>
					<p className="text-sm font-medium text-gray-800">{ label }</p>
					<p className="text-xs text-slate-500">{ description }</p>
				</div>
				<div
					className="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 min-h-[52px] flex flex-wrap items-center gap-2"
					data-airygen-e2e={ e2eName ? `template-token-selected-${ e2eName }` : undefined }
					onDragOver={ handleDragOver }
					onDrop={ ( event ) => handleDrop( event ) }
				>
					{ tokens.length > 0 ? (
						tokens.map( ( token, index ) => (
							<span
								key={ `${ token }-${ index }` }
								className="cursor-grab rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-slate-700 flex items-center justify-center"
								data-airygen-e2e={
									e2eName
										? `template-token-selected-item-${ e2eName }-${ formatTokenLocator( token, tokenLabelMap ) }`
										: undefined
								}
								draggable
								onDragStart={ ( event ) =>
									event.dataTransfer.setData(
										'text/plain',
										`index:${ index }`,
									)
								}
								onDragOver={ handleDragOver }
								onDrop={ ( event ) => handleTokenDrop( event, index ) }
							>
								<span className="flex items-center gap-2">
									<span>
										{ formatTokenLabel( token, tokenLabelMap ) }
									</span>
									<div
										role="button"
										tabIndex={ 0 }
										className="flex h-4 w-4 items-center justify-center rounded-full border border-amber-300 bg-white text-[10px] leading-none text-amber-700 hover:bg-amber-100"
										data-airygen-e2e={
											e2eName
												? `template-token-remove-${ e2eName }-${ formatTokenLocator( token, tokenLabelMap ) }`
												: undefined
										}
										aria-label={ __(
											'Remove token',
											'airygen-seo',
										) }
										onClick={ () => handleRemoveToken( index ) }
										onKeyDown={ ( event ) => {
											if ( event.key === 'Enter' || event.key === ' ' ) {
												event.preventDefault();
												handleRemoveToken( index );
											}
										} }
									>
										<span className="relative -top-px">x</span>
									</div>
								</span>
							</span>
						) )
					) : (
						<span className="text-xs text-slate-400">
							{ __( 'Drag tokens here to build the template.', 'airygen-seo' ) }
						</span>
					) }
				</div>
				<div
					className="flex flex-wrap gap-2"
					data-airygen-e2e={ e2eName ? `template-token-available-${ e2eName }` : undefined }
				>
					{ availableTokens.map( ( token ) => (
						<button
							key={ token.value }
							type="button"
							className="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs text-slate-700 hover:bg-slate-200 flex items-center justify-center"
							data-airygen-e2e={
								e2eName
									? `template-token-button-${ e2eName }-${ token.label.replace( /[^a-zA-Z0-9_-]+/g, '-' ) }`
									: undefined
							}
							title={ token.description }
							draggable
							onDragStart={ ( event ) =>
								event.dataTransfer.setData(
									'text/plain',
									token.value,
								)
							}
							onClick={ () => handleAddToken( token.value ) }
						>
							{ token.label }
						</button>
					) ) }
				</div>
				<p className="text-xs text-slate-500">
					{ __(
						'Click or drag tokens into the template. Manual typing is disabled.',
						'airygen-seo',
					) }
				</p>
			</div>
		</div>
	);
};

export default TemplateTokenEditor;
