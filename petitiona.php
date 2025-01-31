<?php
/*
Plugin Name: Petitiona
Plugin URI: 
Description: Create and manage petitions with customizable forms
Version: 1.0.0
Requires at least: 5.2
Requires PHP: 7.2
Author: Mohamed Aymane Zizi
Author URI: https://github.com/AymaneZizi
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

define('PETITION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PETITION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PETITIONA_VERSION', '1.0.0');

require_once PETITION_PLUGIN_DIR . 'includes/class-database.php';
require_once PETITION_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once PETITION_PLUGIN_DIR . 'includes/class-frontend.php';
require_once PETITION_PLUGIN_DIR . 'admin/class-admin.php';

register_activation_hook(__FILE__, function() {
    $database = Petitiona\Database::getInstance();
    $database->createTables();
});

// Initialize plugin
function init_petitiona() {
    Petitiona\Database::getInstance();
    Petitiona\Shortcode::getInstance();
    Petitiona\Frontend::getInstance();
    Petitiona\Admin::getInstance();
}
add_action('plugins_loaded', 'init_petitiona');
