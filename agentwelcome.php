<?php
/**
 * Plugin Name:       AgentWelcome – llms.txt & Markdown for AI Agents
 * Description:       Serves an llms.txt index and a clean Markdown version of every page, built from the rendered output so ACF, Gutenberg, Elementor and classic content all work.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Sami Ahmed Siddiqui
 * Author URI:        https://www.linkedin.com/in/sami-ahmed-siddiqui/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentwelcome
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'AGENTWELCOME_VERSION', '1.0.0' );
define( 'AGENTWELCOME_FILE', __FILE__ );
define( 'AGENTWELCOME_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGENTWELCOME_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( AGENTWELCOME_PATH . 'vendor/autoload.php' ) ) {
	require_once AGENTWELCOME_PATH . 'vendor/autoload.php';
} else {
	require_once AGENTWELCOME_PATH . 'src/Autoloader.php';
	AgentWelcome\Autoloader::register( 'AgentWelcome\\', AGENTWELCOME_PATH . 'src/' );
}

add_action( 'plugins_loaded', array( AgentWelcome\Plugin::class, 'boot' ) );
