/**
 * The footer's and the announcement strip's settings in the Site Editor (spec 066, slice 3). Both are rendered by the
 * server (inc/footer.php, inc/strip.php) and previewed here as they render.
 *
 * - The footer: its style (the two footers of C1-Header-Footer-Styles), its columns, each naming its navigation menu
 *   for each language, set wide (two columns of links) or under the column before it, the Customer care menu for each
 *   language, the legal line for each language, and the logo file for the Indigo footer.
 * - The strip: fixed or slider, and up to three messages for each language, each with an optional link.
 *
 * A plain script on WordPress's own globals: the theme has no build step.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, Button, Flex } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __, sprintf } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	const LANGUAGES = ( window.mizzeySiteParts && window.mizzeySiteParts.languages ) || [ { code: 'en', name: 'English' } ];
	const MOST = 3;

	function useMenus() {
		return useSelect( ( select ) => select( 'core' ).getEntityRecords( 'postType', 'wp_navigation', { per_page: -1, status: 'publish' } ) || [], [] );
	}

	function menuOptions( menus ) {
		return [ { value: '0', label: __( 'None', 'mizzey-site' ) } ].concat(
			menus.map( ( m ) => ( { value: String( m.id ), label: ( m.title && ( m.title.rendered || m.title.raw ) ) || String( m.id ) } ) )
		);
	}

	/** One menu chooser per language. */
	function menuPerLanguage( value, menus, onChange ) {
		const current = value || {};
		return LANGUAGES.map( ( language ) =>
			el( SelectControl, {
				key: language.code,
				/* translators: %s: a language's name. */
				label: sprintf( __( 'Menu in %s', 'mizzey-site' ), language.name ),
				value: String( current[ language.code ] || 0 ),
				options: menuOptions( menus ),
				onChange: ( id ) => onChange( Object.assign( {}, current, { [ language.code ]: Number( id ) || 0 } ) ),
			} )
		);
	}

	function moved( list, from, to ) {
		const out = list.slice();
		const [ item ] = out.splice( from, 1 );
		out.splice( to, 0, item );
		return out;
	}

	registerBlockType( 'mizzey/site-footer', {
		edit( { attributes, setAttributes } ) {
			const menus = useMenus();
			const props = useBlockProps();
			const columns = Array.isArray( attributes.columns ) ? attributes.columns : [];
			const setColumn = ( i, change ) => setAttributes( { columns: columns.map( ( c, j ) => ( j === i ? Object.assign( {}, c, change ) : c ) ) } );
			const legal = attributes.legal || {};

			const columnPanels = columns.map( ( column, i ) =>
				el(
					PanelBody,
					/* translators: %d: the column's number. */
					{ key: i, title: sprintf( __( 'Column %d', 'mizzey-site' ), i + 1 ), initialOpen: false },
					menuPerLanguage( column.menus, menus, ( value ) => setColumn( i, { menus: value } ) ),
					el( ToggleControl, {
						label: __( 'Wide, links in two columns', 'mizzey-site' ),
						checked: !! column.wide,
						onChange: ( wide ) => setColumn( i, { wide } ),
					} ),
					i > 0 &&
						el( ToggleControl, {
							label: __( 'Under the column before it', 'mizzey-site' ),
							checked: !! column.under,
							onChange: ( under ) => setColumn( i, { under } ),
						} ),
					el(
						Flex,
						{ justify: 'flex-start' },
						el( Button, { variant: 'secondary', disabled: i === 0, onClick: () => setAttributes( { columns: moved( columns, i, i - 1 ) } ) }, __( 'Move up', 'mizzey-site' ) ),
						el( Button, { variant: 'secondary', disabled: i === columns.length - 1, onClick: () => setAttributes( { columns: moved( columns, i, i + 1 ) } ) }, __( 'Move down', 'mizzey-site' ) ),
						el( Button, { variant: 'link', isDestructive: true, onClick: () => setAttributes( { columns: columns.filter( ( c, j ) => j !== i ) } ) }, __( 'Remove the column', 'mizzey-site' ) )
					)
				)
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Footer', 'mizzey-site' ) },
						el( SelectControl, {
							label: __( 'Style', 'mizzey-site' ),
							value: attributes.style,
							options: [
								{ value: 'indigo', label: __( 'Royal Indigo', 'mizzey-site' ) },
								{ value: 'white', label: __( 'White', 'mizzey-site' ) },
							],
							onChange: ( style ) => setAttributes( { style } ),
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
											el( Button, { variant: 'secondary', onClick: open }, attributes.darkLogo ? __( 'Replace the logo for the Indigo footer', 'mizzey-site' ) : __( 'Choose the logo for the Indigo footer', 'mizzey-site' ) ),
											attributes.darkLogo ? el( Button, { variant: 'link', isDestructive: true, onClick: () => setAttributes( { darkLogo: 0 } ) }, __( 'Remove', 'mizzey-site' ) ) : null
										),
								} )
							)
					),
					el(
						PanelBody,
						{ title: __( 'Columns', 'mizzey-site' ) },
						el( 'p', null, __( 'Each column shows a navigation menu, with its title as the heading. Choose the menu for each language. A link to #cookie-preferences opens the cookie preferences.', 'mizzey-site' ) ),
						el( Button, { variant: 'secondary', onClick: () => setAttributes( { columns: columns.concat( [ { menus: {} } ] ) } ) }, __( 'Add a column', 'mizzey-site' ) )
					),
					columnPanels,
					el(
						PanelBody,
						{ title: __( 'Customer care', 'mizzey-site' ), initialOpen: false },
						el( 'p', null, __( 'The WhatsApp link comes first, from WooCommerce > Settings > General > Store contact. Then this menu: the phone, the email, the address.', 'mizzey-site' ) ),
						menuPerLanguage( attributes.care, menus, ( care ) => setAttributes( { care } ) )
					),
					el(
						PanelBody,
						{ title: __( 'Legal line', 'mizzey-site' ), initialOpen: false },
						LANGUAGES.map( ( language ) =>
							el( TextControl, {
								key: language.code,
								/* translators: %s: a language's name. */
								label: sprintf( __( 'Legal line in %s', 'mizzey-site' ), language.name ),
								value: legal[ language.code ] || '',
								onChange: ( text ) => setAttributes( { legal: Object.assign( {}, legal, { [ language.code ]: text } ) } ),
							} )
						)
					)
				),
				el( 'div', props, el( ServerSideRender, { block: 'mizzey/site-footer', attributes } ) )
			);
		},
		save: () => null,
	} );

	registerBlockType( 'mizzey/announcement-strip', {
		edit( { attributes, setAttributes } ) {
			const props = useBlockProps();
			const all = attributes.messages || {};
			const listFor = ( code ) => ( Array.isArray( all[ code ] ) ? all[ code ] : [] );
			const setList = ( code, list ) => setAttributes( { messages: Object.assign( {}, all, { [ code ]: list } ) } );
			const setMessage = ( code, i, change ) => setList( code, listFor( code ).map( ( m, j ) => ( j === i ? Object.assign( {}, m, change ) : m ) ) );

			const languagePanels = LANGUAGES.map( ( language ) => {
				const list = listFor( language.code );
				return el(
					PanelBody,
					/* translators: %s: a language's name. */
					{ key: language.code, title: sprintf( __( 'Messages in %s', 'mizzey-site' ), language.name ), initialOpen: false },
					list.map( ( message, i ) =>
						el(
							'div',
							{ key: i, className: 'mizzey-strip-editor__message' },
							el( TextControl, {
								/* translators: %d: the message's number. */
								label: sprintf( __( 'Message %d', 'mizzey-site' ), i + 1 ),
								value: message.text || '',
								onChange: ( text ) => setMessage( language.code, i, { text } ),
							} ),
							el( TextControl, { label: __( 'Link words', 'mizzey-site' ), value: message.label || '', onChange: ( label ) => setMessage( language.code, i, { label } ) } ),
							el( TextControl, { label: __( 'Link address', 'mizzey-site' ), type: 'url', value: message.url || '', onChange: ( url ) => setMessage( language.code, i, { url } ) } ),
							el( Button, { variant: 'link', isDestructive: true, onClick: () => setList( language.code, list.filter( ( m, j ) => j !== i ) ) }, __( 'Remove the message', 'mizzey-site' ) )
						)
					),
					list.length < MOST && el( Button, { variant: 'secondary', onClick: () => setList( language.code, list.concat( [ { text: '', label: '', url: '' } ] ) ) }, __( 'Add a message', 'mizzey-site' ) )
				);
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Announcement strip', 'mizzey-site' ) },
						el( SelectControl, {
							label: __( 'Variant', 'mizzey-site' ),
							help: __( 'Fixed shows the first message. The slider shows up to three in turn, six seconds each.', 'mizzey-site' ),
							value: attributes.variant,
							options: [
								{ value: 'fixed', label: __( 'Fixed', 'mizzey-site' ) },
								{ value: 'slider', label: __( 'Slider', 'mizzey-site' ) },
							],
							onChange: ( variant ) => setAttributes( { variant } ),
						} ),
						el( 'p', null, __( 'With no message in a language, the strip is not shown in that language.', 'mizzey-site' ) )
					),
					languagePanels
				),
				el( 'div', props, el( ServerSideRender, { block: 'mizzey/announcement-strip', attributes } ) )
			);
		},
		save: () => null,
	} );
} )( window.wp );
