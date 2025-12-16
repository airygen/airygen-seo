import { __ } from '@wordpress/i18n';

type PreviewCodeSamplesProps = {
	injectedCss: string;
	htmlSample: string;
	injectedCssId: string;
	htmlSampleId: string;
	rows?: number;
	injectedCssLabel?: string;
	htmlSampleLabel?: string;
};

const PreviewCodeSamples = ( {
	injectedCss,
	htmlSample,
	injectedCssId,
	htmlSampleId,
	rows = 12,
	injectedCssLabel = __( 'Injected CSS', 'airygen-seo' ),
	htmlSampleLabel = __( 'HTML Sample', 'airygen-seo' ),
}: PreviewCodeSamplesProps ) => (
	<div className="grid gap-4 md:grid-cols-2">
		<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
			<label className="block text-sm font-medium text-gray-800" htmlFor={ injectedCssId }>
				{ injectedCssLabel }
			</label>
			<textarea
				id={ injectedCssId }
				value={ injectedCss }
				readOnly
				rows={ rows }
				className="mt-1 airygen-field w-full font-mono text-xs"
			/>
			<p className="mt-2 text-xs text-slate-500">
				{ __(
					'Airygen automatically injects this CSS. You can override it in your global CSS if needed.',
					'airygen-seo',
				) }
			</p>
		</div>
		<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
			<label className="block text-sm font-medium text-gray-800" htmlFor={ htmlSampleId }>
				{ htmlSampleLabel }
			</label>
			<textarea
				id={ htmlSampleId }
				value={ htmlSample }
				readOnly
				rows={ rows }
				className="mt-1 airygen-field w-full font-mono text-xs"
			/>
			<p className="mt-2 text-xs text-slate-500">
				{ __(
					'This is the HTML structure used by Airygen. Use it to customize styles with your own CSS.',
					'airygen-seo',
				) }
			</p>
		</div>
	</div>
);

export default PreviewCodeSamples;
