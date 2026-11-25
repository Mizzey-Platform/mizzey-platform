/**
 * The featured collections in the editor (spec 066, slice 5: HOME-04, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the section as
 * the storefront renders it. Which collections show, and their order, is set on each collection ("Show on the home
 * page" and its position, specs/056); the sidebar holds the heading.
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
		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Section', 'mizzey-site' ), initialOpen: true },
					el( 'p', null, __( 'The collections marked "Show on the home page" appear here, in their position, up to four.', 'mizzey-site' ) ),
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Heading', 'mizzey-site' ),
						help: __( 'In this page\'s language. For example: Featured collections.', 'mizzey-site' ),
						value: attributes.heading || '',
						onChange: ( value ) => setAttributes( { heading: value } ),
					} )
				)
			),
			el( 'div', blockProps, el( lib.ServerPreview, {
				name: 'mizzey/featured-collections',
				attributes,
				empty: {
					label: attributes.heading || __( 'Featured collections', 'mizzey-site' ),
					text: __( 'No collection is marked "Show on the home page" yet, so visitors do not see this section.', 'mizzey-site' ),
				},
			} ) )
		);
	}

	registerBlockType( 'mizzey/featured-collections', { edit: Edit, save: () => null } );
} )( window.wp );
