<?php
/**
 * Plugin Name:          Debug Compat
 * Plugin URI:           https://github.com/ClassicPress/debug-compat
 * Description:          Get debug information for Block Compatibility.
 * Version:              0.0.1
 * License:              GPL2
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Author:               ClassicPress
 * Author URI:           https://github.com/ClassicPress/
 * Text Domain:          debug-compat
 * Domain Path:          /languages
 */

namespace ClassicPress\DebugCompat;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class debugCompat {

	public function __construct() {
		add_action( 'using_block_function', array( $this, 'log' ) );
		add_action( 'admin_menu', array( $this, 'create_menu' ), 100 );
		register_deactivation_hook( __FILE__ , array( $this, 'clean_options' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__, 'clean_options' ) );
	}

	private function get_options() {
		$default = array(
			'data' => array(
				'themes'        => array(),
				'parent_themes' => array(),
				'plugins'       => array(),
				'misc'          => array(),
			)
		);
		$options = get_option( 'dc_options', $default );
		return $options;
	}

	public function log( $trace ) {
		$options = $this->get_options();
		$func    = $trace[1]['function'];

		if ( 0 === strpos( $trace[1]['file'], realpath( get_stylesheet_directory() ) ) ) {
			// Theme
			if( ! isset( $options['data']['themes'][ wp_get_theme()->get( 'Name' ) ] ) || ! in_array( $func, $options['data']['themes'][ wp_get_theme()->get( 'Name' ) ] ) ) {
				$options['data']['themes'][ wp_get_theme()->get( 'Name' ) ][] = $func;
			}
		} elseif ( 0 === strpos( $trace[1]['file'], realpath( get_template_directory() ) ) ) {
			// Child theme
			if( ! isset( $options['data']['parent_themes'][ wp_get_theme()->parent()->get( 'Name' ) ] ) || ! in_array( $func, $options['data']['parent_themes'][ wp_get_theme()->parent()->get( 'Name' ) ] ) ) {
				$options['data']['parent_themes'][ wp_get_theme()->parent()->get( 'Name' ) ][] = $func;
			}
		} else {
			$files  = array_column( $trace, 'file' );
			$active = wp_get_active_and_valid_plugins();
			$plugin = array_intersect( $files, $active );
			if ( count( $plugin ) !== 1 ) {
				// Hooked somewhere
				if( ! in_array( $func, $options['data']['misc'] ) ) {
					$options['data']['misc'][] = $func;
				}
			} else {
				// Plugin
				$plugin_data = get_plugin_data( array_pop( $plugin ) );
				$plugin_name = $plugin_data['Name'];
				if( ! isset( $options['data']['plugins'][$plugin_name] ) || ! in_array( $func, $options['data']['plugins'][$plugin_name] ) ) {
					$options['data']['plugins'][$plugin_name][] = $func;
				}
			}
		}

		update_option( 'dc_last', $trace );
		update_option( 'dc_options', $options );
	}

	public function render_page (){
		echo '<h1>Block Compatibility Inspector <span class="dashicons dashicons-code-standards"></span></h1>';

		echo '<h2>Data (<code>dc_options</code>)</h2>';
		echo'<pre>';
		$options = get_option( 'dc_options' );
		var_dump($options);
		echo'</pre>';

		echo '<a href="#" onClick="jQuery(\'#dc-last\').css(\'display\', \'block\'); return false;">See last trace.</a>';
		echo '<div id="dc-last" style="display:none;">';

		echo '<h2>Debug (last trace, <code>dc_last</code>)</h2>';
		echo '<pre>';
		$trace = get_option( 'dc_last' );
		var_dump($trace);
		echo'</pre>';
		echo '</div>';
	}


	public function create_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = add_menu_page(
			'Debug Compat',
			'Debug Compat',
			'manage_options',
			'debugcompat',
			array( $this, 'render_page' ),
			'dashicons-code-standards'
		);
	}

	public static function clean_options() {
		delete_option( 'dc_options' );
		delete_option( 'dc_last' );
	}

}

new debugCompat;
