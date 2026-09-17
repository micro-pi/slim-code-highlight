<?php
/**
 * Plugin Name: Slim Code Highlight
 * Plugin URI:  https://github.com/micro-pi/slim-code-highlight
 * Description: Syntax-highlights <pre> code blocks (including the old lang:xxx decode:true markup left over from a previous highlighter plugin) using Prism.js. No bloat.
 * Version:     1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author:      MicroPi
 * Author URI:  https://micro-pi.ru
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: slim-code-highlight
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCH_VERSION', '1.0.0' );
define( 'SCH_FILE', __FILE__ );
define( 'SCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCH_URL', plugin_dir_url( __FILE__ ) );

// Pinned upstream Prism.js version — bump deliberately, not automatically,
// so a new Prism release can't change rendering on this site unannounced.
define( 'SCH_PRISM_VERSION', '1.30.0' );

/**
 * Central options reader, defaults included — shared by the frontend
 * renderer and the settings screen.
 *
 * @return array
 */
function sch_get_options() {
	return array(
		'show_post'    => (bool) get_option( 'sch_show_post', true ),
		'show_page'    => (bool) get_option( 'sch_show_page', true ),
		'theme'        => (string) get_option( 'sch_theme', 'okaidia' ),
		'line_numbers' => (bool) get_option( 'sch_line_numbers', true ),
		'copy_button'  => (bool) get_option( 'sch_copy_button', true ),
		// Off by default: a <pre> with no recognized class could be
		// genuinely non-code preformatted text (ASCII art, a quoted log
		// snippet, etc.) — only opt it in deliberately.
		'plain_pre'    => (bool) get_option( 'sch_plain_pre', false ),
	);
}

require_once SCH_PATH . 'includes/class-sch-frontend.php';
require_once SCH_PATH . 'includes/class-sch-settings.php';

/**
 * Boot the plugin. SCH_Frontend runs on every front-end request; the
 * settings screen only loads in wp-admin.
 */
function sch_init() {
	new SCH_Frontend();

	if ( is_admin() ) {
		new SCH_Settings();
	}
}
add_action( 'plugins_loaded', 'sch_init' );

/**
 * Not on the WordPress.org plugin directory, so translations aren't
 * loaded automatically — bundled in the plugin's own /languages folder.
 */
function sch_load_textdomain() {
	load_plugin_textdomain( 'slim-code-highlight', false, dirname( plugin_basename( SCH_FILE ) ) . '/languages' );
}
add_action( 'init', 'sch_load_textdomain' );

/**
 * "Settings" quick link on the Plugins screen.
 *
 * @param string[] $links
 * @return string[]
 */
function sch_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=slim-code-highlight' ) ) . '">' . esc_html__( 'Settings', 'slim-code-highlight' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SCH_FILE ), 'sch_plugin_action_links' );
