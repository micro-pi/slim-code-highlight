<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites <pre> code blocks — including the old lang:xxx decode:true
 * markup left over from a previous, now-uninstalled highlighter plugin —
 * into Prism.js-ready markup, and loads Prism (core + autoloader, from
 * CDN) only on pages that actually need it.
 */
class SCH_Frontend {

	/**
	 * Maps the site's old lang:xxx tokens (and a few common aliases) to
	 * the Prism.js component id that actually highlights them. A token
	 * not listed here is passed straight through as a Prism id — most
	 * already match (php, python, javascript, sql, json, bash, ...).
	 */
	const LANG_MAP = array(
		'default'    => 'none',
		'c++'        => 'cpp',
		'xhtml'      => 'markup',
		'html'       => 'markup',
		'xml'        => 'markup',
		'js'         => 'javascript',
		'shell'      => 'bash',
		'sh'         => 'bash',
	);

	public function __construct() {
		// Priority 20: after wpautop/do_shortcode/core's own content
		// filters have finished, so this sees the final <pre> markup.
		add_filter( 'the_content', array( __CLASS__, 'transform' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * @return bool Whether the plugin is turned on for the current
	 *              queried post type, per Settings.
	 */
	private static function enabled_for_current_post_type() {
		$options   = sch_get_options();
		$post_type = get_post_type();

		if ( 'post' === $post_type ) {
			return $options['show_post'];
		}
		if ( 'page' === $post_type ) {
			return $options['show_page'];
		}
		return false;
	}

	/**
	 * @param string $content
	 * @return string
	 */
	public static function transform( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! self::enabled_for_current_post_type() ) {
			return $content;
		}
		if ( false === strpos( $content, '<pre' ) ) {
			return $content;
		}

		return preg_replace_callback( '#<pre\b([^>]*)>(.*?)</pre>#is', array( __CLASS__, 'transform_block' ), $content );
	}

	/**
	 * @param string[] $m Regex match: [0] full block, [1] <pre> attributes, [2] inner HTML.
	 * @return string
	 */
	private static function transform_block( $m ) {
		$attrs = $m[1];
		$inner = $m[2];
		$options = sch_get_options();

		// Already has a Prism-shaped <code class="language-xxx">
		// (either hand-written, or from a previous run of this filter on
		// cached HTML) — leave the inner markup alone, just make sure the
		// <pre> itself carries the matching classes for the plugins below.
		$already_prism = (bool) preg_match( '/<code\b[^>]*\blanguage-([a-z0-9+-]+)/i', $inner, $lm );

		$existing_class = '';
		if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $attrs, $cm ) ) {
			$existing_class = $cm[2];
		}

		$lang = null;
		if ( $already_prism ) {
			$lang = strtolower( $lm[1] );
		} elseif ( preg_match( '/\blang:([a-z0-9_+-]+)/i', $existing_class, $lm2 ) ) {
			$token = strtolower( $lm2[1] );
			$lang  = isset( self::LANG_MAP[ $token ] ) ? self::LANG_MAP[ $token ] : $token;
		} elseif ( preg_match( '/\bconsole\b/i', $existing_class ) ) {
			$lang = 'none';
		} elseif ( '' === trim( $existing_class ) && $options['plain_pre'] ) {
			$lang = 'none';
		}

		// Not a block this plugin recognizes as code — leave it exactly
		// as found (e.g. genuinely-plain preformatted text with no class,
		// while "Also apply to plain <pre> blocks" is off).
		if ( null === $lang ) {
			return $m[0];
		}

		// Rebuild the class list: drop the old lang:/decode:true/console
		// tokens (meaningless to Prism), keep everything else the block
		// already had, add language-{lang} and, if enabled, line-numbers.
		$keep = array_filter(
			preg_split( '/\s+/', $existing_class ),
			function ( $t ) {
				return '' !== $t
					&& 0 !== stripos( $t, 'lang:' )
					&& 'decode:true' !== strtolower( $t )
					&& 'console' !== strtolower( $t )
					&& 0 !== stripos( $t, 'language-' );
			}
		);
		$keep[] = 'language-' . $lang;
		if ( $options['line_numbers'] ) {
			$keep[] = 'line-numbers';
		}
		$new_class = implode( ' ', array_unique( $keep ) );

		if ( '' !== $existing_class ) {
			$new_attrs = preg_replace( '/\bclass\s*=\s*(["\'])(.*?)\1/i', 'class="' . esc_attr( $new_class ) . '"', $attrs, 1 );
		} else {
			$new_attrs = rtrim( $attrs ) . ' class="' . esc_attr( $new_class ) . '"';
		}

		if ( $already_prism ) {
			return '<pre' . $new_attrs . '>' . $inner . '</pre>';
		}

		return '<pre' . $new_attrs . '><code class="language-' . esc_attr( $lang ) . '">' . $inner . '</code></pre>';
	}

	/**
	 * Prism is only useful on a singular post/page whose content actually
	 * has a <pre> block, so it's loaded only there.
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() || ! self::enabled_for_current_post_type() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || false === strpos( $post->post_content, '<pre' ) ) {
			return;
		}

		$options = sch_get_options();
		$theme   = SCH_Settings::sanitize_theme( $options['theme'] );
		// The "Default" theme's file is themes/prism.min.css — every
		// other theme follows themes/prism-{name}.min.css.
		$theme_file = ( 'prism' === $theme ) ? 'prism.min.css' : "prism-{$theme}.min.css";

		wp_enqueue_style(
			'sch-prism-theme',
			"https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/themes/{$theme_file}",
			array(),
			SCH_PRISM_VERSION
		);

		wp_enqueue_script(
			'sch-prism',
			"https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/components/prism-core.min.js",
			array(),
			SCH_PRISM_VERSION,
			true
		);

		/*
		 * The autoloader plugin — not a hand-picked component list — is
		 * what actually resolves and fetches each language grammar,
		 * including its transitive dependencies (e.g. php needs
		 * markup-templating, cpp needs c, which both need clike): those
		 * dependency chains are Prism's own to track, and a manually
		 * concatenated bundle got that wrong in testing (a missing
		 * dependency threw partway through one combined script and
		 * silently broke every language on the page, not just the one
		 * missing its dependency). The autoloader loads each language as
		 * its own isolated <script>, so one bad/unknown id can't take
		 * down the rest.
		 */
		wp_enqueue_script(
			'sch-prism-autoloader',
			"https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/autoloader/prism-autoloader.min.js",
			array( 'sch-prism' ),
			SCH_PRISM_VERSION,
			true
		);
		wp_add_inline_script(
			'sch-prism-autoloader',
			"Prism.plugins.autoloader.languages_path = 'https://cdn.jsdelivr.net/npm/prismjs@" . esc_js( SCH_PRISM_VERSION ) . "/components/';",
			'after'
		);

		if ( $options['line_numbers'] ) {
			wp_enqueue_style( 'sch-prism-line-numbers', "https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/line-numbers/prism-line-numbers.min.css", array(), SCH_PRISM_VERSION );
			wp_enqueue_script( 'sch-prism-line-numbers', "https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/line-numbers/prism-line-numbers.min.js", array( 'sch-prism' ), SCH_PRISM_VERSION, true );
		}

		if ( $options['copy_button'] ) {
			wp_enqueue_style( 'sch-prism-toolbar', "https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/toolbar/prism-toolbar.min.css", array(), SCH_PRISM_VERSION );
			wp_enqueue_script( 'sch-prism-toolbar', "https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/toolbar/prism-toolbar.min.js", array( 'sch-prism' ), SCH_PRISM_VERSION, true );
			wp_enqueue_script( 'sch-prism-copy', "https://cdn.jsdelivr.net/npm/prismjs@" . SCH_PRISM_VERSION . "/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js", array( 'sch-prism-toolbar' ), SCH_PRISM_VERSION, true );
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_style( 'slim-code-highlight', SCH_URL . "assets/style{$suffix}.css", array( 'sch-prism-theme' ), SCH_VERSION );

		// 100 means "theme default" — no override needed, so nothing is
		// added to the page in that (default) case. Only the outer <pre>
		// is targeted, not also its <code> child: the theme's own CSS
		// sets font-size on both, and since <code> is nested inside
		// <pre>, setting the same percentage on both would compound
		// (e.g. 80% of 80% = 64%) instead of applying once. <code>
		// inherits the resolved size from <pre> on its own. Loads after
		// the theme (this handle depends on sch-prism-theme), so plain
		// cascade order is enough — no !important needed.
		if ( 100 !== $options['font_size'] ) {
			wp_add_inline_style(
				'slim-code-highlight',
				'pre[class*="language-"] { font-size: ' . (int) $options['font_size'] . '%; }'
			);
		}
	}
}
