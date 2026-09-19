/**
 * Registers the "Code (Slim Highlight)" block: a language picker plus a
 * plain-text input (WordPress's own PlainText component — the same one
 * core's Code block uses, so paste/undo/keyboard behavior matches what
 * authors already expect) and a read-only live preview highlighted with
 * Prism.js. No build step — plain wp.element.createElement calls against
 * the global wp.* objects WordPress itself enqueues in the block editor.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PlainText = wp.blockEditor.PlainText;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;

	var LANGUAGES = ( window.schBlockData && window.schBlockData.languages ) || {};
	var OTHER_VALUE = '__other__';

	/**
	 * @return {{value:string,label:string}[]}
	 */
	function languageChoices() {
		var choices = [];
		Object.keys( LANGUAGES ).forEach( function ( id ) {
			choices.push( { value: id, label: LANGUAGES[ id ] } );
		} );
		choices.sort( function ( a, b ) {
			return a.label.localeCompare( b.label );
		} );
		choices.push( { value: OTHER_VALUE, label: __( 'Other…', 'slim-code-highlight' ) } );
		return choices;
	}

	/**
	 * The language picker, shared between the toolbar area and the
	 * Inspector sidebar. Shows a free-text field instead of/alongside the
	 * dropdown whenever the current language isn't one of the curated
	 * choices — covers both an explicit "Other…" pick and a value saved
	 * before (a custom Prism id typed in previously, or content authored
	 * outside this block and later edited).
	 *
	 * @param {Object}   props
	 * @param {string}   props.language
	 * @param {Function} props.onChange
	 */
	function LanguagePicker( props ) {
		var language = props.language;
		var onChange = props.onChange;
		var isKnown = Object.prototype.hasOwnProperty.call( LANGUAGES, language );

		return el( Fragment, {},
			el( SelectControl, {
				label: __( 'Language', 'slim-code-highlight' ),
				value: isKnown ? language : OTHER_VALUE,
				options: languageChoices(),
				onChange: function ( value ) {
					if ( OTHER_VALUE !== value ) {
						onChange( value );
					}
				},
			} ),
			( ! isKnown ) && el( TextControl, {
				label: __( 'Prism language id', 'slim-code-highlight' ),
				help: __( 'Any id from prismjs.com/#supported-languages, e.g. "kotlin" or "dockerfile".', 'slim-code-highlight' ),
				value: language,
				onChange: function ( value ) {
					onChange( value.replace( /[^a-z0-9+#-]/gi, '' ).toLowerCase() );
				},
			} )
		);
	}

	registerBlockType( 'slim-code-highlight/code', {
		title: __( 'Code (Slim Highlight)', 'slim-code-highlight' ),
		description: __( 'A code block with a language picker and a live Prism.js preview.', 'slim-code-highlight' ),
		icon: 'editor-code',
		category: 'text',
		attributes: {
			language: { type: 'string', default: 'none' },
			content: { type: 'string', source: 'html', selector: 'code', default: '' },
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'sch-block' } );
			var previewRef = useRef( null );

			var languageClass = 'language-' + ( attributes.language || 'none' );

			useEffect( function () {
				if ( ! previewRef.current || ! window.Prism ) {
					return;
				}
				// Prism's own theme CSS keys its background/base text
				// color off `pre[class*="language-"]` — the *outer* <pre>,
				// not just the <code> — so without this the preview
				// showed token colors (meant to sit on the theme's own
				// dark background) over a plain white box instead: pale,
				// low-contrast, and not what Settings > Code Highlight
				// actually renders on the front end.
				window.Prism.highlightElement( previewRef.current );
			}, [ attributes.content, attributes.language ] );

			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Language', 'slim-code-highlight' ) },
						el( LanguagePicker, {
							language: attributes.language,
							onChange: function ( value ) { setAttributes( { language: value } ); },
						} )
					)
				),
				el( 'div', blockProps,
					el( 'div', { className: 'sch-block-language-row' },
						el( LanguagePicker, {
							language: attributes.language,
							onChange: function ( value ) { setAttributes( { language: value } ); },
						} )
					),
					el( 'details', { className: 'sch-block-section', open: true },
						el( 'summary', {}, __( 'Code', 'slim-code-highlight' ) ),
						el( PlainText, {
							className: 'sch-block-input',
							value: attributes.content,
							onChange: function ( value ) { setAttributes( { content: value } ); },
							placeholder: __( 'Paste or type your code…', 'slim-code-highlight' ),
							'aria-label': __( 'Code', 'slim-code-highlight' ),
						} )
					),
					el( 'details', { className: 'sch-block-section', open: true },
						el( 'summary', {}, __( 'Preview', 'slim-code-highlight' ) ),
						el( 'pre', { className: 'sch-block-preview-pre ' + languageClass },
							el( 'code', { ref: previewRef, className: languageClass }, attributes.content )
						)
					)
				)
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save();
			return el( 'pre', blockProps,
				el( 'code', { className: 'language-' + ( attributes.language || 'none' ) }, attributes.content )
			);
		},
	} );
} )( window.wp );
