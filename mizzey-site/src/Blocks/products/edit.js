/**
 * The products section in the editor (spec 066, slice 5: HOME-02, HOME-03, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the section as
 * the storefront renders it (the server's own render, cards included), so the editor sees the products the rule
 * selects today. The sidebar holds the heading and where the products come from: a badge, a category, a collection,
 * the newest, on sale, or picked by hand. The layout is fixed in the rail's stylesheet (G11).
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
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Heading', 'mizzey-site' ),
						help: __( 'In this page\'s language. For example: Trending now.', 'mizzey-site' ),
						value: attributes.heading || '',
						onChange: ( value ) => setAttributes( { heading: value } ),
					} ),
					el( lib.SourceControls, { attributes, setAttributes } )
				)
			),
			el( 'div', blockProps, el( lib.ServerPreview, {
				name: 'mizzey/products',
				attributes,
				empty: {
					label: attributes.heading || __( 'Products', 'mizzey-site' ),
					text: __( 'No product matches this rule yet, so visitors do not see this section. It appears once a product matches.', 'mizzey-site' ),
				},
			} ) )
		);
	}

	registerBlockType( 'mizzey/products', { edit: Edit, save: () => null } );
} )( window.wp );
