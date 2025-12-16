import { useCallback, useMemo } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { getMetaKeys } from '../config';

type PostDataField =
	| 'title'
	| 'description'
	| 'focusKeyphrase'
	| 'focusLongTail'
	| 'agentPrompt'
	| 'canonical'
	| 'robots'
	| 'schemaArticleType';

type PostDataValue = Record<PostDataField, string>;

const DEFAULT_POST_DATA: PostDataValue = {
	title: '',
	description: '',
	focusKeyphrase: '',
	focusLongTail: '',
	agentPrompt: '',
	canonical: '',
	robots: '',
	schemaArticleType: '',
};

const decodePostData = ( value: unknown ): PostDataValue => {
	if ( typeof value === 'string' && value.trim() !== '' ) {
		try {
			const decoded = JSON.parse( value ) as Partial<PostDataValue>;
			return {
				...DEFAULT_POST_DATA,
				...Object.fromEntries(
					Object.entries( decoded ?? {} ).map( ( [ key, item ] ) => [
						key,
						typeof item === 'string' ? item : '',
					] ),
				),
			};
		} catch {
			return DEFAULT_POST_DATA;
		}
	}

	if ( value && typeof value === 'object' ) {
		const decoded = value as Partial<PostDataValue>;
		return {
			...DEFAULT_POST_DATA,
			...Object.fromEntries(
				Object.entries( decoded ).map( ( [ key, item ] ) => [
					key,
					typeof item === 'string' ? item : '',
				] ),
			),
		};
	}

	return DEFAULT_POST_DATA;
};

const usePostDataField = (
	field: PostDataField,
): [string, ( value: string ) => void, boolean] => {
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
	const metaKey = getMetaKeys().postData;
	const currentData = useMemo(
		() => decodePostData( meta?.[ metaKey ] ),
		[ meta, metaKey ],
	);

	const updateValue = useCallback(
		( next: string ) => {
			const nextData: PostDataValue = {
				...currentData,
				[ field ]: next,
			};

			setMeta( {
				...( meta ?? {} ),
				[ metaKey ]: JSON.stringify( nextData ),
			} );
		},
		[ currentData, field, meta, metaKey, setMeta ],
	);

	return [ currentData[ field ], updateValue, typeof originalMeta !== 'undefined' ];
};

export default usePostDataField;
