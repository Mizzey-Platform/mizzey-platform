/**
 * What every section of the library shares in the editor (spec 066, "The section library"; SSC-01).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. It adds the common
 * "Show on the site" setting to the sidebar of every library section, and marks a hidden section in the canvas so
 * the editor can see it is hidden while its content stays there to edit. The storefront side is
 * MizzeySite\Blocks\SectionLibrary::hideHidden().
 *
 * In the Site Editor, whose settings are loaded once for every page and template it opens, it also offers only the
 * library's sections while the front page (in any language) is the entity being edited, and gives the inserter
 * back when another entity is opened. The post editor is narrowed on the server.
 */
( function ( wp ) {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const sections = ( window.mizzeySections && window.mizzeySections.blocks ) || [];
	const isSection = ( name ) => sections.indexOf( name ) !== -1;
	const isHidden = ( attributes ) => attributes && attributes.visible === false;

	const withShowSetting = createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
		if ( ! isSection( props.name ) ) {
			return el( BlockEdit, props );
		}
		const hidden = isHidden( props.attributes );
		return el(
			Fragment,
			null,
			el( BlockEdit, props ),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Visibility', 'mizzey-site' ), initialOpen: true },
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Show on the site', 'mizzey-site' ),
						help: hidden
							? __( 'Hidden: visitors do not see this section. Its content is kept here.', 'mizzey-site' )
							: __( 'Visitors see this section.', 'mizzey-site' ),
						checked: ! hidden,
						onChange: ( shown ) => props.setAttributes( { visible: shown } ),
					} )
				)
			)
		);
	}, 'withMizzeyShowSetting' );

	const withHiddenMark = createHigherOrderComponent( ( BlockListBlock ) => ( props ) => {
		if ( ! isSection( props.name ) || ! isHidden( props.attributes ) ) {
			return el( BlockListBlock, props );
		}
		const wrapperProps = Object.assign( {}, props.wrapperProps, { 'data-mizzey-hidden': __( 'Hidden on the site', 'mizzey-site' ) } );
		return el( BlockListBlock, Object.assign( {}, props, {
			className: [ props.className, 'is-mizzey-hidden' ].filter( Boolean ).join( ' ' ),
			wrapperProps,
		} ) );
	}, 'withMizzeyHiddenMark' );

	/* The Site Editor: sections only while the front page (in any language) is the entity being edited. Its settings
	   are loaded once for everything it opens, so the narrowing is asked of each insertion instead. */
	const frontPages = ( ( window.mizzeySections && window.mizzeySections.frontPages ) || [] ).map( Number );
	function editingFrontPage() {
		const editor = wp.data.select( 'core/editor' );
		return !! editor && editor.getCurrentPostType && editor.getCurrentPostType() === 'page' &&
			frontPages.indexOf( Number( editor.getCurrentPostId() ) ) !== -1;
	}
	// Registered in every editor (this script loads in the head, before the body says which editor it is). In the
	// post editor it agrees with the server, which has already narrowed the front page's settings.
	addFilter( 'blockEditor.__unstableCanInsertBlockType', 'mizzey/front-page-sections', ( canInsert, blockType ) => {
		const name = blockType && ( typeof blockType === 'string' ? blockType : blockType.name );
		return canInsert && ( ! editingFrontPage() || isSection( name ) );
	} );

	addFilter( 'editor.BlockEdit', 'mizzey/section-show-setting', withShowSetting );
	addFilter( 'editor.BlockListBlock', 'mizzey/section-hidden-mark', withHiddenMark );

	/* ---- Parts the home sections' editor scripts share (spec 066, slice 5), as window.mizzeyLibrary ---------------- */

	const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
	const { Button, SelectControl, TextControl, RangeControl, FormTokenField, Placeholder } = wp.components;
	const { useSelect } = wp.data;
	const { useState } = wp.element;
	const data = window.mizzeySections || {};

	/** An image from the media library: its preview, and a button to choose or replace it. */
	function ImageChooser( { label, imageId, onChange, help } ) {
		const media = useSelect( ( select ) => ( imageId ? select( 'core' ).getMedia( imageId, { context: 'view' } ) : null ), [ imageId ] );
		return el(
			'div',
			{ className: 'mizzey-library-image' },
			media && media.source_url ? el( 'img', { src: media.source_url, alt: '', className: 'mizzey-library-image__preview' } ) : null,
			el( MediaUploadCheck, null, el( MediaUpload, {
				allowedTypes: [ 'image' ],
				value: imageId || undefined,
				onSelect: ( chosen ) => onChange( chosen && chosen.id ? chosen.id : 0 ),
				render: ( { open } ) => el( Button, { variant: 'secondary', onClick: open, 'aria-label': label }, imageId ? __( 'Replace image', 'mizzey-site' ) : __( 'Choose image', 'mizzey-site' ) ),
			} ) ),
			imageId ? el( Button, { variant: 'link', isDestructive: true, onClick: () => onChange( 0 ) }, __( 'Remove image', 'mizzey-site' ) ) : null,
			help ? el( 'p', { className: 'components-base-control__help' }, help ) : null
		);
	}

	/** The terms of a product taxonomy, for a select: the store's own names in the page's language. */
	function useTerms( taxonomy, enabled ) {
		return useSelect( ( select ) => ( enabled ? select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, { per_page: 100, hide_empty: false, _fields: 'id,name' } ) : null ), [ taxonomy, enabled ] ) || [];
	}

	/** Where a product section's products come from: a badge, a category, a collection, the newest, on sale, or picked. */
	function SourceControls( { attributes, setAttributes } ) {
		const source = attributes.source || 'badge';
		const categories = useTerms( 'product_cat', source === 'category' );
		const collections = useTerms( 'product_collection', source === 'collection' );
		const [ search, setSearch ] = useState( '' );
		const picked = Array.isArray( attributes.products ) ? attributes.products : [];
		const found = useSelect( ( select ) => {
			if ( source !== 'products' ) {
				return [];
			}
			const store = select( 'core' );
			const named = picked.length ? store.getEntityRecords( 'postType', 'product', { include: picked, per_page: 100, _fields: 'id,title' } ) || [] : [];
			const hits = search.length > 1 ? store.getEntityRecords( 'postType', 'product', { search, per_page: 20, _fields: 'id,title' } ) || [] : [];
			return named.concat( hits );
		}, [ source, search, picked.join( ',' ) ] );
		const title = ( p ) => ( p.title && ( p.title.rendered || p.title.raw ) ) || String( p.id );
		const nameOf = ( id ) => {
			const hit = found.find( ( p ) => p.id === id );
			return hit ? `${ title( hit ) } (#${ id })` : `#${ id }`;
		};
		const idOf = ( token ) => {
			const match = /#(\d+)\)?$/.exec( token );
			if ( match ) {
				return Number( match[ 1 ] );
			}
			const hit = found.find( ( p ) => title( p ) === token );
			return hit ? hit.id : 0;
		};
		const termOptions = ( terms ) => [ { value: 0, label: __( 'Choose…', 'mizzey-site' ) } ].concat( terms.map( ( t ) => ( { value: t.id, label: t.name } ) ) );

		return el(
			Fragment,
			null,
			el( SelectControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: __( 'Products from', 'mizzey-site' ),
				value: source,
				options: [
					{ value: 'badge', label: __( 'A badge', 'mizzey-site' ) },
					{ value: 'category', label: __( 'A category', 'mizzey-site' ) },
					{ value: 'collection', label: __( 'A collection', 'mizzey-site' ) },
					{ value: 'newest', label: __( 'The newest', 'mizzey-site' ) },
					{ value: 'sale', label: __( 'On sale', 'mizzey-site' ) },
					{ value: 'products', label: __( 'Picked by hand', 'mizzey-site' ) },
				],
				onChange: ( value ) => setAttributes( { source: value } ),
			} ),
			source === 'badge' ? el( SelectControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: __( 'Badge', 'mizzey-site' ),
				value: attributes.badge || 'trending',
				options: [
					{ value: 'trending', label: __( 'Trending', 'mizzey-site' ) },
					{ value: 'new', label: __( 'New', 'mizzey-site' ) },
					{ value: 'limited', label: __( 'Limited', 'mizzey-site' ) },
					{ value: 'best-seller', label: __( 'Best Seller', 'mizzey-site' ) },
				],
				onChange: ( value ) => setAttributes( { badge: value } ),
			} ) : null,
			source === 'category' || source === 'collection' ? el( SelectControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: source === 'category' ? __( 'Category', 'mizzey-site' ) : __( 'Collection', 'mizzey-site' ),
				value: attributes.term || 0,
				options: termOptions( source === 'category' ? categories : collections ),
				onChange: ( value ) => setAttributes( { term: Number( value ) } ),
			} ) : null,
			source === 'products' ? el( FormTokenField, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: __( 'Hand-picked products', 'mizzey-site' ),
				// The storefront shows every pick up to this limit (ProductSections::MAX), so the field takes no more.
				maxLength: data.maxCount || 24,
				value: picked.map( nameOf ),
				suggestions: found.filter( ( p ) => picked.indexOf( p.id ) === -1 ).map( ( p ) => `${ title( p ) } (#${ p.id })` ),
				onInputChange: setSearch,
				onChange: ( tokens ) => setAttributes( { products: tokens.map( idOf ).filter( ( id ) => id > 0 ) } ),
				__experimentalExpandOnFocus: true,
			} ) : null,
			source !== 'products' ? el( RangeControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: __( 'How many products', 'mizzey-site' ),
				min: 1,
				max: data.maxCount || 24,
				value: attributes.count || 12,
				onChange: ( value ) => setAttributes( { count: Number( value ) || 12 } ),
			} ) : null,
			el( TextControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				type: 'url',
				label: __( 'View all link', 'mizzey-site' ),
				help: __( 'Optional. Without one, a section by category or collection links to its page.', 'mizzey-site' ),
				value: attributes.link || '',
				onChange: ( value ) => setAttributes( { link: value } ),
			} )
		);
	}

	/** The section as the storefront renders it, with a note when it has nothing to show. */
	function ServerPreview( { name, attributes, empty } ) {
		const SSR = wp.serverSideRender;
		const shown = Object.assign( {}, attributes, { visible: true } );
		return el( SSR, {
			block: name,
			attributes: shown,
			EmptyResponsePlaceholder: () => el( Placeholder, { label: empty.label, instructions: empty.text } ),
		} );
	}

	window.mizzeyLibrary = { ImageChooser, SourceControls, ServerPreview, TextControl };
} )( window.wp );
