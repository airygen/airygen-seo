import type { ComponentType, ReactNode } from 'react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSaveChangesLabel, getSavingLabel } from '../utils/i18n';
import Button from './Button';
import Toast from './Toast';
import type { IconProps } from './Icons';
import classNames from '../utils/classNames';
import type { NoticeState } from '../types/api';
export type ShellPageName = string;

export type ShellTabKey = string;

export type ShellPage = {
	name: ShellPageName;
	title: string;
};

export type ShellTab = {
	name: ShellTabKey;
	title: string;
	icon?: ComponentType<IconProps>;
	render: () => ReactNode;
};

export type AdminShellProps = {
	title: string;
	logoUrl?: string;
	pages: ShellPage[];
	activePage: ShellPageName;
	onSelectPage: ( page: ShellPageName ) => void;
	tabs: Array<ShellTab & { render: () => ReactNode }>;
	activeTab: ShellTabKey;
	onSelectTab: ( tab: ShellTabKey ) => void;
	onSave: () => void;
	isSaving: boolean;
	isLoading: boolean;
	isDirty: boolean;
	notice: NoticeState | null;
	onDismissNotice: () => void;
	loadErrorMessage: string | null;
	onRetryLoad: () => void;
	children: ReactNode;
};

const AdminShell = ( props: AdminShellProps ) => {
	const {
		title,
		logoUrl,
		pages,
		activePage,
		onSelectPage,
		tabs,
		activeTab,
		onSelectTab,
		onSave,
		isSaving,
		isLoading,
		isDirty,
		notice,
		onDismissNotice,
		loadErrorMessage,
		onRetryLoad,
		children,
	} = props;

	const showGlobalSave =
		( activePage === 'settings' && activeTab !== 'redirects' ) ||
		activePage === 'notify';
	const tabRefs = useRef<Partial<Record<ShellTabKey, HTMLButtonElement | null>>>( {} );
	const overflowMenuRef = useRef<HTMLLIElement | null>( null );
	const tabsListRef = useRef<HTMLUListElement | null>( null );
	const [ overflowTabs, setOverflowTabs ] = useState<ShellTabKey[]>( [] );
	const [ isOverflowMenuOpen, setIsOverflowMenuOpen ] = useState( false );
	const [ isMeasuringTabs, setIsMeasuringTabs ] = useState( true );

	const visibleTabs = useMemo( () => {
		if ( isMeasuringTabs ) {
			return tabs;
		}
		return tabs.filter( ( tab ) => ! overflowTabs.includes( tab.name ) );
	}, [ tabs, overflowTabs, isMeasuringTabs ] );

	const overflowList = useMemo( () => {
		if ( isMeasuringTabs ) {
			return [] as typeof tabs;
		}
		return tabs.filter( ( tab ) => overflowTabs.includes( tab.name ) );
	}, [ tabs, overflowTabs, isMeasuringTabs ] );

	const hasOverflow = ! isMeasuringTabs && overflowList.length > 0;

	useLayoutEffect( () => {
		if ( ! isMeasuringTabs ) {
			return;
		}
		const frame = requestAnimationFrame( () => {
			const buttons = tabs
				.map( ( tab ) => tabRefs.current[ tab.name ] )
				.filter( Boolean ) as HTMLButtonElement[];

			if ( buttons.length === 0 ) {
				setOverflowTabs( [] );
				setIsMeasuringTabs( false );
				return;
			}

			const lis = buttons.map( ( b ) => b.parentElement as HTMLLIElement );
			const container = tabsListRef.current;

			if ( ! container || lis.length === 0 ) {
				setOverflowTabs( [] );
				setIsMeasuringTabs( false );
				return;
			}

			const firstRowTop = lis[ 0 ].offsetTop;
			// Find the first item that wraps to the next line
			const firstWrapIndex = lis.findIndex(
				( li ) => li.offsetTop > firstRowTop + 5,
			);

			if ( firstWrapIndex === -1 ) {
				setOverflowTabs( [] );
				setIsMeasuringTabs( false );
				return;
			}

			// We have overflow. Now check if we need to hide one more item to fit the "..." button.
			// The "..." button is roughly 48px wide (icon + padding).
			// We also need to account for the gap (margin-right) of the last item.
			const OVERFLOW_BUTTON_WIDTH = 48;
			const ITEM_MARGIN_RIGHT = 8; // me-2 is 0.5rem = 8px

			const containerRect = container.getBoundingClientRect();
			const lastVisibleLi = lis[ firstWrapIndex - 1 ];
			const lastLiRect = lastVisibleLi.getBoundingClientRect();

			// Calculate space remaining on the right of the last visible item
			// containerRect.right is the edge of the container
			// lastLiRect.right is the edge of the item
			// We subtract ITEM_MARGIN_RIGHT because the next item (the "..." button) will start after the margin.
			const spaceAvailable =
				containerRect.right - lastLiRect.right - ITEM_MARGIN_RIGHT;

			let cutOffIndex = firstWrapIndex;

			// If there isn't enough space for the "..." button, we need to hide the last visible item as well
			if ( spaceAvailable < OVERFLOW_BUTTON_WIDTH ) {
				cutOffIndex = Math.max( 0, firstWrapIndex - 1 );
			}

			const nextOverflow = tabs
				.slice( cutOffIndex )
				.map( ( tab ) => tab.name );

			setOverflowTabs( nextOverflow );
			if ( nextOverflow.length === 0 ) {
				setIsOverflowMenuOpen( false );
			}
			setIsMeasuringTabs( false );
		} );
		return () => cancelAnimationFrame( frame );
	}, [ isMeasuringTabs, tabs, tabRefs ] );

	useEffect( () => {
		const handleResize = () => setIsMeasuringTabs( true );
		window.addEventListener( 'resize', handleResize );
		return () => window.removeEventListener( 'resize', handleResize );
	}, [] );

	useEffect( () => {
		setIsMeasuringTabs( true );
	}, [ tabs ] );

	useEffect( () => {
		if ( ! isOverflowMenuOpen ) {
			return () => {};
		}
		const handleClickOutside = ( event: MouseEvent ) => {
			if ( overflowMenuRef.current?.contains( event.target as Node ) ) {
				return;
			}
			setIsOverflowMenuOpen( false );
		};
		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [ isOverflowMenuOpen ] );

	return (
		<div className="airygen-shell min-h-screen bg-slate-100 text-slate-900">
			<header className="relative isolate overflow-hidden bg-white/90 shadow-sm backdrop-blur">
				<div className="absolute inset-0 -z-10 overflow-hidden">
					<div className="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-br from-sky-100 via-white to-sky-50 opacity-60 blur-3xl" />
				</div>
				<div className="border-b border-slate-200 px-6 py-3">
					<div className="flex items-center justify-between">
						<a
							href="https://www.airygen.com/?utm_source=airygen-seo"
							target="_blank"
							className="flex items-center gap-4" rel="noreferrer"
						>
							{ logoUrl ? (
								<img
									src={ logoUrl }
									alt={ title }
									width={ 200 }
									height={ 60 }
									className="h-auto w-auto max-h-16"
								/>
							) : (
								<div className="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-600">
									AS
								</div>
							) }
							<div>
								{ logoUrl ? null : (
									<p className="text-xs font-semibold uppercase tracking-wide text-sky-600">
										Airygen SEO
									</p>
								) }
								<h1
									className={ classNames(
										'text-2xl font-bold text-slate-900',
										logoUrl ? '' : 'mt-1',
									) }
								>
									{ logoUrl ? (
										<span className="sr-only">{ title }</span>
									) : (
										title
									) }
								</h1>
							</div>
						</a>
						<nav
							aria-label={ __( 'Primary', 'airygen-seo' ) }
							className="_airygen_primary_menu hidden items-center gap-2 md:flex"
							data-airygen-e2e="primary-menu"
						>
							{ pages.map( ( page ) => {
								const isActivePage = page.name === activePage;
								return (
									<button
										key={ page.name }
										type="button"
										onClick={ () => onSelectPage( page.name ) }
										data-airygen-e2e={ `primary-menu-button-${ page.name }` }
										className={ classNames(
											'rounded-full px-4 py-2 text-sm font-medium transition-colors',
											isActivePage
												? 'bg-sky-500 text-white shadow-sm'
												: 'text-slate-500 hover:text-slate-900 hover:bg-slate-100',
										) }
										aria-current={ isActivePage ? 'page' : undefined }
									>
										{ page.title }
									</button>
								);
							} ) }
						</nav>
					</div>
				</div>
			</header>
			<main className="flex-1">
				<div className="w-full px-6 pb-6 pt-0">
					{ pages.length > 0 ? (
						<nav className="mb-4 mt-4 flex flex-wrap gap-2 md:hidden">
							{ pages.map( ( page ) => {
								const isActivePage = page.name === activePage;
								return (
									<button
										key={ page.name }
										type="button"
										onClick={ () => onSelectPage( page.name ) }
										className={ classNames(
											'rounded-full px-4 py-2 text-sm font-medium transition-colors',
											isActivePage
												? 'bg-sky-500 text-white'
												: 'border border-slate-200 bg-white text-slate-600 hover:border-sky-200 hover:text-slate-900',
										) }
										aria-current={ isActivePage ? 'page' : undefined }
									>
										{ page.title }
									</button>
								);
							} ) }
						</nav>
					) : null }
					{ loadErrorMessage ? (
						<div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
							<div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
								<div>
									<p className="font-semibold">{ __( 'We could not load the settings. Please try again.', 'airygen-seo' ) }</p>
									<p className="text-rose-700">{ loadErrorMessage }</p>
								</div>
								<Button
									variant="outline"
									onClick={ onRetryLoad }
									loading={ isLoading }
								>
									{ __( 'Reload', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
					) : null }
					{ tabs.length > 0 ? (
						<>
							<nav className="hidden md:block -mx-6">
								<div className="border-b border-slate-200 bg-white px-6">
									<ul
										ref={ tabsListRef }
										className="flex flex-wrap items-center text-sm font-medium text-slate-500"
									>
										{ visibleTabs.map( ( tab ) => {
											const isActive = tab.name === activeTab;
											const Icon = tab.icon;

											return (
												<li key={ tab.name } className="me-2 md:mb-0">
													<button
														ref={ ( node ) => {
															if ( node ) {
																node.dataset.tab = tab.name;
															}
															tabRefs.current[ tab.name ] = node;
														} }
														type="button"
														className={ classNames(
															'group inline-flex items-center gap-2 rounded-t-lg border-b-2 px-4 py-3 transition-colors',
															isActive
																? 'border-sky-500 bg-white text-sky-600 shadow-sm'
																: 'border-transparent bg-white text-slate-500 hover:border-sky-200 hover:text-slate-700',
														) }
														onClick={ () => onSelectTab( tab.name ) }
													>
														{ Icon ? (
															<Icon
																className={ classNames(
																	'h-5 w-5',
																	isActive
																		? 'text-sky-600'
																		: 'text-slate-400 group-hover:text-slate-500',
																) }
																aria-hidden="true"
															/>
														) : null }
														<span>{ tab.title }</span>
													</button>
												</li>
											);
										} ) }
										{ hasOverflow ? (
											<li ref={ overflowMenuRef } className="relative me-2 md:mb-0">
												<button
													type="button"
													className="_airygen-setting-hamburger inline-flex items-center rounded-t-lg border-b-2 border-transparent px-4 py-3 text-slate-500 transition-colors hover:border-sky-200 hover:text-slate-700"
													onClick={ () =>
														setIsOverflowMenuOpen( ( prev ) => ! prev )
													}
													aria-haspopup="menu"
													aria-expanded={ isOverflowMenuOpen }
												>
													<span aria-hidden="true">…</span>
													<span className="sr-only">
														{ __( 'More modules', 'airygen-seo' ) }
													</span>
												</button>
												{ isOverflowMenuOpen ? (
													<div className="absolute right-0 z-20 mt-2 w-[580px] rounded-md border border-slate-200 bg-white p-2 text-sm text-slate-600 shadow-lg">
														<div className="grid max-h-[420px] grid-cols-3 gap-1 overflow-auto">
															{ overflowList.map( ( tab ) => {
																const Icon = tab.icon;
																return (
																	<button
																		key={ tab.name }
																		type="button"
																		className="flex min-w-[140px] items-center justify-start gap-2 rounded px-2 py-2 text-left hover:bg-slate-50"
																		onClick={ () => {
																			setIsOverflowMenuOpen( false );
																			onSelectTab( tab.name );
																		} }
																	>
																		{ Icon ? (
																			<Icon
																				className="h-4 w-4"
																				aria-hidden="true"
																			/>
																		) : null }
																		<span>{ tab.title }</span>
																	</button>
																);
															} ) }
														</div>
													</div>
												) : null }
											</li>
										) : null }
									</ul>
								</div>
							</nav>
							<div className="mb-4 mt-4 flex flex-col gap-4 md:hidden">
								<div className="flex flex-wrap gap-2">
									{ tabs.map( ( tab ) => {
										const isActive = tab.name === activeTab;
										const Icon = tab.icon;
										return (
											<button
												key={ tab.name }
												type="button"
												onClick={ () => onSelectTab( tab.name ) }
												className={ classNames(
													'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition-colors',
													isActive
														? 'border-sky-500 bg-sky-500 text-white'
														: 'border-slate-200 bg-white text-slate-600 hover:border-sky-200 hover:text-slate-900',
												) }
											>
												{ Icon ? (
													<Icon
														className={ classNames(
															'h-5 w-5',
															isActive ? 'text-white' : 'text-slate-400',
														) }
														aria-hidden="true"
													/>
												) : null }
												{ tab.title }
											</button>
										);
									} ) }
								</div>
								{ showGlobalSave ? (
									<div className="flex justify-end">
										<Button
											variant="outline"
											onClick={ onSave }
											loading={ isSaving }
											disabled={ ! isDirty || isSaving }
										>
											{ isSaving
												? getSavingLabel()
												: getSaveChangesLabel() }
										</Button>
									</div>
								) : null }
							</div>
						</>
					) : null }
					<div className="mt-6">{ children }</div>
				</div>
			</main>
			{ notice ? (
				<div className="pointer-events-none fixed right-[15px] top-[80px] z-50 flex max-w-sm flex-col gap-3 md:right-[30px]">
					<Toast
						status={ notice.status }
						message={ notice.message }
						onDismiss={ onDismissNotice }
					/>
				</div>
			) : null }
		</div>
	);
};

export default AdminShell;
