/**
 * The offer banner in the editor (spec 066, slice 5: HOME-07, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the banner with
 * the storefront's own classes and stylesheet; its tag, heading, line and button words are typed in place. The
 * sidebar holds the image, its description and the button's link. The layout is fixed in style.css (G11).
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const lib = window.mizzeyLibrary;

	const plain = { allowedFormats: [], disableLineBreaks: true, withoutInteractiveFormatting: true };

	function Edit( { attributes, setAttributes } ) {
		const media = useSelect( ( select ) => ( attributes.imageId ? select( 'core' ).getMedia( attributes.imageId, { context: 'view' } ) : null ), [ attributes.imageId ] );
		const blockProps = useBlockProps( { className: 'mizzey-section mizzey-offer alignfull' + ( attributes.imageId ? '' : ' mizzey-offer--no-image' ) } );
		const field = ( key, tag, className, label, placeholder, more ) => el( RichText, Object.assign( {
			tagName: tag,
			className,
			'aria-label': label,
			placeholder,
			value: attributes[ key ] || '',
			onChange: ( value ) => setAttributes( { [ key ]: value } ),
		}, more || plain ) );

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Banner', 'mizzey-site' ), initialOpen: true },
					el( lib.ImageChooser, {
						label: __( 'Choose the banner image', 'mizzey-site' ),
						imageId: attributes.imageId,
						onChange: ( id ) => setAttributes( { imageId: id } ),
						help: __( 'A wide image; its calm side sits under the words.', 'mizzey-site' ),
					} ),
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Banner image description', 'mizzey-site' ),
						help: __( 'For people who cannot see the image. Leave empty when it only decorates.', 'mizzey-site' ),
						value: attributes.imageAlt || '',
						onChange: ( value ) => setAttributes( { imageAlt: value } ),
					} ),
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						type: 'url',
						label: __( 'Banner button link', 'mizzey-site' ),
						value: attributes.ctaUrl || '',
						onChange: ( value ) => setAttributes( { ctaUrl: value } ),
					} )
				)
			),
			el(
				'section',
				blockProps,
				el(
					'div',
					{ className: 'mizzey-offer__card' },
					media && media.source_url ? el( 'div', { className: 'mizzey-offer__media' }, el( 'img', { className: 'mizzey-offer__image', src: media.source_url, alt: '' } ) ) : null,
					el(
						'div',
						{ className: 'mizzey-offer__body' },
						field( 'tag', 'span', 'mizzey-badge mizzey-badge--tint mizzey-offer__tag', __( 'Banner tag', 'mizzey-site' ), __( 'New', 'mizzey-site' ) ),
						field( 'heading', 'h2', 'mizzey-offer__heading', __( 'Banner headline', 'mizzey-site' ), __( 'Fresh on the beauty shelf', 'mizzey-site' ) ),
						field( 'text', 'p', 'mizzey-offer__text', __( 'Banner text', 'mizzey-site' ), __( 'This month\'s arrivals. All original stock.', 'mizzey-site' ), { allowedFormats: [ 'core/bold' ], withoutInteractiveFormatting: true } ),
						field( 'ctaLabel', 'span', 'mizzey-button mizzey-button--primary mizzey-offer__button', __( 'Banner button', 'mizzey-site' ), __( 'See what\'s new', 'mizzey-site' ) )
					)
				)
			)
		);
	}

	registerBlockType( 'mizzey/offer-banner', { edit: Edit, save: () => null } );
} )( window.wp );
