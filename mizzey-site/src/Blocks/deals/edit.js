/**
 * The deals section in the editor (spec 066, slice 5: HOME-07, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the Indigo panel
 * as the storefront renders it, cards included. The sidebar holds the heading, an optional tag beside it, the line
 * under it and where the
 * products come from (on sale, a badge, a category, a collection, the newest, or picked by hand). The layout is fixed
 * in style.css (G11).
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
		const text = ( key, label, help ) => el( TextControl, {
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
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
					text( 'heading', __( 'Heading', 'mizzey-site' ), __( 'In this page\'s language. For example: Deals of the week.', 'mizzey-site' ) ),
					text( 'headingTag', __( 'Label beside the heading', 'mizzey-site' ), __( 'Optional. A short tag next to the heading, for example: This week.', 'mizzey-site' ) ),
					text( 'line', __( 'Line', 'mizzey-site' ), __( 'Optional. For example: Up to 25% off this week.', 'mizzey-site' ) ),
					el( lib.SourceControls, { attributes, setAttributes } )
				)
			),
			el( 'div', blockProps, el( lib.ServerPreview, {
				name: 'mizzey/deals',
				attributes,
				empty: {
					label: attributes.heading || __( 'Deals', 'mizzey-site' ),
					text: __( 'No product matches this rule yet, so visitors do not see this section. It appears once a product matches.', 'mizzey-site' ),
				},
			} ) )
		);
	}

	registerBlockType( 'mizzey/deals', { edit: Edit, save: () => null } );
} )( window.wp );
