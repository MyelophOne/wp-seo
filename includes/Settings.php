<?php
/**
 * Settings storage and helpers.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Settings
{
	const OPTION = "meph_seo_options";
	const REDIRECTS_OPTION = "meph_seo_redirects";
	const NOT_FOUND_OPTION = "meph_seo_404_log";
	const PAGE = "myelophone-seo";
	const LEGACY_PRODUCT_TITLE = "%%product_name%% %%sep%% %%brand%% %%sep%% %%price%%";
	const LEGACY_PRODUCT_DESCRIPTION = "%%product_name%% from %%brand%%. %%price%% %%stock%%";
	const LEGACY_ARCHIVE_TITLE = "%%term_title%% %%sep%% %%sitename%%";
	const LEGACY_ARCHIVE_DESCRIPTION = "Browse %%term_title%% articles, products, and resources from %%sitename%%.";
	const PREVIOUS_PRODUCT_DESCRIPTION = "Order online %%primary_category%% %%title%% %%brand%% at %%sitename%% from [%%price_min%% ?? %%price%%]. %%wc_shortdesc%%";
	const PREVIOUS_PRODUCT_DESCRIPTION_2 = "Order online %%title%% %%primary_category%% %%brand%% at %%sitename%% from [%%price_min%% ?? %%price%%]. %%wc_shortdesc%%";

	/**
	 * Runtime options cache.
	 *
	 * @var array|null
	 */
	private $options_cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults()
	{
		return [
			"enable_metadata" => "0",
			"enable_schema" => "0",
			"enable_social" => "0",
			"enable_breadcrumbs" => "0",
			"enable_robots" => "0",
			"enable_sitemap" => "0",
			"enable_indexnow" => "0",
			"enable_content_shortcode" => "0",
			"enable_transliteration" => "0",
			"block_ai_training" => "0",
			"block_intrusive_bots" => "0",
			"default_title" => "%%title%% %%sep%% %%sitename%%",
			"default_description" => "%%excerpt%%",
			"product_title" => "Buy %%title%% %%brand%% online %%sep%% %%sitename%%",
			"product_description" => "Order online %%title%% [%%primary_category%% === \"Uncategorized\" ? \"\" : %%primary_category%%] %%brand%% at %%sitename%% [%%price_min%% ? from %%price_min%% : \"\"]. %%wc_shortdesc%%",
			"product_titles" => [],
			"product_descriptions" => [],
			"auto_product_recommended" => "0",
			"global_noindex" => "0",
			"archive_title" => "",
			"archive_description" => "",
			"separator" => "-",
			"organization_name" => "",
			"organization_logo" => "",
			"global_social_image" => "",
			"same_as" => "",
			"local_business_type" => "LocalBusiness",
			"local_address" => "",
			"local_phone" => "",
			"canonical_mode" => "self",
			"noindex_search" => "0",
			"noindex_author_archives" => "0",
			"disable_author_archives" => "0",
			"noindex_date_archives" => "0",
			"noindex_tag_archives" => "0",
			"noindex_empty_archives" => "0",
			"noindex_paginated_pages" => "0",
			"disable_attachment_urls" => "0",
			"robots_clean_params" => "replytocom,utm_source,utm_medium,utm_campaign,utm_term,utm_content,fbclid,gclid",
			"not_found_max_entries" => "2000",
			"not_found_retention_days" => "90",
			"sos_telegram_bot_token" => "",
			"sos_telegram_chat_id" => "",
			"sos_slack_webhook_url" => "",
			"sos_monitor_urls" => "",
			"sos_check_sitemap_spam" => "0",
			"sos_quarantine" => "0",
			"sos_sitemap_inflation_alert" => "0",
			"sos_block_empty_user_agent" => "0",
			"sos_block_suspicious_user_agent" => "0",
			"sos_block_popular_crawlers" => "0",
		];
	}

	/**
	 * Recommended settings preset.
	 *
	 * @return array
	 */
	public static function recommended()
	{
		return wp_parse_args([
			"enable_metadata" => "1",
			"enable_schema" => "1",
			"enable_social" => "1",
			"enable_breadcrumbs" => "1",
			"enable_robots" => "1",
			"enable_sitemap" => "1",
			"enable_indexnow" => "0",
			"enable_content_shortcode" => "1",
			"enable_transliteration" => "1",
			"block_ai_training" => "1",
			"block_intrusive_bots" => "1",
			"auto_product_recommended" => "1",
			"noindex_search" => "1",
			"noindex_author_archives" => "1",
			"disable_author_archives" => "0",
			"noindex_date_archives" => "1",
			"noindex_tag_archives" => "0",
			"noindex_empty_archives" => "1",
			"noindex_paginated_pages" => "0",
			"disable_attachment_urls" => "1",
		], self::defaults());
	}

	/**
	 * Register option.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting(self::PAGE, self::OPTION, [
			"type" => "array",
			"default" => self::defaults(),
			"sanitize_callback" => [$this, "sanitize_options"],
		]);
	}

	/**
	 * Get all options.
	 *
	 * @return array
	 */
	public function all()
	{
		if (is_array($this->options_cache)) {
			return $this->options_cache;
		}

		$options = get_option(self::OPTION, []);

		if (!is_array($options)) {
			$options = [];
		}

		$options = $this->migrate_legacy_product_templates($options);
		$this->options_cache = $this->sanitize_options(wp_parse_args($options, self::defaults()));

		return $this->options_cache;
	}

	/**
	 * Get setting.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		$options = $this->all();

		if (array_key_exists($key, $options)) {
			return $options[$key];
		}

		return $default;
	}

	/**
	 * Sanitize options.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_options($input)
	{
		$defaults = self::defaults();
		$output = [];
		$stored = get_option(self::OPTION, []);
		if (!is_array($stored)) {
			$stored = [];
		}

		if (!is_array($input)) {
			$input = [];
		}

		$input = $this->replace_legacy_product_templates($input);

		foreach ($defaults as $key => $default) {
			$value = array_key_exists($key, $input)
				? $input[$key]
				: (array_key_exists($key, $stored) ? $stored[$key] : $default);

			if (in_array($default, ["0", "1"], true)) {
				$output[$key] = $value === "1" ? "1" : "0";
				continue;
			}

			if (in_array($key, ["product_titles", "product_descriptions"], true)) {
				$output[$key] = $this->sanitize_language_templates($value);
				continue;
			}

			if (in_array($key, ["organization_logo", "global_social_image", "sos_slack_webhook_url"], true)) {
				$output[$key] = esc_url_raw($value);
				continue;
			}

			if (in_array($key, ["local_address", "same_as", "sos_monitor_urls"], true)) {
				$output[$key] = sanitize_textarea_field($this->clean_broken_value($value));
				continue;
			}

			if (in_array($key, ["not_found_max_entries", "not_found_retention_days"], true)) {
				$output[$key] = (string) max(1, absint($value));
				continue;
			}

			if ($key === "local_business_type") {
				$allowed_types = [
					"LocalBusiness",
					"Store",
					"Restaurant",
					"ProfessionalService",
					"MedicalBusiness",
					"LegalService",
					"RealEstateAgent",
					"AutomotiveBusiness",
					"HealthAndBeautyBusiness",
					"HomeAndConstructionBusiness",
				];
				$output[$key] = in_array($value, $allowed_types, true)
					? $value
					: "LocalBusiness";
				continue;
			}

			$output[$key] = sanitize_text_field($this->clean_broken_value($value));
		}

		return $output;
	}

	/**
	 * Product SEO template for the requested or current language.
	 *
	 * @param string      $key Product template key.
	 * @param string|null $language Language code.
	 * @return string
	 */
	public function product_template($key, $language = null)
	{
		$key = $key === "product_description" ? "product_description" : "product_title";
		$language_key = $key === "product_description" ? "product_descriptions" : "product_titles";
		$language = $language ? sanitize_key($language) : $this->current_language();
		$templates = $this->get($language_key, []);

		if ($language && is_array($templates) && !empty($templates[$language])) {
			return (string) $templates[$language];
		}

		return (string) $this->get($key, self::defaults()[$key]);
	}

	/**
	 * Product SEO template for a post language.
	 *
	 * @param string $key Product template key.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function product_template_for_post($key, $post_id)
	{
		return $this->product_template($key, $this->post_language($post_id));
	}

	/**
	 * Languages from MyelophOne Locales when it is active.
	 *
	 * @return array
	 */
	public function product_template_languages()
	{
		if (!class_exists("MephLocales_Settings")) {
			return [];
		}

		$settings = new MephLocales_Settings();
		$languages = $settings->languages();

		return is_array($languages) ? $languages : [];
	}

	/**
	 * Whether language-specific product templates should be shown.
	 *
	 * @return bool
	 */
	public function has_product_template_languages()
	{
		return !empty($this->product_template_languages());
	}

	/**
	 * Expand preset product templates for active locales in the admin UI.
	 *
	 * @param array $settings Settings preset.
	 * @return array
	 */
	public function localize_product_template_preset($settings)
	{
		$languages = $this->product_template_languages();
		if (empty($languages)) {
			return $settings;
		}

		$title = isset($settings["product_title"]) ? (string) $settings["product_title"] : "";
		$description = isset($settings["product_description"]) ? (string) $settings["product_description"] : "";
		$settings["product_titles"] = [];
		$settings["product_descriptions"] = [];

		foreach (array_keys($languages) as $language) {
			$settings["product_titles"][$language] = $title;
			$settings["product_descriptions"][$language] = $description;
		}

		return $settings;
	}

	/**
	 * Replace old shipped product template defaults in saved options.
	 *
	 * Only exact legacy defaults are migrated, so custom user templates remain intact.
	 *
	 * @param array $options Stored options.
	 * @return array
	 */
	private function migrate_legacy_product_templates($options)
	{
		$migrated = $this->replace_legacy_product_templates($options);

		if ($migrated !== $options) {
			update_option(self::OPTION, $migrated, false);
		}

		return $migrated;
	}

	/**
	 * Replace exact legacy product template values.
	 *
	 * @param array $options Options.
	 * @return array
	 */
	private function replace_legacy_product_templates($options)
	{
		$defaults = self::defaults();

		if (
			isset($options["product_title"]) &&
			in_array(trim((string) $options["product_title"]), [self::LEGACY_PRODUCT_TITLE, self::LEGACY_ARCHIVE_TITLE], true)
		) {
			$options["product_title"] = $defaults["product_title"];
		}

		if (
			isset($options["product_description"]) &&
			in_array(trim((string) $options["product_description"]), [self::LEGACY_PRODUCT_DESCRIPTION, self::LEGACY_ARCHIVE_DESCRIPTION, self::PREVIOUS_PRODUCT_DESCRIPTION, self::PREVIOUS_PRODUCT_DESCRIPTION_2], true)
		) {
			$options["product_description"] = $defaults["product_description"];
		}

		if (isset($options["archive_title"]) && trim((string) $options["archive_title"]) === self::LEGACY_ARCHIVE_TITLE) {
			$options["archive_title"] = $defaults["archive_title"];
		}

		if (isset($options["archive_description"]) && trim((string) $options["archive_description"]) === self::LEGACY_ARCHIVE_DESCRIPTION) {
			$options["archive_description"] = $defaults["archive_description"];
		}

		return $options;
	}

	/**
	 * Sanitize a map of language-specific product templates.
	 *
	 * @param mixed $templates Raw templates.
	 * @return array
	 */
	private function sanitize_language_templates($templates)
	{
		if (!is_array($templates)) {
			return [];
		}

		$output = [];
		foreach ($templates as $language => $template) {
			$language = sanitize_key($language);
			if ($language === "") {
				continue;
			}

			$output[$language] = sanitize_text_field($this->clean_broken_value($template));
		}

		return $output;
	}

	/**
	 * Current language from MyelophOne Locales.
	 *
	 * @return string
	 */
	private function current_language()
	{
		if (function_exists("meph_current_language")) {
			return sanitize_key((string) meph_current_language());
		}

		return "";
	}

	/**
	 * Language assigned to a post by MyelophOne Locales.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function post_language($post_id)
	{
		if (!class_exists("MephLocales_Settings")) {
			return $this->current_language();
		}

		$settings = new MephLocales_Settings();
		$language = sanitize_key((string) get_post_meta($post_id, MephLocales_Settings::META_LANGUAGE, true));

		return $settings->is_language($language) ? $language : $this->current_language();
	}

	/**
	 * Remove accidental fatal-error fragments from saved settings.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function clean_broken_value($value)
	{
		$value = is_scalar($value) ? (string) $value : "";
		$value = preg_replace('/<br\s*\/?>.*$/is', "", $value);
		$value = preg_replace('/Fatal error.*$/is', "", $value);

		return $value;
	}

	/**
	 * Check if enabled.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function enabled($key)
	{
		return $this->get($key, "0") === "1";
	}
}
