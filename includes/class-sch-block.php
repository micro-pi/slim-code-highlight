<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A block-editor "Code" block: a language picker plus a live Prism.js
 * preview, so a post author sees highlighted code while writing instead
 * of only after publishing. Saves the exact same
 * <pre><code class="language-xxx"> markup SCH_Frontend already knows to
 * leave alone (see its "already_prism" branch) — so nothing on the
 * front-end rendering side needed to change for this to work.
 */
class SCH_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_script(
			'sch-prism-editor',
			'https://cdn.jsdelivr.net/npm/prismjs@' . SCH_PRISM_VERSION . '/components/prism-core.min.js',
			array(),
			SCH_PRISM_VERSION,
			true
		);

		// Same autoloader approach as the front end (see
		// SCH_Frontend::enqueue_assets()) — resolves each language's own
		// dependency chain instead of a hand-picked component list, so
		// every Prism-supported language works here too, not only the
		// ones in SCH_Settings::POPULAR_LANGUAGES.
		wp_register_script(
			'sch-prism-autoloader-editor',
			'https://cdn.jsdelivr.net/npm/prismjs@' . SCH_PRISM_VERSION . '/plugins/autoloader/prism-autoloader.min.js',
			array( 'sch-prism-editor' ),
			SCH_PRISM_VERSION,
			true
		);
		wp_add_inline_script(
			'sch-prism-autoloader-editor',
			"Prism.plugins.autoloader.languages_path = 'https://cdn.jsdelivr.net/npm/prismjs@" . esc_js( SCH_PRISM_VERSION ) . "/components/';",
			'after'
		);

		// The same theme configured on Settings > Code Highlight, so the
		// in-editor preview actually looks like the real front-end output
		// instead of an arbitrary fixed theme.
		$options    = sch_get_options();
		$theme      = SCH_Settings::sanitize_theme( $options['theme'] );
		$theme_file = ( 'prism' === $theme ) ? 'prism.min.css' : "prism-{$theme}.min.css";
		wp_register_style(
			'sch-prism-theme-editor',
			'https://cdn.jsdelivr.net/npm/prismjs@' . SCH_PRISM_VERSION . '/themes/' . $theme_file,
			array(),
			SCH_PRISM_VERSION
		);

		wp_register_script(
			'sch-block-editor',
			SCH_URL . "assets/editor{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'sch-prism-autoloader-editor' ),
			SCH_VERSION,
			true
		);
		wp_register_style(
			'sch-block-editor',
			SCH_URL . "assets/editor{$suffix}.css",
			array( 'sch-prism-theme-editor' ),
			SCH_VERSION
		);

		wp_localize_script( 'sch-block-editor', 'schBlockData', array(
			'languages' => $this->language_options(),
		) );
		wp_set_script_translations( 'sch-block-editor', 'slim-code-highlight', SCH_PATH . 'languages' );

		register_block_type( 'slim-code-highlight/code', array(
			'attributes'    => array(
				'language' => array(
					'type'    => 'string',
					'default' => 'none',
				),
				'content'  => array(
					'type'     => 'string',
					'source'   => 'html',
					'selector' => 'code',
					'default'  => '',
				),
			),
			'editor_script' => 'sch-block-editor',
			'editor_style'  => 'sch-block-editor',
		) );
	}

	/**
	 * SCH_Settings::POPULAR_LANGUAGES rows are [old lang:xxx token(s),
	 * modern Prism id, display name] — reused here for id => display name
	 * only, so the block's dropdown and the settings screen's reference
	 * table never list the two sets of languages differently.
	 *
	 * @return array<string,string>
	 */
	private function language_options() {
		$options = array();
		foreach ( SCH_Settings::POPULAR_LANGUAGES as $row ) {
			$options[ $row[1] ] = $row[2];
		}
		return $options;
	}
}
