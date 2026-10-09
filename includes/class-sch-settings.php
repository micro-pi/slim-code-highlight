<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > Code Highlight screen.
 */
class SCH_Settings {

	/** Prism.js themes offered in the dropdown — id => filename slug on the CDN. */
	const THEMES = array(
		'prism'          => 'Default',
		'coy'            => 'Coy',
		'dark'           => 'Dark',
		'funky'          => 'Funky',
		'okaidia'        => 'Okaidia',
		'solarizedlight' => 'Solarized Light',
		'tomorrow'       => 'Tomorrow Night',
		'twilight'       => 'Twilight',
	);

	/** Font size offered in the dropdown, as a percentage of the theme's own size. */
	const FONT_SIZES = array(
		100 => 'Theme default',
		90  => 'Small (90%)',
		80  => 'Smaller (80%)',
		70  => 'Smallest (70%)',
	);

	/**
	 * The most popular languages, for the reference table on the
	 * settings screen — each row is [old-style lang:xxx token(s), the
	 * modern Prism id, display name]. Not an exhaustive list: the
	 * autoloader (see SCH_Frontend::enqueue_assets()) can load any
	 * Prism-supported language, whether or not it's listed here — see
	 * render_reference().
	 */
	const POPULAR_LANGUAGES = array(
		array( 'java', 'java', 'Java' ),
		array( 'python', 'python', 'Python' ),
		array( 'php', 'php', 'PHP' ),
		array( 'javascript or js', 'javascript', 'JavaScript' ),
		array( 'typescript', 'typescript', 'TypeScript' ),
		array( 'c', 'c', 'C' ),
		array( 'c++', 'cpp', 'C++' ),
		array( 'arduino', 'arduino', 'Arduino (C/C++)' ),
		array( 'csharp', 'csharp', 'C#' ),
		array( 'go', 'go', 'Go' ),
		array( 'rust', 'rust', 'Rust' ),
		array( 'ruby', 'ruby', 'Ruby' ),
		array( 'html or xhtml', 'markup', 'HTML / XML' ),
		array( 'css', 'css', 'CSS' ),
		array( 'sql', 'sql', 'SQL' ),
		array( 'json', 'json', 'JSON' ),
		array( 'yaml', 'yaml', 'YAML' ),
		array( 'bash or shell', 'bash', 'Bash / shell' ),
		array( 'default, or console', 'none', 'Plain text (no coloring)' ),
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param string $hook
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_slim-code-highlight' !== $hook ) {
			return;
		}
		// Unminified source when SCRIPT_DEBUG is on (matches WordPress
		// core's own convention for its bundled scripts/styles).
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_style( 'slim-admin-ui', SCH_URL . "assets/slim-admin{$suffix}.css", array(), SCH_VERSION );
	}

	public function add_menu() {
		add_options_page(
			__( 'Code Highlight', 'slim-code-highlight' ),
			__( 'Code Highlight', 'slim-code-highlight' ),
			'manage_options',
			'slim-code-highlight',
			array( $this, 'render' )
		);
	}

	public function register_settings() {
		foreach ( array( 'sch_show_post', 'sch_show_page', 'sch_line_numbers', 'sch_copy_button' ) as $name ) {
			register_setting( 'sch_settings', $name, array(
				'type'              => 'boolean',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			) );
		}
		register_setting( 'sch_settings', 'sch_plain_pre', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'sch_settings', 'sch_theme', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_theme' ),
			'default'           => 'okaidia',
		) );
		register_setting( 'sch_settings', 'sch_font_size', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_font_size' ),
			'default'           => 100,
		) );
	}

	/**
	 * @param string $theme
	 * @return string A valid key of self::THEMES.
	 */
	public static function sanitize_theme( $theme ) {
		return isset( self::THEMES[ $theme ] ) ? $theme : 'okaidia';
	}

	/**
	 * @param mixed $size
	 * @return int A valid key of self::FONT_SIZES.
	 */
	public static function sanitize_font_size( $size ) {
		$size = (int) $size;
		return isset( self::FONT_SIZES[ $size ] ) ? $size : 100;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = sch_get_options();
		?>
		<div class="wrap slim-wrap">
			<h1><?php esc_html_e( 'Code Highlight', 'slim-code-highlight' ); ?> <span class="slim-version">v<?php echo esc_html( SCH_VERSION ); ?></span></h1>
			<p class="slim-lead"><?php esc_html_e( 'Syntax-highlights code inside <pre> blocks with Prism.js, including the old lang:xxx decode:true markup left over from a previous highlighter plugin.', 'slim-code-highlight' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'sch_settings' ); ?>
				<div class="slim-card">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show on', 'slim-code-highlight' ); ?></th>
							<td class="slim-checks">
								<label>
									<input type="checkbox" name="sch_show_post" value="1" <?php checked( $options['show_post'] ); ?>>
									<?php esc_html_e( 'Posts', 'slim-code-highlight' ); ?>
								</label>
								<label>
									<input type="checkbox" name="sch_show_page" value="1" <?php checked( $options['show_page'] ); ?>>
									<?php esc_html_e( 'Pages', 'slim-code-highlight' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sch_theme"><?php esc_html_e( 'Theme', 'slim-code-highlight' ); ?></label></th>
							<td>
								<select name="sch_theme" id="sch_theme">
									<?php foreach ( self::THEMES as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['theme'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sch_font_size"><?php esc_html_e( 'Font size', 'slim-code-highlight' ); ?></label></th>
							<td>
								<select name="sch_font_size" id="sch_font_size">
									<?php foreach ( self::FONT_SIZES as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['font_size'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Extras', 'slim-code-highlight' ); ?></th>
							<td class="slim-checks">
								<label>
									<input type="checkbox" name="sch_line_numbers" value="1" <?php checked( $options['line_numbers'] ); ?>>
									<?php esc_html_e( 'Line numbers', 'slim-code-highlight' ); ?>
								</label>
								<label>
									<input type="checkbox" name="sch_copy_button" value="1" <?php checked( $options['copy_button'] ); ?>>
									<?php esc_html_e( 'Copy-to-clipboard button', 'slim-code-highlight' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Unmarked blocks', 'slim-code-highlight' ); ?></th>
							<td class="slim-checks">
								<label>
									<input type="checkbox" name="sch_plain_pre" value="1" <?php checked( $options['plain_pre'] ); ?>>
									<?php esc_html_e( 'Also apply to <pre> blocks with no language class', 'slim-code-highlight' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Off by default: a <pre> block with no class could be genuinely non-code preformatted text, not a code sample.', 'slim-code-highlight' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				<div class="slim-actions"><?php submit_button( null, 'primary', 'submit', false ); ?></div>
			</form>

			<?php $this->render_reference(); ?>
		</div>
		<?php
	}

	/**
	 * Supported-languages reference and usage examples, shown below the
	 * settings form. Purely informational — no settings fields here.
	 */
	private function render_reference() {
		?>
		<div class="slim-card">
			<h2><?php esc_html_e( 'Supported languages & how to use them', 'slim-code-highlight' ); ?></h2>
			<p><?php esc_html_e( 'Write a code block using either of the two shapes below, anywhere in a post or page (the block editor\'s "Custom HTML" block, or the Text/Code tab of the Classic editor — not the Visual editor, which reformats raw HTML). Both are recognized automatically; no shortcode needed.', 'slim-code-highlight' ); ?></p>

			<h3><?php esc_html_e( 'Option 1: the old lang:xxx decode:true markup', 'slim-code-highlight' ); ?></h3>
			<p><?php esc_html_e( 'What every existing post on this site already uses. Just a class on the <pre> tag — this plugin rewrites it to Prism.js markup automatically, every time the page is viewed.', 'slim-code-highlight' ); ?></p>
			<pre class="slim-code"><?php echo esc_html( "<pre class=\"lang:java decode:true\">\npublic class Hello {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, world!\");\n    }\n}\n</pre>" ); ?></pre>

			<h3><?php esc_html_e( 'Option 2: modern Prism.js markup', 'slim-code-highlight' ); ?></h3>
			<p><?php esc_html_e( 'Also recognized, untouched — use this for a new post if you\'d rather write it directly in the shape Prism itself expects.', 'slim-code-highlight' ); ?></p>
			<pre class="slim-code"><?php echo esc_html( "<pre><code class=\"language-python\">\ndef hello():\n    print(\"Hello, world!\")\n</code></pre>" ); ?></pre>

			<h3><?php esc_html_e( 'Plain output / console text, no coloring', 'slim-code-highlight' ); ?></h3>
			<p><?php esc_html_e( 'For command output or a terminal transcript where nothing should be colored — still gets line numbers and the copy button if those are enabled above.', 'slim-code-highlight' ); ?></p>
			<pre class="slim-code"><?php echo esc_html( "<pre class=\"console\">\n\$ mvn package\nBUILD SUCCESS\n</pre>" ); ?></pre>

			<p class="description"><?php esc_html_e( 'Inside either shape, escape any <, > and & in the code itself as &lt;, &gt; and &amp; — the block is still HTML underneath, so unescaped angle brackets would be parsed as real tags rather than shown as text.', 'slim-code-highlight' ); ?></p>

			<h3><?php esc_html_e( 'Most popular languages', 'slim-code-highlight' ); ?></h3>
			<p><?php esc_html_e( 'Use either the old-style token (after lang:) or the modern id (after language- in a <code> class) — both work. This isn\'t the full list: any language Prism.js supports works the same way, even if it isn\'t in this table — see the link below.', 'slim-code-highlight' ); ?></p>
			<table class="widefat striped slim-mid">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Language', 'slim-code-highlight' ); ?></th>
						<th><?php esc_html_e( 'Old-style: lang:xxx', 'slim-code-highlight' ); ?></th>
						<th><?php esc_html_e( 'Modern: language-xxx', 'slim-code-highlight' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( self::POPULAR_LANGUAGES as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row[2] ); ?></td>
							<td><code><?php echo esc_html( $row[0] ); ?></code></td>
							<td><code><?php echo esc_html( $row[1] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to Prism.js's own supported-languages list */
					esc_html__( 'Full list of every supported language: %s', 'slim-code-highlight' ),
					'<a href="https://prismjs.com/#supported-languages" target="_blank" rel="noopener noreferrer">prismjs.com/#supported-languages</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
