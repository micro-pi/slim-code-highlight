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
	var RawHTML = wp.element.RawHTML;
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

	/**
	 * The preview is a Prism theme's token colors drawn on whatever
	 * background the editor ends up giving the <pre>. A dark theme such as
	 * Okaidia has light text and its own dark background, but the editor
	 * (or the active theme's editor styles) can override that background
	 * with a pale one, leaving light text on light gray. These helpers
	 * measure the real contrast so the preview can step in only when the
	 * text would be hard to read.
	 */
	function parseColor( value ) {
		var m = /rgba?\(([^)]+)\)/.exec( value || '' );
		if ( ! m ) {
			return null;
		}
		var p = m[ 1 ].split( /[\s,/]+/ ).filter( Boolean ).map( Number );
		if ( p.length < 3 || p.some( isNaN ) ) {
			return null;
		}
		return { r: p[ 0 ], g: p[ 1 ], b: p[ 2 ], a: p.length > 3 ? p[ 3 ] : 1 };
	}

	function luminance( c ) {
		var ch = [ c.r, c.g, c.b ].map( function ( v ) {
			v = v / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * ch[ 0 ] + 0.7152 * ch[ 1 ] + 0.0722 * ch[ 2 ];
	}

	/** A translucent background is seen over the editor's white canvas. */
	function overWhite( c ) {
		return {
			r: Math.round( c.r * c.a + 255 * ( 1 - c.a ) ),
			g: Math.round( c.g * c.a + 255 * ( 1 - c.a ) ),
			b: Math.round( c.b * c.a + 255 * ( 1 - c.a ) ),
			a: 1,
		};
	}

	function contrast( a, b ) {
		var la = luminance( a );
		var lb = luminance( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	/**
	 * Leaves the theme's own background alone whenever the text is readable
	 * on it; otherwise picks a dark or light background to suit the text.
	 *
	 * @param {HTMLElement} pre  The preview <pre>.
	 * @param {HTMLElement} code The highlighted <code> inside it.
	 */
	function ensureReadable( pre, code ) {
		var view = pre.ownerDocument.defaultView || window;
		pre.style.backgroundColor = '';
		var fg = parseColor( view.getComputedStyle( code ).color );
		var bg = parseColor( view.getComputedStyle( pre ).backgroundColor );
		if ( ! fg || ! bg ) {
			return;
		}
		bg = overWhite( bg );
		if ( contrast( fg, bg ) < 3 ) {
			pre.style.backgroundColor = luminance( fg ) > 0.5 ? '#272822' : '#f5f2f0';
		}
	}

	/**
	 * Escapes the three characters that matter in element text. Done by hand
	 * because WordPress's own serializer leaves an ampersand alone when it
	 * already looks like an entity, so code that contains the text "&amp;" or
	 * "&lt;" (HTML and escaping tutorials) would not survive a save unchanged.
	 *
	 * @param {string} text
	 * @return {string}
	 */
	function escapeText( text ) {
		return String( text ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	registerBlockType( 'slim-code-highlight/code', {
		title: __( 'Code (Slim Highlight)', 'slim-code-highlight' ),
		description: __( 'A code block with a language picker and a live Prism.js preview.', 'slim-code-highlight' ),
		icon: 'editor-code',
		category: 'text',
		attributes: {
			language: { type: 'string', default: 'none' },
			// 'text', not 'html': the code is plain text. With 'html' the editor
			// handed back the escaped markup ("&lt;"), showed it literally, and
			// save() then escaped the ampersand again ("&amp;lt;") on every save.
			content: { type: 'string', source: 'text', selector: 'code', default: '' },
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'sch-block' } );
			var previewRef = useRef( null );
			var preRef = useRef( null );

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
				if ( preRef.current ) {
					ensureReadable( preRef.current, previewRef.current );
				}
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
						el( 'pre', { ref: preRef, className: 'sch-block-preview-pre ' + languageClass },
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
				el( 'code', { className: 'language-' + ( attributes.language || 'none' ) }, el( RawHTML, {}, escapeText( attributes.content ) ) )
			);
		},
	} );
} )( window.wp );
