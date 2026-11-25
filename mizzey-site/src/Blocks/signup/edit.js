/**
 * The sign-up prompt in the editor (spec 066, slice 5: HOME-09, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows the prompt with
 * the storefront's own classes and stylesheet: the heading, the line and the button's words are typed in place; the
 * email field and the optional box are shown as the visitor sees them, and are not edited here (their words are
 * translated with the site). The layout is fixed in style.css (G11).
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText } = wp.blockEditor;
	const { createElement: el } = wp.element;
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
				{ className: 'mizzey-signup__form' },
				el( 'span', { className: 'mizzey-signup__label' }, __( 'Email address', 'mizzey-site' ) ),
				el( 'span', { className: 'mizzey-signup__box' }, el( 'input', { className: 'mizzey-signup__input', type: 'email', dir: 'ltr', placeholder: 'name@example.com', disabled: true, 'aria-hidden': 'true', tabIndex: -1 } ) ),
				el(
					'span',
					{ className: 'mizzey-check mizzey-signup__consent', 'aria-hidden': 'true' },
					el( 'input', { className: 'mizzey-check__box', type: 'checkbox', disabled: true, tabIndex: -1 } ),
					el( 'span', { className: 'mizzey-signup__consent-text' }, __( 'Email me news and offers. Optional, and separate from your account.', 'mizzey-site' ) )
				),
				field( 'ctaLabel', 'span', 'mizzey-button mizzey-button--primary mizzey-signup__button', __( 'Sign-up button', 'mizzey-site' ), __( 'Sign up', 'mizzey-site' ) )
			)
		);
	}

	registerBlockType( 'mizzey/signup', { edit: Edit, save: () => null } );
} )( window.wp );
