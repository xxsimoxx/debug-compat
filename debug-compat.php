<?php
/**
 * Plugin Name:          Debug Compat
 * Plugin URI:           https://github.com/ClassicPress/debug-compat
 * Description:          Get debug information for Block Compatibility.
 * Version:              0.0.2
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

function myplugin_caching_test() { // ELIMINAMI
    $result = array(
        'label'       => __( 'Caching is enabled' ),
        'status'      => 'good',
        'badge'       => array(
            'label' => __( 'Performance' ),
            'color' => 'orange',
        ),
        'description' => sprintf(
            '<p>%s</p>',
            __( 'Caching can help load your site more quickly for visitors.' )
        ),
        'actions'     => '',
        'test'        => 'caching_plugin',
    );

    if ( ! true ) {
        $result['status'] = 'recommended';
        $result['label'] = __( 'Caching is not enabled' );
        $result['description'] = sprintf(
            '<p>%s</p>',
            __( 'Caching is not currently enabled on your site. Caching can help load your site more quickly for visitors.' )
        );
        $result['actions'] .= sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url( admin_url( 'admin.php?page=cachingplugin&action=enable-caching' ) ),
            __( 'Enable Caching' )
        );
    }

    return $result;
}


class debugCompat {

	public function __construct() {
		add_action( 'using_block_function', array( $this, 'log' ) );
		add_action( 'admin_menu', array( $this, 'create_menu' ), 100 );
		add_filter( 'site_status_tests', array( $this, 'add_site_status_tests' ) );
		register_deactivation_hook( __FILE__ , array( $this, 'clean_options' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__, 'clean_options' ) );
	}

	public function add_site_status_tests( $tests ) {
		//var_dump($tests);
		$tests['direct']['dc_plugins_blocks'] = array(
			'label' => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'test'  => array( $this, 'test_plugin' ),
		);
		return $tests;
	}

	public function test_plugin() {
		$options = $this->get_options();
		$result = array(
			'label'       => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'compatibility',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				 esc_html__( 'No plugins are using block functions.', 'debug-compat' ),
			),
			'actions'     => '',
			'test'        => 'dc_plugins_blocks',
		);
		if ( $options['data']['plugins'] === array() ) {
			return $result;
		}
		$result = array(
			'label'       => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => 'compatibility',
				'color' => 'orange',
			),
			'description' => $this->list_items( $options, 'plugins' ),
			'actions'     => esc_html__( 'Plugins in this list may not work properly.', 'debug-compat' ),
			'test'        => 'dc_plugins_blocks',
		);
		return $result;
	}

	private function list_items( $options, $type ) {
		$response = '';
		foreach ( $options['data'][$type] as $who => $what ) {
			$response .= sprintf(
				'<p><b>%s</b>: %s.</p>',
				 esc_html( $who ),
				 implode( ', ', $what)
			);
		}
		return $response;
	}

	private function get_options() {
		$default = array(
			'db_version' => '2',
			'data'       => array(
				'themes'        => array(),
				'parent_themes' => array(),
				'plugins'       => array(),
				'misc'          => array(),
			),
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

		update_option( 'dc_options', $options );
	}

	public function render_page (){
		echo '<h1>Block Compatibility Inspector <span class="dashicons dashicons-code-standards"></span></h1>';

		echo '<h2>Data (<code>dc_options</code>)</h2>';
		echo'<pre>';
		$options = get_option( 'dc_options' );
		var_dump($options);
		echo'</pre>';
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
