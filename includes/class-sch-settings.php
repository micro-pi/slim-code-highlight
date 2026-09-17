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

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Slim Code Highlight', 'slim-code-highlight' ),
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
	}

	/**
	 * @param string $theme
	 * @return string A valid key of self::THEMES.
	 */
	public static function sanitize_theme( $theme ) {
		return isset( self::THEMES[ $theme ] ) ? $theme : 'okaidia';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = sch_get_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Slim Code Highlight', 'slim-code-highlight' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Syntax-highlights code inside <pre> blocks with Prism.js, including the old lang:xxx decode:true markup left over from a previous highlighter plugin.', 'slim-code-highlight' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'sch_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on', 'slim-code-highlight' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="checkbox" name="sch_show_post" value="1" <?php checked( $options['show_post'] ); ?>>
								<?php esc_html_e( 'Posts', 'slim-code-highlight' ); ?>
							</label>
							<label style="display:block;">
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
						<th scope="row"><?php esc_html_e( 'Extras', 'slim-code-highlight' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="checkbox" name="sch_line_numbers" value="1" <?php checked( $options['line_numbers'] ); ?>>
								<?php esc_html_e( 'Line numbers', 'slim-code-highlight' ); ?>
							</label>
							<label style="display:block;">
								<input type="checkbox" name="sch_copy_button" value="1" <?php checked( $options['copy_button'] ); ?>>
								<?php esc_html_e( 'Copy-to-clipboard button', 'slim-code-highlight' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Unmarked blocks', 'slim-code-highlight' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="checkbox" name="sch_plain_pre" value="1" <?php checked( $options['plain_pre'] ); ?>>
								<?php esc_html_e( 'Also apply to <pre> blocks with no language class', 'slim-code-highlight' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default: a <pre> block with no class could be genuinely non-code preformatted text, not a code sample.', 'slim-code-highlight' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
