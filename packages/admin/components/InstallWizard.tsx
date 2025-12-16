import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import Modal from './Modal';
import Button from './Button';
import Toggle from './Toggle';
import Checkbox from './Checkbox';

// ─── Types ────────────────────────────────────────────────────────────────────

interface WizardModuleCard {
	key: string;
	label: string;
	description: string;
}

interface WizardQuestion {
	id: number;
	question: string;
	detail?: string;
	modules: WizardModuleCard[];
}

interface InstallWizardProps {
	onDismiss: () => void;
	onApply: ( enabledModules: Record<string, boolean> ) => void;
	restBase: string;
}

// ─── Wizard question data ─────────────────────────────────────────────────────

const QUESTIONS: WizardQuestion[] = [
	{
		id: 1,
		question: __( 'Does your website represent a business with a physical location?', 'airygen-seo' ),
		detail: __( 'For example, a restaurant, clinic, law firm, gym, or retail store.', 'airygen-seo' ),
		modules: [
			{
				key: 'localSeo',
				label: __( 'Local SEO', 'airygen-seo' ),
				description: __( 'Adds LocalBusiness schema to your site so Google can show your address, phone number, and opening hours in local search results and Maps — making it easier for nearby customers to find you.', 'airygen-seo' ),
			},
			{
				key: 'schema',
				label: __( 'Schema Markup', 'airygen-seo' ),
				description: __( 'Alongside local data, also outputs Organization and WebSite schema to help Google better understand your brand.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 2,
		question: __( 'Does your website sell products or services online?', 'airygen-seo' ),
		detail: __( 'For example, a WooCommerce store or any site with a shopping cart.', 'airygen-seo' ),
		modules: [
			{
				key: 'wooCommerceSeo',
				label: __( 'WooCommerce SEO', 'airygen-seo' ),
				description: __( 'Automatically generates Product schema for every product page so Google can display price, stock status, and star ratings directly in search results.', 'airygen-seo' ),
			},
			{
				key: 'schema',
				label: __( 'Schema Markup', 'airygen-seo' ),
				description: __( 'Adds site-level Organization schema to help Google identify your brand and store.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 3,
		question: __( 'Does your site have multiple language versions or serve users in different countries?', 'airygen-seo' ),
		detail: __( 'For example, separate English and Spanish versions, or different URLs targeting different regions.', 'airygen-seo' ),
		modules: [
			{
				key: 'hreflang',
				label: __( 'Hreflang', 'airygen-seo' ),
				description: __( 'Automatically adds hreflang tags to every page so Google serves the right language version to the right audience, preventing different variants from competing with each other in search rankings.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 4,
		question: __( 'Do you want links to your site to display a rich preview when shared on social media?', 'airygen-seo' ),
		modules: [
			{
				key: 'social',
				label: __( 'Social Cards', 'airygen-seo' ),
				description: __( 'Automatically generates Open Graph and Twitter Card meta tags for every post so shared links display your chosen image and title instead of letting platforms pick a random one.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 5,
		question: __( 'Does your site publish long-form articles, tutorials, or in-depth knowledge content?', 'airygen-seo' ),
		detail: __( 'For example, a blog, technical documentation site, or educational platform.', 'airygen-seo' ),
		modules: [
			{
				key: 'toc',
				label: __( 'Table of Contents', 'airygen-seo' ),
				description: __( 'Automatically generates a table of contents at the top of long posts so readers can jump straight to any section. Google sometimes surfaces TOC links directly in search results.', 'airygen-seo' ),
			},
			{
				key: 'relatedPosts',
				label: __( 'Related Posts', 'airygen-seo' ),
				description: __( 'Recommends related posts at the bottom of each article based on categories and tags, keeping readers engaged and reducing bounce rate.', 'airygen-seo' ),
			},
			{
				key: 'topicCluster',
				label: __( 'Topic Cluster', 'airygen-seo' ),
				description: __( 'Helps you manage a topic cluster structure (pillar → cluster → supporting articles) and automatically renders a topic navigation block within posts, making it easy for readers to explore related content while signaling topical depth to Google.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 6,
		question: __( 'Does your site have multiple authors or use many categories and tags to organize content?', 'airygen-seo' ),
		modules: [
			{
				key: 'authorSeo',
				label: __( 'Author SEO', 'airygen-seo' ),
				description: __( 'Generates Person schema for every author page to establish authorship identity and strengthen the E-E-A-T signals Google increasingly factors into rankings.', 'airygen-seo' ),
			},
			{
				key: 'taxonomySeo',
				label: __( 'Taxonomy SEO', 'airygen-seo' ),
				description: __( 'Lets you customize meta titles and descriptions for taxonomy archive pages and control which ones Google should index, preventing SEO authority from being diluted across low-value pages.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 7,
		question: __( 'Has your site ever changed its URL structure, or do you know some links lead to 404 pages?', 'airygen-seo' ),
		modules: [
			{
				key: 'redirects',
				label: __( 'Redirects', 'airygen-seo' ),
				description: __( 'Set up 301/302 redirects to seamlessly transfer traffic and SEO ranking power from old URLs to new ones so your hard-earned rankings are never lost.', 'airygen-seo' ),
			},
			{
				key: 'notFoundManager',
				label: __( '404 Manager', 'airygen-seo' ),
				description: __( 'Automatically logs every 404 visit so you can see at a glance which broken links need fixing, and set up redirects directly from the interface.', 'airygen-seo' ),
			},
			{
				key: 'brokenLinkChecker',
				label: __( 'Broken Link Checker', 'airygen-seo' ),
				description: __( 'Actively crawls your entire site to surface all broken internal and external links before Google finds them and uses them against your rankings.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 8,
		question: __( 'Would you like search engines to index your new and updated content immediately after publishing?', 'airygen-seo' ),
		modules: [
			{
				key: 'instantIndexing',
				label: __( 'Instant Indexing', 'airygen-seo' ),
				description: __( 'Uses the IndexNow protocol to push new and updated URLs to Bing and other supported search engines the moment content is published or changed — no waiting for crawlers. Especially valuable for news sites and time-sensitive content.', 'airygen-seo' ),
			},
		],
	},
	{
		id: 9,
		question: __( 'Would you like AI tools like ChatGPT and Perplexity to more easily discover and cite your site?', 'airygen-seo' ),
		detail: __( 'AI search is changing how people find information — make sure your site is visible in the AI era.', 'airygen-seo' ),
		modules: [
			{
				key: 'llmsTxt',
				label: __( 'LLMs.txt', 'airygen-seo' ),
				description: __( 'Automatically generates an llms.txt file — like a sitemap for AI — so tools like ChatGPT and Perplexity know what content on your site is available to cite.', 'airygen-seo' ),
			},
			{
				key: 'markdownForAgents',
				label: __( 'Markdown for Agents', 'airygen-seo' ),
				description: __( 'Automatically converts your posts into AI-friendly Markdown so AI agents can read and understand your content without struggling to parse HTML.', 'airygen-seo' ),
			},
		],
	},
];

// ─── Sub-components ───────────────────────────────────────────────────────────

const ModuleCard = ( {
	mod,
	enabled,
	onToggle,
}: {
	mod: WizardModuleCard;
	enabled: boolean;
	onToggle: ( key: string, val: boolean ) => void;
} ) => (
	<div className="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<div className="flex items-start justify-between gap-3">
			<span className="text-sm font-semibold text-slate-800">{ mod.label }</span>
			<Toggle
				label={ mod.label }
				hideLabelText
				checked={ enabled }
				onChange={ ( val ) => onToggle( mod.key, val ) }
			/>
		</div>
		<p className="text-xs text-slate-500">{ mod.description }</p>
	</div>
);

// ─── Main component ───────────────────────────────────────────────────────────

const InstallWizard = ( {
	onDismiss,
	onApply,
	restBase,
}: InstallWizardProps ) => {
	// 0 = welcome, 1 = questions Q1-Q10 (index 0-9), 11 = summary
	const [ step, setStep ] = useState<number>( 0 );
	const [ answers, setAnswers ] = useState<Record<number, boolean | null>>( {} );
	const [ moduleToggles, setModuleToggles ] = useState<Record<string, boolean>>( {} );
	const [ dontShowAgain, setDontShowAgain ] = useState<boolean>( false );
	const [ isApplying, setIsApplying ] = useState<boolean>( false );

	const TOTAL_QUESTIONS = QUESTIONS.length; // 10
	const SUMMARY_STEP = TOTAL_QUESTIONS + 1; // 11

	// Current question (step 1..10 → question index 0..9)
	const currentQuestion = step >= 1 && step <= TOTAL_QUESTIONS ? QUESTIONS[ step - 1 ] : null;
	const questionNumber = step; // 1-based

	// When user answers Yes to a question, default all its modules to on.
	const handleAnswer = ( yes: boolean ) => {
		if ( ! currentQuestion ) {
			return;
		}
		setAnswers( ( prev ) => ( { ...prev, [ currentQuestion.id ]: yes } ) );
		if ( yes ) {
			setModuleToggles( ( prev ) => {
				const next = { ...prev };
				currentQuestion.modules.forEach( ( mod ) => {
					if ( ! ( mod.key in next ) ) {
						next[ mod.key ] = true;
					}
				} );
				return next;
			} );
		}
	};

	const handleModuleToggle = ( key: string, val: boolean ) => {
		setModuleToggles( ( prev ) => ( { ...prev, [ key ]: val } ) );
	};

	const handleNext = () => {
		if ( step === 0 ) {
			setStep( 1 );
		} else if ( step < TOTAL_QUESTIONS ) {
			setStep( ( s ) => s + 1 );
		} else {
			setStep( SUMMARY_STEP );
		}
	};

	const handlePrev = () => {
		if ( step > 1 ) {
			setStep( ( s ) => s - 1 );
		} else if ( step === 1 ) {
			setStep( 0 );
		} else if ( step === SUMMARY_STEP ) {
			setStep( TOTAL_QUESTIONS );
		}
	};

	const handleApply = async () => {
		setIsApplying( true );

		const toEnable = Object.entries( moduleToggles )
			.filter( ( [ , on ] ) => on )
			.reduce<Record<string, boolean>>( ( acc, [ key ] ) => {
				acc[ key ] = true;
				return acc;
			}, {} );

		try {
			if ( Object.keys( toEnable ).length > 0 ) {
				await apiFetch( {
					path: `${ restBase }/settings`,
					method: 'POST',
					data: { settings: { modules: toEnable } },
				} );
			}

			if ( dontShowAgain ) {
				await apiFetch( {
					path: `${ restBase }/wizard/dismiss`,
					method: 'POST',
					data: { dismissed: true },
				} );
			}

			onApply( toEnable );
		} finally {
			setIsApplying( false );
			onDismiss();
		}
	};

	const handleClose = async () => {
		if ( dontShowAgain ) {
			await apiFetch( {
				path: `${ restBase }/wizard/dismiss`,
				method: 'POST',
				data: { dismissed: true },
			} );
		}
		onDismiss();
	};

	// Collect all recommended modules from Yes-answered questions (de-duped by key)
	const allRecommendedModules = ( () => {
		const seen = new Set<string>();
		const result: WizardModuleCard[] = [];
		QUESTIONS.forEach( ( q ) => {
			if ( answers[ q.id ] !== true ) {
				return;
			}
			q.modules.forEach( ( mod ) => {
				if ( ! seen.has( mod.key ) ) {
					seen.add( mod.key );
					result.push( mod );
				}
			} );
		} );
		return result;
	} )();

	const enabledRecommended = allRecommendedModules.filter(
		( mod ) => moduleToggles[ mod.key ],
	);

	// ─── Progress indicator ───────────────────────────────────────────────────

	const progressLabel =
		step >= 1 && step <= TOTAL_QUESTIONS
			? `${ questionNumber } / ${ TOTAL_QUESTIONS }`
			: null;

	// ─── Footer ───────────────────────────────────────────────────────────────

	const footer = (
		<div className="flex items-center justify-between gap-4">
			<Checkbox
				label={ __( "Got it, don't show again", 'airygen-seo' ) }
				checked={ dontShowAgain }
				onChange={ setDontShowAgain }
			/>
			<div className="flex items-center gap-2">
				{ step > 0 && (
					<Button variant="secondary" onClick={ handlePrev } disabled={ isApplying }>
						{ __( '← Back', 'airygen-seo' ) }
					</Button>
				) }
				{ step < SUMMARY_STEP && (
					<Button variant="primary" onClick={ handleNext }>
						{ step === 0
							? __( 'Get started →', 'airygen-seo' )
							: __( 'Next →', 'airygen-seo' ) }
					</Button>
				) }
				{ step === SUMMARY_STEP && (
					<Button variant="primary" onClick={ handleApply } loading={ isApplying }>
						{ __( 'Apply & finish', 'airygen-seo' ) }
					</Button>
				) }
			</div>
		</div>
	);

	// ─── Body content ─────────────────────────────────────────────────────────

	let body: JSX.Element;

	if ( step === 0 ) {
		body = (
			<div className="flex flex-col items-center gap-6 py-4 text-center">
				<div className="text-4xl">✦</div>
				<div>
					<h2 className="text-xl font-semibold text-slate-900">
						{ __( 'Welcome to Airygen SEO!', 'airygen-seo' ) }
					</h2>
					<p className="mt-3 text-sm leading-relaxed text-slate-600">
						{ __( 'Answer a few quick questions and we\'ll recommend the right SEO modules for your site — core modules are already enabled by default.', 'airygen-seo' ) }
					</p>
					<p className="mt-2 text-sm text-slate-500">
						{ __( "Don't worry — every question is just Yes or No. No technical knowledge needed. It takes about 2 minutes. 😊", 'airygen-seo' ) }
					</p>
				</div>
			</div>
		);
	} else if ( step === SUMMARY_STEP ) {
		body = (
			<div className="flex flex-col gap-6">
				<p className="text-sm text-slate-600">
					{ __( 'All done! Core modules (On-Page SEO, Breadcrumbs, Sitemap, Image SEO, Robots, Site Verification, Link Counter, Link Suggestions, Score Calculator) are already enabled by default.', 'airygen-seo' ) }
				</p>

				{ enabledRecommended.length > 0 && (
					<div>
						<h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-700">
							{ __( 'Additional modules to enable based on your answers', 'airygen-seo' ) }
						</h3>
						<div className="flex flex-wrap gap-2">
							{ enabledRecommended.map( ( mod ) => (
								<span
									key={ mod.key }
									className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200"
								>
									✓ { mod.label }
								</span>
							) ) }
						</div>
					</div>
				) }

				{ enabledRecommended.length === 0 && (
					<p className="text-sm text-slate-500">
						{ __( 'No additional modules recommended. You can enable more from the dashboard anytime.', 'airygen-seo' ) }
					</p>
				) }
			</div>
		);
	} else if ( currentQuestion ) {
		const answer = answers[ currentQuestion.id ];
		body = (
			<div className="flex flex-col gap-6">
				<div>
					<p className="text-base font-medium text-slate-800">
						{ currentQuestion.question }
					</p>
					{ currentQuestion.detail && (
						<p className="mt-1 text-sm text-slate-500">{ currentQuestion.detail }</p>
					) }
				</div>

				<div className="flex gap-3">
					<Button
						variant={ answer === true ? 'primary' : 'secondary' }
						onClick={ () => handleAnswer( true ) }
					>
						{ answer === true ? '✓ ' : '' }{ __( 'Yes', 'airygen-seo' ) }
					</Button>
					<Button
						variant={ answer === false ? 'secondary' : 'secondary' }
						onClick={ () => handleAnswer( false ) }
						className={ answer === false ? 'ring-2 ring-slate-400' : '' }
					>
						{ __( 'No', 'airygen-seo' ) }
					</Button>
				</div>

				{ answer === true && (
					<div className="flex flex-col gap-3">
						{ currentQuestion.modules.map( ( mod ) => {
							const enabled = moduleToggles[ mod.key ] ?? true;
							return (
								<ModuleCard
									key={ mod.key }
									mod={ mod }
									enabled={ enabled }
									onToggle={ handleModuleToggle }
								/>
							);
						} ) }
					</div>
				) }
			</div>
		);
	} else {
		body = <></>;
	}

	// ─── Modal title with progress ────────────────────────────────────────────

	const titleNode = (
		<span className="flex w-full items-center justify-between">
			<span className="inline-flex items-center gap-2">🧭 { __( 'Setup Wizard', 'airygen-seo' ) }</span>
			{ progressLabel && (
				<span className="mr-8 text-sm font-normal text-slate-400">
					{ progressLabel }
				</span>
			) }
		</span>
	);

	return (
		<Modal
			isOpen
			onClose={ handleClose }
			title={ titleNode as unknown as string }
			maxWidth="max-w-2xl"
			footer={ footer }
		>
			{ body }
		</Modal>
	);
};

export default InstallWizard;
