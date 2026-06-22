<?php
/**
 * Uninstall cleanup.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Uninstaller
{
	/**
	 * Uninstall.
	 *
	 * @return void
	 */
	public static function uninstall()
	{
		delete_option(MephSeo_Settings::OPTION);
		delete_option(MephSeo_Settings::REDIRECTS_OPTION);
		delete_option(MephSeo_Settings::NOT_FOUND_OPTION);
	}
}
