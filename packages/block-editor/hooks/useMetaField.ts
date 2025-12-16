import { useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

const useMetaField = (
	metaKey: string,
): [ string, ( value: string ) => void, boolean ] => {
	const postType = useSelect(
		( select ) =>
			(
				select( 'core/editor' ) as {
					getCurrentPostType?: () => string | null;
				}
			).getCurrentPostType?.(),
		[],
	);

	const [ meta, setMeta, originalMeta ] = useEntityProp(
		'postType',
		postType ?? 'post',
		'meta',
	);

	const current =
		typeof meta?.[ metaKey ] === 'string' ? ( meta[ metaKey ] as string ) : '';

	const updateValue = useCallback(
		( next: string ) => {
			setMeta( {
				...( meta ?? {} ),
				[ metaKey ]: next,
			} );
		},
		[ meta, metaKey, setMeta ],
	);

	return [ current, updateValue, typeof originalMeta !== 'undefined' ];
};

export default useMetaField;
