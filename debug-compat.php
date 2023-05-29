<?php
/**
 * Plugin Name:          Debug Compat
 * Plugin URI:           https://github.com/ClassicPress/debug-compat
 * Description:          Get debug information for Block Compatibility.
 * Version:              0.0.3
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
		add_action( 'update_option_blocks_compatibility_level', array( $this, 'clean_options' ), 10, 2 );
		$blocks_compatibility_level = (int) get_option( 'blocks_compatibility_level', 1 );
		if ( $blocks_compatibility_level !== 2 ) {
			add_filter( 'site_status_tests', array( $this, 'add_site_status_not_working' ) );
			return;
		}
		add_action( 'using_block_function', array( $this, 'log' ) );
		add_action( 'admin_menu', array( $this, 'create_menu' ), 100 ); // To be deleted
		add_filter( 'site_status_tests', array( $this, 'add_site_status_tests' ) );
		register_deactivation_hook( __FILE__ , array( $this, 'clean_options' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__, 'clean_options' ) );
	}

	public function add_site_status_not_working( $tests ) {
		$tests['direct']['dc_not_working'] = array(
			'label' => esc_html__( 'Debug Compat plugin is not working', 'debug-compat' ),
			'test'  => array( $this, 'not_working' ),
		);
		return $tests;
	}

	public function add_site_status_tests( $tests ) {
		$tests['direct']['dc_plugins_blocks'] = array(
			'label' => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'test'  => array( $this, 'test_plugin' ),
		);
		$tests['direct']['dc_themes_blocks'] = array(
			'label' => esc_html__( 'Themes using block functions', 'debug-compat' ),
			'test'  => array( $this, 'test_theme' ),
		);
		return $tests;
	}

	public function not_working() {
		$descritpion = esc_html__('Debug compat requires Block Compatibility set to Troubleshooting to work.', 'debug-compat');
		$action = '<a href="' . admin_url( 'options-general.php' ) . '">';
		$action .= esc_html__( 'Change Block Compatibility option', 'debug-compat' );
		$action .= '</a> or <a href="' . admin_url( 'plugins.php' ) . '">';
		$action .= esc_html__( 'disable Debug Compat plugin', 'debug-compat' );
		$action .= '</a>.';

		$result = array(
			'label'       => esc_html__( 'Debug Compat plugin is not working', 'debug-compat' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => 'Plugin',
				'color' => 'red',
			),
			'description' => $descritpion,
			'actions'     => $action,
			'test'        => 'dc_plugins_blocks',
		);
		return $result;
	}

	public function test_plugin() {
		$options = $this->get_options();
		$result = array(
			'label'       => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'Compatibility',
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
		$action  = esc_html__( 'Plugins in this list may have issues. ', 'debug-compat' );
		$action .= ' <a href="https://docs.classicpress.net/user-guides/using-classicpress/site-health-screen/#block-compatibility">' . esc_html__( 'Learn more.', 'debug-compat' ) . '</a>';
		$result = array(
			'label'       => esc_html__( 'Plugins using block functions', 'debug-compat' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => 'Compatibility',
				'color' => 'orange',
			),
			'description' => $this->list_items( $options, 'plugins' ),
			'actions'     => $action,
			'test'        => 'dc_plugins_blocks',
		);
		return $result;
	}

	public function test_theme() {
		$options = $this->get_options();
		$result = array(
			'label'       => esc_html__( 'Themes using block functions', 'debug-compat' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'Compatibility',
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				 esc_html__( 'No themes are using block functions.', 'debug-compat' ),
			),
			'actions'     => '',
			'test'        => 'dc_themes_blocks',
		);
		$themes = array_merge(  $options['data']['themes'],  $options['data']['parent_themes'] );
		if ( $themes === array() ) {
			return $result;
		}
		$action  = esc_html__( 'Themes in this list may have issues. ', 'debug-compat' );
		$action .= ' <a href="https://docs.classicpress.net/user-guides/using-classicpress/site-health-screen/#block-compatibility">' . esc_html__( 'Learn more.', 'debug-compat' ) . '</a>';
		$result = array(
			'label'       => esc_html__( 'Themes using block functions', 'debug-compat' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => 'Compatibility',
				'color' => 'orange',
			),
			'description' => $this->list_items( $options, 'themes' ) . $this->list_items( $options, 'parent_themes' ),
			'actions'     => $action,
			'test'        => 'dc_themes_blocks',
		);
		return $result;
	}

	private function list_items( $options, $type ) {
		$response = '';
		foreach ( $options['data'][$type] as $who => $what ) {
			$response .= sprintf(
				wp_kses(
					/* translators: %1$s is the plugin/theme name, %b$s is a comma separated list of functions */
					'<p><b>%1$s</b> is using: %2$s.</p>',
					array(
						'b' => array(),
						'p' => array(),
					)
				),
				 esc_html( $who ),
				 esc_html( implode( ', ', $what ) )
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

	public function render_page (){ // To be deleted
		echo '<h1>Block Compatibility Inspector <span class="dashicons dashicons-code-standards"></span></h1>';

		echo '<h2>Data (<code>dc_options</code>)</h2>';
		echo'<pre>';
		$options = get_option( 'dc_options' );
		var_dump($options);
		echo'</pre>';
	}


	public function create_menu() { // To be deleted
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
	}

}

new debugCompat;
