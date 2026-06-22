<?php
/**
 * XML sitemap generator.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Sitemap
{
	const PER_PAGE = 1000;

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
		add_action("template_redirect", [$this, "maybe_render"], 0);
		add_filter("robots_txt", [$this, "add_sitemap_to_robots"], 30, 2);
	}

	/**
	 * Render sitemap index or sub-sitemap.
	 *
	 * @return void
	 */
	public function maybe_render()
	{
		if (!$this->settings->enabled("enable_sitemap")) {
			return;
		}

		$path = isset($_SERVER["REQUEST_URI"]) ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER["REQUEST_URI"])), PHP_URL_PATH) : "";
		$path = trim((string) $path, "/");

		if ($path === "sitemap.xml") {
			$this->render_index();
		}

		if (preg_match('/^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$/i', $path, $matches)) {
			$this->render_post_type($matches[1], max(1, absint($matches[2])));
		}
	}

	/**
	 * Add sitemap URL to robots output.
	 *
	 * @param string $output Robots.
	 * @param bool   $public Public flag.
	 * @return string
	 */
	public function add_sitemap_to_robots($output, $public)
	{
		if (!$this->settings->enabled("enable_sitemap") || !$public) {
			return $output;
		}

		if (strpos($output, home_url("/sitemap.xml")) !== false) {
			return $output;
		}

		return rtrim($output) . "\nSitemap: " . home_url("/sitemap.xml") . "\n";
	}

	/**
	 * Sitemap index.
	 *
	 * @return void
	 */
	private function render_index()
	{
		$items = [];
		foreach ($this->public_post_types() as $post_type) {
			$count = $this->count_indexable_posts($post_type);
			if ($count <= 0) {
				continue;
			}

			$pages = (int) ceil($count / self::PER_PAGE);
			for ($page = 1; $page <= $pages; $page++) {
				$items[] = [
					"loc" => home_url("/sitemap-" . $post_type . "-" . $page . ".xml"),
					"lastmod" => $this->latest_modified($post_type),
				];
			}
		}

		$this->send_xml_headers();
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
		foreach ($items as $item) {
			echo "\t<sitemap><loc>" . esc_url($item["loc"]) . "</loc>";
			if ($item["lastmod"]) {
				echo "<lastmod>" . esc_html($item["lastmod"]) . "</lastmod>";
			}
			echo "</sitemap>\n";
		}
		echo "</sitemapindex>";
		exit();
	}

	/**
	 * Post type sitemap.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page Page.
	 * @return void
	 */
	private function render_post_type($post_type, $page)
	{
		if (!in_array($post_type, $this->public_post_types(), true)) {
			status_header(404);
			exit();
		}

		$query = new WP_Query([
			"post_type" => $post_type,
			"post_status" => "publish",
			"posts_per_page" => self::PER_PAGE,
			"paged" => $page,
			"orderby" => "modified",
			"order" => "DESC",
			"fields" => "ids",
			"no_found_rows" => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to exclude per-entry noindex URLs from XML sitemaps.
			"meta_query" => $this->indexable_meta_query(),
		]);

		$this->send_xml_headers();
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
		foreach ($query->posts as $post_id) {
			echo "\t<url><loc>" . esc_url(get_permalink($post_id)) . "</loc>";
			echo "<lastmod>" . esc_html(get_post_modified_time(DATE_W3C, true, $post_id)) . "</lastmod></url>\n";
		}
		echo "</urlset>";
		exit();
	}

	/**
	 * Public post types for sitemap.
	 *
	 * @return array
	 */
	private function public_post_types()
	{
		$post_types = get_post_types(["public" => true], "names");
		$blocked = [
			"attachment",
			"elementor_library",
			"e-landing-page",
			"elementor_snippet",
		];
		foreach ($post_types as $post_type => $label) {
			if (in_array($post_type, $blocked, true) || strpos($post_type, "elementor") !== false) {
				unset($post_types[$post_type]);
			}
		}

		return array_values(apply_filters("myelophone_seo_sitemap_post_types", $post_types));
	}

	/**
	 * Count posts that may be included in sitemap.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function count_indexable_posts($post_type)
	{
		$query = new WP_Query([
			"post_type" => $post_type,
			"post_status" => "publish",
			"posts_per_page" => 1,
			"fields" => "ids",
			"no_found_rows" => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to count only sitemap-indexable entries.
			"meta_query" => $this->indexable_meta_query(),
		]);

		return (int) $query->found_posts;
	}

	/**
	 * Meta query excluding noindex pages.
	 *
	 * @return array
	 */
	private function indexable_meta_query()
	{
		return [
			"relation" => "OR",
			[
				"key" => "_meph_seo_noindex",
				"compare" => "NOT EXISTS",
			],
			[
				"key" => "_meph_seo_noindex",
				"value" => "1",
				"compare" => "!=",
			],
		];
	}

	/**
	 * Latest modified timestamp.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function latest_modified($post_type)
	{
		$latest = get_posts([
			"post_type" => $post_type,
			"post_status" => "publish",
			"posts_per_page" => 1,
			"orderby" => "modified",
			"order" => "DESC",
			"fields" => "ids",
			"no_found_rows" => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to exclude noindex entries from sitemap lastmod values.
			"meta_query" => $this->indexable_meta_query(),
		]);

		return $latest ? get_post_modified_time(DATE_W3C, true, $latest[0]) : "";
	}

	/**
	 * XML headers.
	 *
	 * @return void
	 */
	private function send_xml_headers()
	{
		status_header(200);
		header("Content-Type: application/xml; charset=UTF-8");
	}
}
