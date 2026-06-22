<?php
/**
 * Activation tasks.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Activator
{
	/**
	 * Activate.
	 *
	 * @return void
	 */
	public static function activate()
	{
		if (!get_option(MephSeo_Settings::OPTION)) {
			add_option(MephSeo_Settings::OPTION, MephSeo_Settings::defaults());
		}

		if (!get_option(MephSeo_Settings::REDIRECTS_OPTION)) {
			add_option(MephSeo_Settings::REDIRECTS_OPTION, []);
		}

		if (!get_option(MephSeo_Settings::NOT_FOUND_OPTION)) {
			add_option(MephSeo_Settings::NOT_FOUND_OPTION, []);
		}
	}
}
