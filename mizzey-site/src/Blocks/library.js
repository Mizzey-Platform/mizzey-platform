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
} )( window.wp );
