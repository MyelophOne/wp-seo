<?php
/**
 * Elementor integration.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Elementor
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
		add_action("elementor/init", [$this, "register_hooks"]);
		add_action("elementor/editor/after_enqueue_styles", [$this, "enqueue_editor_styles"]);
		add_action("updated_post_meta", [$this, "sync_elementor_page_settings_meta"], 10, 4);
		add_action("added_post_meta", [$this, "sync_elementor_page_settings_meta"], 10, 4);
	}

	/**
	 * Load only the small editor stylesheet required by Elementor controls.
	 *
	 * @return void
	 */
	public function enqueue_editor_styles()
	{
		wp_enqueue_style(
			"myelophone-seo-elementor-editor",
			MYELOPHONE_SEO_URL . "assets/css/elementor-editor.css",
			[],
			MYELOPHONE_SEO_VERSION,
			"all",
		);
	}

	/**
	 * Register Elementor hooks.
	 *
	 * @return void
	 */
	public function register_hooks()
	{
		add_action("elementor/elements/categories_registered", [$this, "register_category"], 0);
		add_filter("elementor/elements/categories", [$this, "filter_categories"], 0);
		add_action("elementor/widgets/register", [$this, "register_widgets"]);
		add_action("elementor/documents/register_controls", [$this, "register_document_controls"]);
	}

	/**
	 * Register MyelophOne category.
	 *
	 * @param object $elements_manager Elements manager.
	 * @return void
	 */
	public function register_category($elements_manager)
	{
		if (!is_object($elements_manager) || !method_exists($elements_manager, "add_category")) {
			return;
		}

		$elements_manager->add_category("myelophone", [
			"title" => __("MyelophOne", "myelophone-seo"),
			"icon" => "fa fa-plug",
		]);
	}

	/**
	 * Keep MyelophOne category first when Elementor exposes categories via filter.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public function filter_categories($categories)
	{
		if (!is_array($categories)) {
			return $categories;
		}

		$myelophone = [
			"myelophone" => [
				"title" => __("MyelophOne", "myelophone-seo"),
				"icon" => "fa fa-plug",
			],
		];

		if (isset($categories["myelophone"])) {
			unset($categories["myelophone"]);
		}

		return $myelophone + $categories;
	}

	/**
	 * Register widgets.
	 *
	 * @param object $widgets_manager Widgets manager.
	 * @return void
	 */
	public function register_widgets($widgets_manager)
	{
		if (!is_object($widgets_manager) || !class_exists("\Elementor\Widget_Base") || !method_exists($widgets_manager, "register")) {
			return;
		}

		$widgets_manager->register(new MephSeo_Elementor_Copyright_Widget());
	}

	/**
	 * Add SEO controls to Elementor post/page settings.
	 *
	 * @param object $document Elementor document.
	 * @return void
	 */
	public function register_document_controls($document)
	{
		if (
			!is_object($document) ||
			!class_exists("\Elementor\Controls_Manager") ||
			!method_exists($document, "start_controls_section") ||
			!method_exists($document, "add_control") ||
			!method_exists($document, "end_controls_section")
		) {
			return;
		}

		$post_id = method_exists($document, "get_main_id") ? absint($document->get_main_id()) : 0;

		$document->start_controls_section("meph_seo_section", [
			"label" => __("MyelophOne SEO", "myelophone-seo"),
			"tab" => \Elementor\Controls_Manager::TAB_SETTINGS,
		]);

		$document->add_control("meph_seo_note", [
			"type" => \Elementor\Controls_Manager::RAW_HTML,
			"raw" => $this->get_variable_help_html(),
		]);

		$document->add_control("meph_seo_title", [
			"label" => __("SEO title", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXTAREA,
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_title", true) : "",
			"description" => __("SEO-only title. It does not change Elementor page titles or heading widgets.", "myelophone-seo"),
		]);

		$document->add_control("meph_seo_excerpt", [
			"label" => __("SEO excerpt", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXTAREA,
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_excerpt", true) : "",
			"description" => __("Used by the %%excerpt%% variable before the WordPress excerpt or page content fallback.", "myelophone-seo"),
		]);

		$document->add_control("meph_seo_description", [
			"label" => __("SEO description", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXTAREA,
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_description", true) : "",
			"description" => __("SEO-only meta description. Social description can override it for OpenGraph and Twitter tags.", "myelophone-seo"),
		]);

		$document->add_control("meph_seo_image", [
			"label" => __("Primary image", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::MEDIA,
			"default" => [
				"url" => $post_id ? get_post_meta($post_id, "_meph_seo_image", true) : "",
			],
			"description" => __("SEO image override. If empty, MyelophOne SEO can fall back to the featured image.", "myelophone-seo"),
		]);

		$document->add_control("meph_seo_social_title", [
			"label" => __("Social title", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXTAREA,
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_social_title", true) : "",
		]);

		$document->add_control("meph_seo_social_description", [
			"label" => __("Social description", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXTAREA,
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_social_description", true) : "",
		]);

		$document->add_control("meph_seo_social_image", [
			"label" => __("Social image", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::MEDIA,
			"default" => [
				"url" => $post_id ? get_post_meta($post_id, "_meph_seo_social_image", true) : "",
			],
			"description" => __("Social image override. If empty, social tags can fall back to the primary SEO image or featured image.", "myelophone-seo"),
		]);

		$document->add_control("meph_seo_noindex", [
			"label" => __("Noindex", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::SWITCHER,
			"return_value" => "1",
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_noindex", true) : "",
		]);

		$document->add_control("meph_seo_disable_comments", [
			"label" => __("Disable comments", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::SWITCHER,
			"return_value" => "1",
			"default" => $post_id ? get_post_meta($post_id, "_meph_seo_disable_comments", true) : "",
		]);

		$document->end_controls_section();
	}

	/**
	 * Variable help for Elementor controls.
	 *
	 * @return string
	 */
	private function get_variable_help_html()
	{
		$variables = [
			"%%title%%",
			"%%sitename%%",
			"%%excerpt%%",
			"%%sep%%",
		];

		if (function_exists("wc_get_product")) {
			$variables = array_merge($variables, [
				"%%product_name%%",
				"%%brand%%",
				"%%price%%",
				"%%price_min%%",
				"%%sku%%",
				"%%stock%%",
				"%%category%%",
				"%%primary_category%%",
				"%%wc_shortdesc%%",
			]);
		}

		$html = '<div class="meph-elementor-variable-help">';
		$html .= '<div class="meph-elementor-variable-title">' . esc_html__("Available variables", "myelophone-seo") . "</div>";
		$html .= '<div class="meph-elementor-variable-chips">';
		foreach ($variables as $variable) {
			$html .= "<code>" . esc_html($variable) . "</code>";
		}

		$html .= "</div>";
		$html .= '<div class="meph-elementor-variable-examples">';
		$html .= "<code>" . esc_html('[%%brand%% ? "(%%brand%%)" : ""]') . "</code>";
		$html .= "<code>" . esc_html('[%%price%% ?? ""]') . "</code>";
		$html .= "</div>";

		return $html . "</div>";
	}

	/**
	 * Backward-compatible Elementor save callback.
	 *
	 * Some Elementor callbacks pass a document object, while others pass a
	 * numeric post ID. Keep this method defensive so compatibility callbacks
	 * cannot break the editor.
	 *
	 * @param object|int $document Elementor document object or post ID.
	 * @param mixed      $data Optional save data.
	 * @return void
	 */
	public function sync_document_settings($document, $data = null)
	{
		$post_id = 0;
		$settings = [];

		if (is_object($document) && method_exists($document, "get_main_id") && method_exists($document, "get_settings")) {
			$post_id = absint($document->get_main_id());
			$settings = $document->get_settings();
		} elseif (is_numeric($document)) {
			$post_id = absint($document);
			if (is_array($data) && isset($data["settings"]) && is_array($data["settings"])) {
				$settings = $data["settings"];
			} else {
				$settings = get_post_meta($post_id, "_elementor_page_settings", true);
			}
		}

		if (!$post_id || !current_user_can("edit_post", $post_id) || !is_array($settings)) {
			return;
		}

		$this->sync_settings_to_post_meta($post_id, $settings);
	}

	/**
	 * Sync when Elementor saves _elementor_page_settings.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function sync_elementor_page_settings_meta($meta_id, $post_id, $meta_key, $meta_value)
	{
		if ($meta_key !== "_elementor_page_settings" || !is_array($meta_value)) {
			return;
		}

		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$this->sync_settings_to_post_meta($post_id, $meta_value);
	}

	/**
	 * Sync Elementor settings array into regular SEO post meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $settings Elementor settings.
	 * @return void
	 */
	private function sync_settings_to_post_meta($post_id, $settings)
	{
		$map = [
			"meph_seo_title" => "_meph_seo_title",
			"meph_seo_excerpt" => "_meph_seo_excerpt",
			"meph_seo_description" => "_meph_seo_description",
			"meph_seo_image" => "_meph_seo_image",
			"meph_seo_social_title" => "_meph_seo_social_title",
			"meph_seo_social_description" => "_meph_seo_social_description",
			"meph_seo_social_image" => "_meph_seo_social_image",
			"meph_seo_noindex" => "_meph_seo_noindex",
			"meph_seo_disable_comments" => "_meph_seo_disable_comments",
		];

		foreach ($map as $elementor_key => $meta_key) {
			if (!array_key_exists($elementor_key, $settings)) {
				continue;
			}

			if (in_array($elementor_key, ["meph_seo_image", "meph_seo_social_image"], true)) {
				$value = is_array($settings[$elementor_key]) && !empty($settings[$elementor_key]["url"])
					? esc_url_raw($settings[$elementor_key]["url"])
					: "";
			} elseif (in_array($elementor_key, ["meph_seo_excerpt", "meph_seo_description", "meph_seo_social_description"], true)) {
				$value = sanitize_textarea_field($settings[$elementor_key]);
			} else {
				$value = sanitize_text_field($settings[$elementor_key]);
			}

			if ($value !== "") {
				update_post_meta($post_id, $meta_key, $value);
			} else {
				delete_post_meta($post_id, $meta_key);
			}
		}
	}
}
