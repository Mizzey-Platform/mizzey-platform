/**
 * The sign-up prompt in the editor (spec 066, slice 5: HOME-09, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the prompt with
 * the storefront's own classes and stylesheet; its heading, line and button words are typed in place, and the
 * sidebar holds the button's link (the account page's sign-up when empty). The layout is fixed in style.css (G11).
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const plain = { allowedFormats: [], disableLineBreaks: true, withoutInteractiveFormatting: true };

	function Edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'mizzey-section mizzey-signup alignfull' } );
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
					{ title: __( 'Sign-up prompt', 'mizzey-site' ), initialOpen: true },
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						type: 'url',
						label: __( 'Sign-up button link', 'mizzey-site' ),
						help: __( 'Optional. Without one, the button opens the account page, where a visitor signs up.', 'mizzey-site' ),
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
					{ className: 'mizzey-signup__words' },
					field( 'heading', 'h2', 'mizzey-signup__heading', __( 'Sign-up headline', 'mizzey-site' ), __( 'Stay in touch', 'mizzey-site' ) ),
					field( 'text', 'p', 'mizzey-signup__text', __( 'Sign-up text', 'mizzey-site' ), __( 'One short email when something worth knowing lands.', 'mizzey-site' ), { allowedFormats: [ 'core/bold' ], withoutInteractiveFormatting: true } )
				),
				el(
					'div',
					{ className: 'mizzey-signup__action' },
					field( 'ctaLabel', 'span', 'mizzey-button mizzey-button--primary mizzey-signup__button', __( 'Sign-up button', 'mizzey-site' ), __( 'Sign up', 'mizzey-site' ) )
				)
			)
		);
	}

	registerBlockType( 'mizzey/signup', { edit: Edit, save: () => null } );
} )( window.wp );
