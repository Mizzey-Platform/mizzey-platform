/**
 * The brand highlights in the editor (spec 066, slice 5: HOME-06, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the section as
 * the storefront renders it. Which brands show, and their order, is set on each brand ("Show on the home page" and
 * its position, specs/056); the sidebar holds the heading and the words and address of the link to all brands.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const lib = window.mizzeyLibrary;

	function Edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'mizzey-library-preview' } );
		const text = ( key, label, help, type ) => el( TextControl, {
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
			type: type || 'text',
			label,
			help,
			value: attributes[ key ] || '',
			onChange: ( value ) => setAttributes( { [ key ]: value } ),
		} );
		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Section', 'mizzey-site' ), initialOpen: true },
					el( 'p', null, __( 'The brands marked "Show on the home page" appear here, in their position.', 'mizzey-site' ) ),
					text( 'heading', __( 'Heading', 'mizzey-site' ), __( 'In this page\'s language. For example: Shop by brand.', 'mizzey-site' ) ),
					text( 'allLabel', __( 'All brands label', 'mizzey-site' ), __( 'Optional. The link at the heading\'s end.', 'mizzey-site' ) ),
					text( 'allUrl', __( 'All brands link', 'mizzey-site' ), __( 'Optional. The link shows only with its words and its address.', 'mizzey-site' ), 'url' )
				)
			),
			el( 'div', blockProps, el( lib.ServerPreview, {
				name: 'mizzey/brand-highlights',
				attributes,
				empty: {
					label: attributes.heading || __( 'Brand highlights', 'mizzey-site' ),
					text: __( 'No brand is marked "Show on the home page" yet, so visitors do not see this section.', 'mizzey-site' ),
				},
			} ) )
		);
	}

	registerBlockType( 'mizzey/brand-highlights', { edit: Edit, save: () => null } );
} )( window.wp );
