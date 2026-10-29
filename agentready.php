<?php
/**
 * Plugin Name:       AgentReady – llms.txt & Markdown for AI Agents
 * Description:       Serves an llms.txt index and a clean Markdown version of every page, built from the rendered output so ACF, Gutenberg, Elementor and classic content all work.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Sami Ahmed Siddiqui
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentready
 * Domain Path:       /languages
 *
 * @package AgentReady
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'AGENTREADY_VERSION', '0.1.0' );
define( 'AGENTREADY_FILE', __FILE__ );
define( 'AGENTREADY_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGENTREADY_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( AGENTREADY_PATH . 'vendor/autoload.php' ) ) {
	require_once AGENTREADY_PATH . 'vendor/autoload.php';
} else {
	require_once AGENTREADY_PATH . 'src/Autoloader.php';
	AgentReady\Autoloader::register( 'AgentReady\\', AGENTREADY_PATH . 'src/' );
}

register_activation_hook( __FILE__, array( AgentReady\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( AgentReady\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( AgentReady\Plugin::class, 'boot' ) );
