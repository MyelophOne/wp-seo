<?php
/**
 * JSON-LD schema graph.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Schema
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
		add_action("wp_head", [$this, "render_schema"], 30);
	}

	/**
	 * Render schema.
	 *
	 * @return void
	 */
	public function render_schema()
	{
		if (!$this->settings->enabled("enable_schema") || is_404()) {
			return;
		}

		$graph = [];
		$home = home_url("/");
		$site_id = trailingslashit($home) . "#website";
		$org_id = trailingslashit($home) . "#organization";

		$graph[] = [
			"@type" => "Organization",
			"@id" => $org_id,
			"name" => $this->settings->get("organization_name") ?: get_bloginfo("name"),
			"url" => $home,
		];

		if ($this->settings->get("organization_logo")) {
			$graph[0]["logo"] = [
				"@type" => "ImageObject",
				"url" => $this->settings->get("organization_logo"),
			];
		}

		$same_as = $this->get_same_as();
		if ($same_as) {
			$graph[0]["sameAs"] = $same_as;
		}

		if ($this->settings->get("local_address") || $this->settings->get("local_phone")) {
			$graph[] = [
				"@type" => $this->settings->get("local_business_type", "LocalBusiness"),
				"@id" => trailingslashit($home) . "#localbusiness",
				"name" => $this->settings->get("organization_name") ?: get_bloginfo("name"),
				"url" => $home,
				"telephone" => $this->settings->get("local_phone"),
				"address" => $this->settings->get("local_address"),
			];
		}

		$graph[] = [
			"@type" => "WebSite",
			"@id" => $site_id,
			"url" => $home,
			"name" => get_bloginfo("name"),
			"publisher" => ["@id" => $org_id],
			"potentialAction" => [
				"@type" => "SearchAction",
				"target" => home_url("/?s={search_term_string}"),
				"query-input" => "required name=search_term_string",
			],
		];

		if (is_singular()) {
			$graph[] = $this->get_singular_schema($org_id);
		}

		if (is_singular("product") && function_exists("wc_get_product")) {
			$product_schema = $this->get_product_schema();
			if ($product_schema) {
				$graph[] = $product_schema;
			}
		}

		$breadcrumbs = $this->get_breadcrumb_schema();
		if ($breadcrumbs) {
			$graph[] = $breadcrumbs;
		}

		$video = $this->get_video_schema();
		if ($video) {
			$graph[] = $video;
		}

		$graph = apply_filters("myelophone_seo_schema_graph", array_values(array_filter($graph)));

		$data = [
			"@context" => "https://schema.org",
			"@graph" => $graph,
		];

		$data = apply_filters("myelophone_seo_schema_data", $data);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			esc_html(wp_json_encode($data)),
		);
	}

	/**
	 * Singular page schema.
	 *
	 * @param string $org_id Organization ID.
	 * @return array
	 */
	private function get_singular_schema($org_id)
	{
		$post_id = get_queried_object_id();
		$type = is_singular("post") ? "Article" : "WebPage";

		if (is_singular("post") && has_category("news", $post_id)) {
			$type = "NewsArticle";
		}

		$schema = [
			"@type" => $type,
			"@id" => get_permalink($post_id) . "#webpage",
			"url" => get_permalink($post_id),
			"name" => MephSeo_Variables::replace("%%title%%", $post_id),
			"headline" => MephSeo_Variables::replace("%%title%%", $post_id),
			"description" => MephSeo_Variables::replace("%%excerpt%%", $post_id),
			"datePublished" => get_the_date(DATE_W3C, $post_id),
			"dateModified" => get_the_modified_date(DATE_W3C, $post_id),
			"author" => [
				"@type" => "Person",
				"name" => get_the_author_meta("display_name", get_post_field("post_author", $post_id)),
			],
			"publisher" => ["@id" => $org_id],
		];

		return apply_filters("myelophone_seo_singular_schema", $schema, $post_id);
	}

	/**
	 * Product schema.
	 *
	 * @return array|null
	 */
	private function get_product_schema()
	{
		$product = wc_get_product(get_queried_object_id());
		if (!$product) {
			return null;
		}

		$data = [
			"@type" => "Product",
			"@id" => get_permalink() . "#product",
			"name" => $product->get_name(),
			"description" => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
			"sku" => $product->get_sku(),
			"url" => get_permalink(),
			"offers" => [
				"@type" => "Offer",
				"price" => $this->get_schema_price($product),
				"priceCurrency" => get_woocommerce_currency(),
				"priceSpecification" => $this->get_price_specification($product),
				"availability" => $product->is_in_stock()
					? "https://schema.org/InStock"
					: "https://schema.org/OutOfStock",
				"url" => get_permalink(),
			],
		];

		if ((float) $product->get_average_rating() > 0 && (int) $product->get_rating_count() > 0) {
			$data["aggregateRating"] = [
				"@type" => "AggregateRating",
				"ratingValue" => (string) $product->get_average_rating(),
				"reviewCount" => (string) $product->get_rating_count(),
			];
		}

		if (has_post_thumbnail()) {
			$image = wp_get_attachment_image_src(get_post_thumbnail_id(), "full");
			if ($image) {
				$data["image"] = $image[0];
			}
		}

		return apply_filters("myelophone_seo_product_schema", $data, $product);
	}

	/**
	 * Product schema price fallback.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_schema_price($product)
	{
		if ($product->is_type("variable")) {
			$price = $product->get_variation_price("min", true);
			return $price !== "" ? (string) $price : "";
		}

		if ($product->is_type("grouped")) {
			$prices = [];
			foreach ($product->get_children() as $child_id) {
				$child = wc_get_product($child_id);
				if ($child && $child->get_price() !== "") {
					$prices[] = (float) $child->get_price();
				}
			}
			return $prices ? (string) min($prices) : "";
		}

		return (string) $product->get_price();
	}

	/**
	 * PriceSpecification schema.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private function get_price_specification($product)
	{
		$spec = [
			"@type" => "PriceSpecification",
			"priceCurrency" => get_woocommerce_currency(),
		];

		if ($product->is_type("variable")) {
			$min = $product->get_variation_price("min", true);
			$max = $product->get_variation_price("max", true);
			if ($min !== "") {
				$spec["minPrice"] = (string) $min;
			}
			if ($max !== "") {
				$spec["maxPrice"] = (string) $max;
			}
			return $spec;
		}

		if ($product->is_type("grouped")) {
			$prices = [];
			foreach ($product->get_children() as $child_id) {
				$child = wc_get_product($child_id);
				if ($child && $child->get_price() !== "") {
					$prices[] = (float) $child->get_price();
				}
			}
			if ($prices) {
				$spec["minPrice"] = (string) min($prices);
				$spec["maxPrice"] = (string) max($prices);
			}
			return $spec;
		}

		$spec["price"] = (string) $product->get_price();
		return $spec;
	}

	/**
	 * Breadcrumb schema.
	 *
	 * @return array|null
	 */
	private function get_breadcrumb_schema()
	{
		if (!$this->settings->enabled("enable_breadcrumbs")) {
			return null;
		}

		$items = [
			[
				"@type" => "ListItem",
				"position" => 1,
				"name" => __("Home", "myelophone-seo"),
				"item" => home_url("/"),
			],
		];

		if (is_singular()) {
			$post = get_post();
			$position = 2;
			if ($post && $post->post_parent) {
				$ancestors = array_reverse(get_post_ancestors($post));
				foreach ($ancestors as $ancestor_id) {
					$items[] = [
						"@type" => "ListItem",
						"position" => $position++,
						"name" => get_the_title($ancestor_id),
						"item" => get_permalink($ancestor_id),
					];
				}
			}
			$items[] = [
				"@type" => "ListItem",
				"position" => $position,
				"name" => get_the_title(),
				"item" => get_permalink(),
			];
		} elseif (is_archive()) {
			$items[] = [
				"@type" => "ListItem",
				"position" => 2,
				"name" => wp_get_document_title(),
				"item" => get_pagenum_link(1),
			];
		}

		$schema = [
			"@type" => "BreadcrumbList",
			"@id" => home_url(add_query_arg([])) . "#breadcrumbs",
			"itemListElement" => $items,
		];

		return apply_filters("myelophone_seo_breadcrumb_schema", $schema, $items);
	}

	/**
	 * Detect simple embedded video schema.
	 *
	 * @return array|null
	 */
	private function get_video_schema()
	{
		if (!is_singular()) {
			return null;
		}

		$content = get_post_field("post_content", get_queried_object_id());
		if (!preg_match('/https?:\/\/(?:www\.)?(youtube\.com|youtu\.be|vimeo\.com)\/[^\s"\']+/i', $content, $match)) {
			return null;
		}

		$schema = [
			"@type" => "VideoObject",
			"name" => get_the_title(),
			"description" => MephSeo_Variables::replace("%%excerpt%%"),
			"thumbnailUrl" => has_post_thumbnail() ? get_the_post_thumbnail_url(null, "large") : "",
			"uploadDate" => get_the_date(DATE_W3C),
			"embedUrl" => esc_url_raw($match[0]),
		];

		return apply_filters("myelophone_seo_video_schema", $schema, get_queried_object_id());
	}

	/**
	 * SameAs URLs.
	 *
	 * @return array
	 */
	private function get_same_as()
	{
		$urls = preg_split('/\r\n|\r|\n/', (string) $this->settings->get("same_as", ""));
		$urls = array_filter(array_map("trim", $urls));
		$clean = [];

		foreach ($urls as $url) {
			$url = esc_url_raw($url);
			if ($url) {
				$clean[] = $url;
			}
		}

		return array_values(array_unique($clean));
	}
}
