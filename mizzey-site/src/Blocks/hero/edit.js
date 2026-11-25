/**
 * The hero in the editor (spec 066, slice 5: HOME-01, HOME-10).
 *
 * A plain script on WordPress's globals: there is no build step in this repository. The canvas shows each slide with
 * the storefront's own classes and stylesheet, one under the other so every slide can be edited; each slide's
 * headline, line and button words are typed in place. The sidebar holds the style (a single banner or a slider), the
 * seconds a slide stays (six at least), the logo, and for each slide its image, the image's description, its ground
 * (light or Indigo) and the button's link; slides are added, moved and removed there. The storefront is rendered by
 * MizzeySite\Blocks\Hero, and the slider's motion by view.js.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, Button, __experimentalNumberControl: NumberControl } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __, sprintf } = wp.i18n;
	const lib = window.mizzeyLibrary;
	const data = window.mizzeySections || {};
	const MIN = data.minSeconds || 6;
	const MAX = data.maxSlides || 8;
	const EMPTY = { imageId: 0, imageAlt: '', tone: 'light', title: '', line: '', ctaLabel: '', ctaUrl: '' };

	const slidesOf = ( attributes ) => ( Array.isArray( attributes.slides ) && attributes.slides.length ? attributes.slides : [ EMPTY ] ).map( ( s ) => Object.assign( {}, EMPTY, s ) );

	function SlideMedia( { imageId } ) {
		const media = useSelect( ( select ) => ( imageId ? select( 'core' ).getMedia( imageId, { context: 'view' } ) : null ), [ imageId ] );
		return el( 'div', { className: 'mizzey-hero__media' }, media && media.source_url ? el( 'img', { className: 'mizzey-hero__image', src: media.source_url, alt: '' } ) : null );
	}

	function Edit( { attributes, setAttributes } ) {
		const slides = slidesOf( attributes );
		const slider = attributes.variant !== 'single';
		const shown = slider ? slides : slides.slice( 0, 1 );
		const setSlide = ( index, patch ) => setAttributes( { slides: slides.map( ( s, i ) => ( i === index ? Object.assign( {}, s, patch ) : s ) ) } );
		const move = ( index, step ) => {
			const next = slides.slice();
			const [ item ] = next.splice( index, 1 );
			next.splice( index + step, 0, item );
			setAttributes( { slides: next } );
		};
		const blockProps = useBlockProps( { className: 'mizzey-section mizzey-hero mizzey-hero--editing alignfull' } );
		const text = ( index, key, tag, className, label, placeholder, more ) => el( RichText, Object.assign( {
			tagName: tag,
			className,
			'aria-label': label,
			placeholder,
			value: slides[ index ][ key ] || '',
			onChange: ( value ) => setSlide( index, { [ key ]: value } ),
		}, more ) );

		const canvas = el(
			'section',
			blockProps,
			shown.map( ( slide, i ) => el(
				'div',
				{ key: i, className: `mizzey-hero__slide mizzey-hero__slide--${ slide.tone === 'dark' ? 'dark' : 'light' } is-active` },
				el( SlideMedia, { imageId: slide.imageId } ),
				el(
					'div',
					{ className: 'mizzey-hero__panel' },
					text( i, 'title', 'h2', 'mizzey-hero__title', sprintf( __( 'Slide %d headline', 'mizzey-site' ), i + 1 ), __( 'The real thing, delivered', 'mizzey-site' ), { allowedFormats: [], withoutInteractiveFormatting: true } ),
					text( i, 'line', 'p', 'mizzey-hero__line', sprintf( __( 'Slide %d line', 'mizzey-site' ), i + 1 ), __( 'Authentic products, guaranteed', 'mizzey-site' ), { allowedFormats: [ 'core/bold' ], disableLineBreaks: true, withoutInteractiveFormatting: true } ),
					text( i, 'ctaLabel', 'span', 'mizzey-button mizzey-button--primary mizzey-hero__button' + ( slide.tone === 'dark' ? ' mizzey-hero__button--on-dark' : '' ), sprintf( __( 'Slide %d button', 'mizzey-site' ), i + 1 ), __( 'Authenticity guarantee', 'mizzey-site' ), { allowedFormats: [], disableLineBreaks: true, withoutInteractiveFormatting: true } )
				)
			) )
		);

		const slidePanels = shown.map( ( slide, i ) => el(
			PanelBody,
			{ key: i, title: sprintf( __( 'Slide %d', 'mizzey-site' ), i + 1 ), initialOpen: true },
			el( lib.ImageChooser, {
				label: sprintf( __( 'Choose slide %d image', 'mizzey-site' ), i + 1 ),
				imageId: slide.imageId,
				onChange: ( id ) => setSlide( i, { imageId: id } ),
				help: __( 'A wide image whose calm side sits under the words. The first slide\'s image loads first.', 'mizzey-site' ),
			} ),
			el( TextControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: sprintf( __( 'Slide %d image description', 'mizzey-site' ), i + 1 ),
				help: __( 'For people who cannot see the image. Leave empty when it only decorates.', 'mizzey-site' ),
				value: slide.imageAlt,
				onChange: ( value ) => setSlide( i, { imageAlt: value } ),
			} ),
			el( SelectControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: sprintf( __( 'Slide %d ground', 'mizzey-site' ), i + 1 ),
				help: __( 'Light for a pale image, with dark words; Indigo for a deep image, with white words.', 'mizzey-site' ),
				value: slide.tone,
				options: [
					{ value: 'light', label: __( 'Light', 'mizzey-site' ) },
					{ value: 'dark', label: __( 'Indigo', 'mizzey-site' ) },
				],
				onChange: ( value ) => setSlide( i, { tone: value } ),
			} ),
			el( TextControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				type: 'url',
				label: sprintf( __( 'Slide %d button link', 'mizzey-site' ), i + 1 ),
				value: slide.ctaUrl,
				onChange: ( value ) => setSlide( i, { ctaUrl: value } ),
			} ),
			slider ? el(
				'div',
				{ className: 'mizzey-library-row' },
				i > 0 ? el( Button, { variant: 'secondary', onClick: () => move( i, -1 ) }, sprintf( __( 'Move slide %d earlier', 'mizzey-site' ), i + 1 ) ) : null,
				slides.length > 1 ? el( Button, { variant: 'link', isDestructive: true, onClick: () => setAttributes( { slides: slides.filter( ( s, n ) => n !== i ) } ) }, sprintf( __( 'Remove slide %d', 'mizzey-site' ), i + 1 ) ) : null
			) : null
		) );

		const sidebar = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Hero', 'mizzey-site' ), initialOpen: true },
				el( SelectControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Hero style', 'mizzey-site' ),
					value: slider ? 'slider' : 'single',
					options: [
						{ value: 'single', label: __( 'Single banner', 'mizzey-site' ) },
						{ value: 'slider', label: __( 'Slider', 'mizzey-site' ) },
					],
					onChange: ( value ) => setAttributes( { variant: value } ),
				} ),
				slider ? el( NumberControl, {
					__next40pxDefaultSize: true,
					label: __( 'Seconds per slide', 'mizzey-site' ),
					help: sprintf( __( 'At least %d. The slider pauses while a visitor touches, hovers or focuses it.', 'mizzey-site' ), MIN ),
					min: MIN,
					max: 20,
					value: Math.max( MIN, attributes.interval || MIN ),
					onChange: ( value ) => setAttributes( { interval: Math.max( MIN, parseInt( value, 10 ) || MIN ) } ),
				} ) : null,
				el( TextControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Name read by screen readers', 'mizzey-site' ),
					help: __( 'Optional, in this page\'s language. For example: Offers.', 'mizzey-site' ),
					value: attributes.label || '',
					onChange: ( value ) => setAttributes( { label: value } ),
				} ),
				el( ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Show the logo on desktop', 'mizzey-site' ),
					checked: attributes.showLogo !== false,
					onChange: ( value ) => setAttributes( { showLogo: value } ),
				} ),
				attributes.showLogo !== false ? el( lib.ImageChooser, {
					label: __( 'Choose the logo for Indigo slides', 'mizzey-site' ),
					imageId: attributes.darkLogo,
					onChange: ( id ) => setAttributes( { darkLogo: id } ),
					help: __( 'The logo file made for Indigo grounds. Light slides show the site logo.', 'mizzey-site' ),
				} ) : null
			),
			slidePanels,
			slider && slides.length < MAX ? el(
				PanelBody,
				{ title: __( 'More slides', 'mizzey-site' ), initialOpen: true },
				el( Button, { variant: 'secondary', onClick: () => setAttributes( { slides: slides.concat( [ Object.assign( {}, EMPTY ) ] ) } ) }, __( 'Add a slide', 'mizzey-site' ) )
			) : null
		);

		return el( Fragment, null, sidebar, canvas );
	}

	registerBlockType( 'mizzey/hero', { edit: Edit, save: () => null } );
} )( window.wp );
