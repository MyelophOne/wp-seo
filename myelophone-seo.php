<?php
/**
 * MyelophOne SEO - WordPress Plugin
 *
 * @package     MyelophOne SEO
 * @author      Aliaksandr Ivanou
 * @license     GPLv2 or later
 *
 * @wordpress-plugin
 * Plugin Name: MyelophOne SEO
 * Plugin URI:  https://github.com/MyelophOne/wp-seo
 * Description: SEO extension for MyelophOne Core with metadata, schema, redirects, robots.txt, social previews, and WooCommerce support.
 * Version:     1.0.0
 * Author:      Aliaksandr Ivanou
 * Author URI:  https://github.com/aleksivanou
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: myelophone-seo
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: myelophone-core
 */

if (!defined("ABSPATH")) {
	exit();
}

define("MYELOPHONE_SEO_VERSION", "1.0.0");
define("MYELOPHONE_SEO_FILE", __FILE__);
define("MYELOPHONE_SEO_DIR", plugin_dir_path(__FILE__));
define("MYELOPHONE_SEO_URL", plugin_dir_url(__FILE__));
define("MYELOPHONE_SEO_BASENAME", plugin_basename(__FILE__));

spl_autoload_register(function ($class_name) {
	$prefix = "MephSeo_";
	$base_dir = MYELOPHONE_SEO_DIR . "includes/";

	$len = strlen($prefix);
	if (strncmp($prefix, $class_name, $len) !== 0) {
		return;
	}

	$relative_class = substr($class_name, $len);
	$file = $base_dir . str_replace("_", "/", $relative_class) . ".php";

	if (file_exists($file)) {
		require_once $file;
	}
});

add_action("plugins_loaded", "myelophone_seo_init_plugin", 12);

/**
 * Initialize plugin.
 *
 * @return void
 */
function myelophone_seo_init_plugin()
{
	if (!myelophone_seo_is_core_active()) {
		add_action("admin_notices", "myelophone_seo_core_missing_notice");
		return;
	}

	if (class_exists("MephSeo_Plugin")) {
		MephSeo_Plugin::get_instance()->init();
	}
}

/**
 * Check MyelophOne Core dependency.
 *
 * @return bool
 */
function myelophone_seo_is_core_active()
{
	if (defined("MYELOPHONE_CORE_VERSION") || class_exists("Meph_Plugin")) {
		return true;
	}

	if (!function_exists("is_plugin_active")) {
		require_once ABSPATH . "wp-admin/includes/plugin.php";
	}

	return is_plugin_active("myelophone-core/myelophone-core.php") ||
		is_plugin_active("CorePlugin/myelophone-core.php");
}

/**
 * Admin dependency notice.
 *
 * @return void
 */
function myelophone_seo_core_missing_notice()
{
	if (!current_user_can("activate_plugins")) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php echo esc_html__(
				"MyelophOne SEO requires MyelophOne Core to be installed and active.",
				"myelophone-seo",
			); ?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @return void
 */
function myelophone_seo_activate_plugin()
{
	if (!myelophone_seo_is_core_active()) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(
			esc_html__(
				"MyelophOne SEO requires MyelophOne Core. Activate Core first, then activate SEO.",
				"myelophone-seo",
			),
		);
	}

	require_once MYELOPHONE_SEO_DIR . "includes/Activator.php";
	MephSeo_Activator::activate();
}
register_activation_hook(__FILE__, "myelophone_seo_activate_plugin");

/**
 * Uninstall hook.
 *
 * @return void
 */
function myelophone_seo_uninstall_plugin()
{
	require_once MYELOPHONE_SEO_DIR . "includes/Uninstaller.php";
	MephSeo_Uninstaller::uninstall();
}
register_uninstall_hook(__FILE__, "myelophone_seo_uninstall_plugin");
