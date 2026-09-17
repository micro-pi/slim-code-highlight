<?php
// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'sch_show_post',
	'sch_show_page',
	'sch_theme',
	'sch_line_numbers',
	'sch_copy_button',
	'sch_plain_pre',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
