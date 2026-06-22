<?php
/**
 * Frontend metadata.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Frontend
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
		add_action("wp", [$this, "maybe_disable_core_head_metadata"], 0);
		add_action("template_redirect", [$this, "maybe_redirect_thin_pages"], 0);
		add_action("wp_head", [$this, "render_head"], 1);
		add_filter("document_title_parts", [$this, "filter_document_title"], 20);
		add_filter("comments_open", [$this, "maybe_close_comments"], 20, 2);
		add_filter("pings_open", [$this, "maybe_close_comments"], 20, 2);
	}

	/**
	 * Redirect thin pages that should not exist as indexable URLs.
	 *
	 * @return void
	 */
	public function maybe_redirect_thin_pages()
	{
		if (is_admin()) {
			return;
		}

		if ($this->settings->enabled("disable_author_archives") && is_author()) {
			wp_safe_redirect(home_url("/"), 301);
			exit();
		}

		if ($this->settings->enabled("disable_attachment_urls") && is_attachment()) {
			$parent_id = (int) wp_get_post_parent_id(get_queried_object_id());
			$target = $parent_id ? get_permalink($parent_id) : wp_get_attachment_url(get_queried_object_id());
			if ($target) {
				wp_safe_redirect($target, 301);
				exit();
			}
		}
	}

	/**
	 * Prevent duplicated robots meta when MyelophOne SEO owns metadata output.
	 *
	 * @return void
	 */
	public function maybe_disable_core_head_metadata()
	{
		if (!$this->settings->enabled("enable_metadata") && !$this->settings->enabled("global_noindex")) {
			return;
		}

		if (!apply_filters("myelophone_seo_suppress_wp_robots_meta", true, $this->get_context())) {
			return;
		}

		remove_action("wp_head", "wp_robots", 1);
		remove_action("wp_head", "rel_canonical");
	}

	/**
	 * Filter document title.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_document_title($parts)
	{
		if (!$this->settings->enabled("enable_metadata")) {
			return $parts;
		}

		$title = $this->get_title();
		if ($title) {
			$parts = ["title" => $this->normalize_output_string($title)];
		}

		return $parts;
	}

	/**
	 * Render head tags.
	 *
	 * @return void
	 */
	public function render_head()
	{
		if (!$this->settings->enabled("enable_metadata")) {
			if ($this->settings->enabled("global_noindex")) {
				echo "\n<!-- MyelophOne SEO v" . esc_html(MYELOPHONE_SEO_VERSION) . " -->\n";
				printf('<meta name="robots" content="%s">' . "\n", esc_attr($this->normalize_output_string($this->get_robots())));
			}
			return;
		}

		$title = $this->get_title();
		$description = $this->get_description();
		$canonical = $this->get_canonical();
		$image = $this->get_image();
		$robots = $this->get_robots();
		$context = $this->get_context();
		$head_data = apply_filters("myelophone_seo_head_data", [
			"title" => $title,
			"description" => $description,
			"canonical" => $canonical,
			"image" => $image,
			"robots" => $robots,
		], $context);
		$title = isset($head_data["title"]) ? $head_data["title"] : $title;
		$description = isset($head_data["description"]) ? $head_data["description"] : $description;
		$canonical = isset($head_data["canonical"]) ? $head_data["canonical"] : $canonical;
		$image = isset($head_data["image"]) ? $head_data["image"] : $image;
		$robots = isset($head_data["robots"]) ? $head_data["robots"] : $robots;
		$title = $this->normalize_output_string($title);
		$description = $this->normalize_output_string($description);
		$robots = $this->normalize_output_string($robots);

		echo "\n<!-- MyelophOne SEO v" . esc_html(MYELOPHONE_SEO_VERSION) . " -->\n";

		if ($description) {
			printf(
				'<meta name="description" content="%s">' . "\n",
				esc_attr($description),
			);
		}

		if ($robots) {
			printf('<meta name="robots" content="%s">' . "\n", esc_attr($robots));
		}

		if ($canonical) {
			printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
		}

		if ($this->settings->enabled("enable_social")) {
			$social_title_meta = $this->get_meta("_meph_seo_social_title");
			$social_description_meta = $this->get_meta("_meph_seo_social_description");
			$social_title = $social_title_meta ? MephSeo_Variables::replace($social_title_meta) : $title;
			$social_description = $social_description_meta ? MephSeo_Variables::replace($social_description_meta) : $description;
			$social_title = $social_title ?: $title;
			$social_description = $social_description ?: $description;
			$social_image = $this->get_meta("_meph_seo_social_image") ?: $image;
			$og_type = $this->get_og_type();
			$social_title = apply_filters("myelophone_seo_social_title", $social_title, $context);
			$social_description = apply_filters("myelophone_seo_social_description", $social_description, $context);
			$social_image = apply_filters("myelophone_seo_social_image", $social_image, $context);
			$og_type = apply_filters("myelophone_seo_og_type", $og_type, $context);
			$twitter_card = apply_filters("myelophone_seo_twitter_card", "summary_large_image", $context);
			$social_title = $this->normalize_output_string($social_title);
			$social_description = $this->normalize_output_string($social_description);
			$og_type = $this->normalize_output_string($og_type);
			$twitter_card = $this->normalize_output_string($twitter_card);

			printf('<meta property="og:type" content="%s">' . "\n", esc_attr($og_type));
			printf('<meta property="og:title" content="%s">' . "\n", esc_attr($social_title));
			if ($social_description) {
				printf('<meta property="og:description" content="%s">' . "\n", esc_attr($social_description));
			}
			printf('<meta property="og:url" content="%s">' . "\n", esc_url($canonical ?: home_url(add_query_arg([]))));
			printf('<meta property="og:site_name" content="%s">' . "\n", esc_attr(get_bloginfo("name")));
			if ($social_image) {
				printf('<meta property="og:image" content="%s">' . "\n", esc_url($social_image));
			}
			printf('<meta name="twitter:card" content="%s">' . "\n", esc_attr($twitter_card));
			printf('<meta name="twitter:title" content="%s">' . "\n", esc_attr($social_title));
			if ($social_description) {
				printf('<meta name="twitter:description" content="%s">' . "\n", esc_attr($social_description));
			}
			if ($social_image) {
				printf('<meta name="twitter:image" content="%s">' . "\n", esc_url($social_image));
			}
		}
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title()
	{
		$context = $this->get_context();
		$custom = $this->get_meta("_meph_seo_title");
		if ($custom) {
			return apply_filters("myelophone_seo_title", MephSeo_Variables::replace($custom), $context);
		}

		if (is_singular("product")) {
			return apply_filters("myelophone_seo_title", MephSeo_Variables::replace($this->settings->product_template("product_title")), $context);
		}

		if (is_archive()) {
			return apply_filters("myelophone_seo_title", MephSeo_Variables::replace($this->settings->get("archive_title")), $context);
		}

		return apply_filters("myelophone_seo_title", MephSeo_Variables::replace($this->settings->get("default_title")), $context);
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function get_description()
	{
		$context = $this->get_context();
		$custom = $this->get_meta("_meph_seo_description");
		if ($custom) {
			return apply_filters("myelophone_seo_description", MephSeo_Variables::replace($custom), $context);
		}

		if (is_singular("product")) {
			return apply_filters("myelophone_seo_description", MephSeo_Variables::replace($this->settings->product_template("product_description")), $context);
		}

		if (is_archive()) {
			return apply_filters("myelophone_seo_description", MephSeo_Variables::replace($this->settings->get("archive_description")), $context);
		}

		return apply_filters("myelophone_seo_description", MephSeo_Variables::replace($this->settings->get("default_description")), $context);
	}

	/**
	 * Canonical URL.
	 *
	 * @return string
	 */
	private function get_canonical()
	{
		$canonical = "";
		if (is_singular()) {
			$canonical = get_permalink();
		} elseif (is_home() || is_front_page()) {
			$canonical = home_url("/");
		} elseif (is_archive() || is_search()) {
			$canonical = get_pagenum_link(max(1, get_query_var("paged")));
		}

		$canonical = $this->clean_canonical_url($canonical);

		return apply_filters("myelophone_seo_canonical", $canonical, $this->get_context());
	}

	/**
	 * Remove tracking and duplicate-content parameters from canonical URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function clean_canonical_url($url)
	{
		if (!$url) {
			return "";
		}

		$parts = wp_parse_url($url);
		if (empty($parts["query"])) {
			return $url;
		}

		parse_str($parts["query"], $query);
		if (!$query) {
			return strtok($url, "?");
		}

		$clean_params = array_filter(array_map("trim", explode(",", $this->settings->get("robots_clean_params", ""))));
		$clean_params = array_merge($clean_params, [
			"replytocom",
			"utm_*",
			"fbclid",
			"gclid",
			"dclid",
			"gbraid",
			"wbraid",
			"yclid",
			"mc_cid",
			"mc_eid",
			"_ga",
			"_gl",
			"fb_action_ids",
			"fb_action_types",
			"fb_source",
			"ref",
			"ref_src",
			"spm",
		]);
		$clean_params = apply_filters("myelophone_seo_canonical_clean_params", array_unique($clean_params), $url, $this->get_context());

		foreach (array_keys($query) as $key) {
			if ($this->canonical_param_should_be_removed($key, $clean_params)) {
				unset($query[$key]);
			}
		}

		$base = strtok($url, "?");
		if (!$query) {
			return $base;
		}

		return add_query_arg($query, $base);
	}

	/**
	 * Check whether a query parameter should be stripped from canonical.
	 *
	 * @param string $key Query key.
	 * @param array  $clean_params Clean list.
	 * @return bool
	 */
	private function canonical_param_should_be_removed($key, $clean_params)
	{
		foreach ($clean_params as $param) {
			$param = trim((string) $param);
			if ($param === "") {
				continue;
			}

			if (substr($param, -1) === "*" && strpos($key, rtrim($param, "*")) === 0) {
				return true;
			}

			if (strcasecmp($key, $param) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Image URL.
	 *
	 * @return string
	 */
	private function get_image()
	{
		$image = "";
		$custom = $this->get_meta("_meph_seo_image");
		if ($custom) {
			$image = $custom;
			return apply_filters("myelophone_seo_image", $image, $this->get_context());
		}

		if (is_singular() && has_post_thumbnail()) {
			$thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id(), "full");
			if ($thumbnail) {
				$image = $thumbnail[0];
				return apply_filters("myelophone_seo_image", $image, $this->get_context());
			}
		}

		$global_image = $this->settings->get("global_social_image", "");
		if ($global_image) {
			$image = $global_image;
			return apply_filters("myelophone_seo_image", $image, $this->get_context());
		}

		$site_icon = get_site_icon_url(512);
		if ($site_icon) {
			$image = $site_icon;
			return apply_filters("myelophone_seo_image", $image, $this->get_context());
		}

		$image = $this->settings->get("organization_logo", "");
		return apply_filters("myelophone_seo_image", $image, $this->get_context());
	}

	/**
	 * Robots content.
	 *
	 * @return string
	 */
	private function get_robots()
	{
		$rules = [];
		$noindex = $this->settings->enabled("global_noindex") || $this->get_meta("_meph_seo_noindex") === "1" || get_option("blog_public") === "0";

		if (is_search() && $this->settings->enabled("noindex_search")) {
			$noindex = true;
		}
		if (is_author() && $this->settings->enabled("noindex_author_archives")) {
			$noindex = true;
		}
		if (is_date() && $this->settings->enabled("noindex_date_archives")) {
			$noindex = true;
		}
		if (is_tag() && $this->settings->enabled("noindex_tag_archives")) {
			$noindex = true;
		}
		if ((is_paged() || get_query_var("page") > 1) && $this->settings->enabled("noindex_paginated_pages")) {
			$noindex = true;
		}

		$rules[] = $noindex ? "noindex" : "index";
		$rules[] = "follow";
		$rules[] = "max-snippet:-1";
		$rules[] = "max-image-preview:large";
		$rules[] = "max-video-preview:-1";

		return apply_filters("myelophone_seo_robots", implode(", ", $rules), $rules, $this->get_context());
	}

	/**
	 * OG type.
	 *
	 * @return string
	 */
	private function get_og_type()
	{
		if (is_singular("product")) {
			return "product";
		}

		if (is_singular("post")) {
			return "article";
		}

		return "website";
	}

	/**
	 * Current SEO context.
	 *
	 * @return array
	 */
	private function get_context()
	{
		$post_id = is_singular() ? get_queried_object_id() : 0;

		$context = [
			"post_id" => $post_id,
			"post_type" => $post_id ? get_post_type($post_id) : "",
			"is_singular" => is_singular(),
			"is_archive" => is_archive(),
			"is_search" => is_search(),
			"is_front_page" => is_front_page(),
			"queried_object" => get_queried_object(),
		];

		return apply_filters("myelophone_seo_context", $context);
	}

	/**
	 * Current post meta.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function get_meta($key)
	{
		if (!is_singular()) {
			return "";
		}

		return (string) get_post_meta(get_queried_object_id(), $key, true);
	}

	/**
	 * Close comments per content entry.
	 *
	 * @param bool $open Current status.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function maybe_close_comments($open, $post_id)
	{
		if (get_post_meta($post_id, "_meph_seo_disable_comments", true) === "1") {
			return false;
		}

		return $open;
	}

	/**
	 * Normalize final text values before output.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function normalize_output_string($value)
	{
		$value = is_scalar($value) ? (string) $value : "";
		$value = wp_strip_all_tags($value);
		$value = preg_replace_callback('/&#(x?[0-9a-f]+);?/i', function ($matches) {
			return html_entity_decode("&#" . $matches[1] . ";", ENT_QUOTES | ENT_HTML5, "UTF-8");
		}, $value);
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, "UTF-8");
		$value = str_replace(["\xc2\xa0", "&nbsp;"], " ", $value);
		$value = str_replace(["–", "—", "−"], "-", $value);

		return trim(preg_replace('/\s+/u', " ", $value));
	}
}
