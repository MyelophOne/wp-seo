<?php
/**
 * Robots.txt management.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Robots
{
	/**
	 * Settings.
	 *
	 * @var MephSeo_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param MephSeo_Settings $settings Settings.
	 */
	public function __construct($settings)
	{
		$this->settings = $settings;
		add_filter("robots_txt", [$this, "filter_robots_txt"], 20, 2);
	}

	/**
	 * Optimize virtual robots.txt.
	 *
	 * @param string $output Existing output.
	 * @param bool   $public Site public.
	 * @return string
	 */
	public function filter_robots_txt($output, $public)
	{
		$robots = self::build_robots_txt($this->settings, $public);
		if (!$robots) {
			return $output;
		}

		return apply_filters(
			"myelophone_seo_robots_txt",
			$robots,
			$public,
			$this->settings,
		);
	}

	/**
	 * Build robots.txt contents without registering hooks.
	 *
	 * @param MephSeo_Settings $settings Settings.
	 * @param bool             $public Site public.
	 * @return string
	 */
	public static function build_robots_txt($settings, $public = true)
	{
		if (!$public) {
			return "User-agent: *\nDisallow: /\n";
		}

		$lines = [];

		if ($settings->enabled("enable_robots")) {
			$lines = [
				"User-agent: *",
				"Disallow: /wp-admin/",
				"Allow: /wp-admin/admin-ajax.php",
				"Disallow: /wp-login.php",
				"Disallow: /wp-register.php",
				"Disallow: /?s=",
				"Disallow: /search/",
				"Disallow: /*?replytocom=",
				"Disallow: /*&replytocom=",
				"Disallow: /xmlrpc.php",
			];

			$params = array_filter(array_map("trim", explode(",", $settings->get("robots_clean_params", ""))));
			if ($params) {
				$lines[] = "Clean-param: " . implode("&", array_map("sanitize_key", $params));
			}

		}

		if ($settings->enabled("block_intrusive_bots")) {
			$lines[] = "";
			foreach (["AhrefsBot", "SemrushBot", "MJ12bot", "DotBot", "BLEXBot"] as $bot) {
				$lines[] = "User-agent: " . $bot;
				$lines[] = "Disallow: /";
			}
		}

		if ($settings->enabled("block_ai_training")) {
			$lines[] = "";
			foreach (["GPTBot", "CCBot", "Google-Extended", "ClaudeBot", "Bytespider"] as $bot) {
				$lines[] = "User-agent: " . $bot;
				$lines[] = "Disallow: /";
			}
		}

		if ($settings->enabled("enable_robots")) {
			$lines[] = "";
			$lines[] = "Sitemap: " . home_url($settings->enabled("enable_sitemap") ? "/sitemap.xml" : "/wp-sitemap.xml");
		}

		$lines = apply_filters("myelophone_seo_robots_txt_lines", $lines, $settings, $public);

		return $lines ? implode("\n", $lines) . "\n" : "";
	}
}
