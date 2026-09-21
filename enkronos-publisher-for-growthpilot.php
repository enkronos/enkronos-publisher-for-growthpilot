<?php
/**
 * Plugin Name: Enkronos Publisher for GrowthPilot.cloud
 * Plugin URI: https://github.com/enkronos
 * Description: Secure WordPress REST API connector for publishing content from GrowthPilot.cloud.
 * Version: 1.1.1
 * Author: Enkronos
 * Author URI: https://enkronos.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: enkronos-publisher-for-growthpilot
 */

declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

define('ENKRPUFO_VERSION', '1.1.1');
define('ENKRPUFO_PLUGIN_FILE', __FILE__);
define('ENKRPUFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ENKRPUFO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-db.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-utils.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-logger.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-auth.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-seo.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-rest.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-admin.php';
require_once ENKRPUFO_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

add_action('plugins_loaded', function() {
    Plugin::get_instance()->init();
});
