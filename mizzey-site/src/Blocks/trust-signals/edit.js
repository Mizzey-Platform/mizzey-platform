/**
 * The trust signals section in the editor (spec 066, slice 1: SSC-01, the proof of the section library).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the section
 * with the storefront's own classes and stylesheet, so it looks as it will on the site; each item's title and line
 * are typed in place. The sidebar holds each item's mark (one of the three the boards draw) and the line's link, and
 * the section's name for screen readers. The layout is not editable: it is fixed in style.css (G11). The storefront
 * is rendered by render.php.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl } = wp.components;
	const { createElement: el, Fragment, RawHTML } = wp.element;
	const { __, sprintf } = wp.i18n;

	const COUNT = 3;
	const MARKS = [ 'shield', 'van', 'returns' ];
	const marks = ( window.mizzeySections && window.mizzeySections.trustMarks ) || {};

	const markOptions = () => [
		{ value: 'shield', label: __( 'Authenticity shield', 'mizzey-site' ) },
		{ value: 'van', label: __( 'Delivery van', 'mizzey-site' ) },
		{ value: 'returns', label: __( 'Returns arrow', 'mizzey-site' ) },
	];

	/* Hints in the empty fields, the board's words: the editor types the store's own, in each language. */
	const hints = () => [
		{ title: __( 'Authentic products, guaranteed', 'mizzey-site' ), line: __( 'Authenticity guarantee', 'mizzey-site' ) },
		{ title: __( 'Fast delivery', 'mizzey-site' ), line: __( 'Free delivery on orders over EGP 1,000', 'mizzey-site' ) },
		{ title: __( 'Easy returns', 'mizzey-site' ), line: __( 'Return and refund policy', 'mizzey-site' ) },
	];

	/* Always the three items the boards draw, whatever an older save holds. */
	function itemsOf( attributes ) {
		const stored = Array.isArray( attributes.items ) ? attributes.items : [];
		const out = [];
		for ( let i = 0; i < COUNT; i++ ) {
			const item = stored[ i ] || {};
			out.push( {
				icon: MARKS.indexOf( item.icon ) !== -1 ? item.icon : MARKS[ i ],
				title: item.title || '',
				line: item.line || '',
				url: item.url || '',
			} );
		}
		return out;
	}

	function Edit( { attributes, setAttributes } ) {
		const items = itemsOf( attributes );
		const hint = hints();
		const setItem = ( index, patch ) => setAttributes( {
			items: items.map( ( item, i ) => ( i === index ? Object.assign( {}, item, patch ) : item ) ),
		} );
		const blockProps = useBlockProps( {
			className: 'mizzey-section mizzey-trust alignfull',
			'aria-label': attributes.label || undefined,
		} );

		const list = el(
			'ul',
			{ className: 'mizzey-trust__list' },
			items.map( ( item, i ) => el(
				'li',
				{ className: 'mizzey-trust__item', key: i },
				el( RawHTML, { className: 'mizzey-trust__mark-host' }, marks[ item.icon ] || '' ),
				el(
					'span',
					{ className: 'mizzey-trust__text' },
					el( RichText, {
						tagName: 'span',
						className: 'mizzey-trust__title',
						'aria-label': sprintf( __( 'Item %d title', 'mizzey-site' ), i + 1 ),
						placeholder: hint[ i ].title,
						value: item.title,
						allowedFormats: [],
						disableLineBreaks: true,
						withoutInteractiveFormatting: true,
						onChange: ( value ) => setItem( i, { title: value } ),
					} ),
					el( RichText, {
						tagName: 'span',
						className: 'mizzey-trust__line',
						'aria-label': sprintf( __( 'Item %d line', 'mizzey-site' ), i + 1 ),
						placeholder: hint[ i ].line,
						value: item.line,
						allowedFormats: [],
						disableLineBreaks: true,
						withoutInteractiveFormatting: true,
						onChange: ( value ) => setItem( i, { line: value } ),
					} )
				)
			) )
		);

		const sidebar = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Section', 'mizzey-site' ), initialOpen: true },
				el( TextControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Name read by screen readers', 'mizzey-site' ),
					help: __( 'Optional, in this page\'s language. For example: Why shop with us.', 'mizzey-site' ),
					value: attributes.label || '',
					onChange: ( value ) => setAttributes( { label: value } ),
				} )
			),
			items.map( ( item, i ) => el(
				PanelBody,
				{ key: i, title: sprintf( __( 'Item %d', 'mizzey-site' ), i + 1 ), initialOpen: i === 0 },
				el( SelectControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: sprintf( __( 'Item %d mark', 'mizzey-site' ), i + 1 ),
					value: item.icon,
					options: markOptions(),
					onChange: ( value ) => setItem( i, { icon: value } ),
				} ),
				el( TextControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					type: 'url',
					label: sprintf( __( 'Item %d link', 'mizzey-site' ), i + 1 ),
					help: __( 'Optional. The line links here; without a link it is plain text.', 'mizzey-site' ),
					value: item.url,
					onChange: ( value ) => setItem( i, { url: value } ),
				} )
			) )
		);

		return el( Fragment, null, sidebar, el( 'section', blockProps, list ) );
	}

	registerBlockType( 'mizzey/trust-signals', {
		edit: Edit,
		save: () => null,
	} );
} )( window.wp );
