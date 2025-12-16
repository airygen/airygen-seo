declare module '@wordpress/block-editor' {
	export function useBlockProps(
		props?: Record<string, unknown>,
	): Record<string, unknown>;
}
