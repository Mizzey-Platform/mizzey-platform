/**
 * The site header's settings in the Site Editor (spec 066, slice 2): its style (the three headers of
 * C1-Header-Footer-Styles), the navigation menu of its category row, where Help leads, and the logo file for the
 * Indigo header. The header itself is rendered by the server (inc/header.php) and previewed here as it renders.
 * A plain script on WordPress's own globals: the theme has no build step.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, Button } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'mizzey/site-header', {
		edit( { attributes, setAttributes } ) {
			const menus = useSelect( ( select ) => select( 'core' ).getEntityRecords( 'postType', 'wp_navigation', { per_page: -1, status: 'publish' } ) || [], [] );
			const props = useBlockProps();
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Header', 'mizzey-site' ) },
						el( SelectControl, {
							label: __( 'Style', 'mizzey-site' ),
							value: attributes.style,
							options: [
								{ value: 'white', label: __( 'White, logo at the start', 'mizzey-site' ) },
								{ value: 'indigo', label: __( 'Royal Indigo, logo at the start', 'mizzey-site' ) },
								{ value: 'centred', label: __( 'White, logo centred', 'mizzey-site' ) },
							],
							onChange: ( style ) => setAttributes( { style } ),
						} ),
						el( SelectControl, {
							label: __( 'Menu of the category row', 'mizzey-site' ),
							help: __( 'Each language uses its own translation of the menu. With none chosen, the menu titled "Mizzey header" is used.', 'mizzey-site' ),
							value: String( attributes.menu || 0 ),
							options: [ { value: '0', label: __( 'The menu titled "Mizzey header"', 'mizzey-site' ) } ].concat(
								menus.map( ( m ) => ( { value: String( m.id ), label: ( m.title && m.title.rendered ) || String( m.id ) } ) )
							),
							onChange: ( menu ) => setAttributes( { menu: Number( menu ) || 0 } ),
						} ),
						el( TextControl, {
							label: __( 'Help link', 'mizzey-site' ),
							help: __( 'The address Help leads to. A page of this site is followed to its translation. Leave empty to hide Help.', 'mizzey-site' ),
							type: 'url',
							value: attributes.helpUrl,
							onChange: ( helpUrl ) => setAttributes( { helpUrl } ),
						} ),
						attributes.style === 'indigo' &&
							el(
								MediaUploadCheck,
								null,
								el( MediaUpload, {
									allowedTypes: [ 'image' ],
									value: attributes.darkLogo,
									onSelect: ( media ) => setAttributes( { darkLogo: media.id } ),
									render: ( { open } ) =>
										el(
											'div',
											null,
											el( Button, { variant: 'secondary', onClick: open }, attributes.darkLogo ? __( 'Replace the logo for the Indigo header', 'mizzey-site' ) : __( 'Choose the logo for the Indigo header', 'mizzey-site' ) ),
											attributes.darkLogo ? el( Button, { variant: 'link', isDestructive: true, onClick: () => setAttributes( { darkLogo: 0 } ) }, __( 'Use the site logo', 'mizzey-site' ) ) : null
										),
								} )
							)
					)
				),
				el( 'div', props, el( ServerSideRender, { block: 'mizzey/site-header', attributes } ) )
			);
		},
		save: () => null,
	} );
} )( window.wp );
