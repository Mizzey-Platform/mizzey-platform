/**
 * The category shortcuts in the editor (spec 066, slice 5: HOME-05, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the section as
 * the storefront renders it. Which categories show, and their order, is set on each category ("Show on the home page"
 * and its position, specs/056); the sidebar holds the heading and the words and link of the all-categories tile.
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
					el( 'p', null, __( 'The categories marked "Show on the home page" appear here, in their position. Set them on Products, Categories.', 'mizzey-site' ) ),
					text( 'heading', __( 'Heading', 'mizzey-site' ), __( 'In this page\'s language. For example: Shop by category.', 'mizzey-site' ) ),
					text( 'allLabel', __( 'All categories label', 'mizzey-site' ), __( 'Optional. The last tile\'s words.', 'mizzey-site' ) ),
					text( 'allUrl', __( 'All categories link', 'mizzey-site' ), __( 'Optional. Without one, the tile opens the shop.', 'mizzey-site' ), 'url' )
				)
			),
			el( 'div', blockProps, el( lib.ServerPreview, {
				name: 'mizzey/category-shortcuts',
				attributes,
				empty: {
					label: attributes.heading || __( 'Category shortcuts', 'mizzey-site' ),
					text: __( 'No category is marked "Show on the home page" yet, so visitors do not see this section.', 'mizzey-site' ),
				},
			} ) )
		);
	}

	registerBlockType( 'mizzey/category-shortcuts', { edit: Edit, save: () => null } );
} )( window.wp );
