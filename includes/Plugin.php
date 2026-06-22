<?php
/**
 * Main plugin class.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Plugin
{
	/**
	 * Instance.
	 *
	 * @var MephSeo_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var MephSeo_Settings
	 */
	private $settings;

	/**
	 * Get singleton.
	 *
	 * @return MephSeo_Plugin
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init()
	{
		$this->settings = new MephSeo_Settings();

		new MephSeo_Admin_Page($this->settings);
		new MephSeo_MetaBox($this->settings);
		new MephSeo_Frontend($this->settings);
		new MephSeo_Schema($this->settings);
		new MephSeo_Redirects($this->settings);
		new MephSeo_Robots($this->settings);
		new MephSeo_Sitemap($this->settings);
		new MephSeo_Transliterator($this->settings);
		new MephSeo_Elementor($this->settings);
		new MephSeo_SOS($this->settings);
		new MephSeo_Misc($this->settings);

		add_action("admin_init", [$this->settings, "register_settings"]);
		add_action("admin_enqueue_scripts", [$this, "enqueue_admin_assets"]);
		add_action("admin_notices", [$this, "yoast_conflict_notice"]);
		add_action("wp_dashboard_setup", [$this, "register_dashboard_widget"]);
		add_action(
			"myelophone_dashboard_widget_sections",
			[$this, "render_dashboard_widget_section"],
			absint(apply_filters("myelophone_seo_dashboard_widget_priority", 20)),
		);
		add_filter("plugin_action_links_" . MYELOPHONE_SEO_BASENAME, [
			$this,
			"add_plugin_action_links",
		]);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_admin_assets($hook)
	{
		global $post;

		$screen = function_exists("get_current_screen") ? get_current_screen() : null;
		$screen_id = $screen && isset($screen->id) ? (string) $screen->id : "";
		$page = sanitize_key((string) filter_input(INPUT_GET, "page", FILTER_UNSAFE_RAW));
		$is_seo_page =
			$page === MephSeo_Settings::PAGE ||
			strpos((string) $hook, MephSeo_Settings::PAGE) !== false ||
			strpos($screen_id, MephSeo_Settings::PAGE) !== false;
		$is_editor = in_array($hook, ["post.php", "post-new.php"], true);
		$is_dashboard = $hook === "index.php";
		$admin_action = sanitize_text_field((string) filter_input(INPUT_GET, "action", FILTER_UNSAFE_RAW));
		if ($admin_action === "elementor") {
			return;
		}

		if (!$is_seo_page && !$is_editor && !$is_dashboard) {
			return;
		}

		$style_dependencies = [];
		if (defined("MYELOPHONE_CORE_URL")) {
			wp_enqueue_style(
				"myelophone-core-admin-style",
				MYELOPHONE_CORE_URL . "assets/css/admin.css",
				[],
				defined("MYELOPHONE_CORE_VERSION") ? MYELOPHONE_CORE_VERSION : MYELOPHONE_SEO_VERSION,
				"all",
			);
			$style_dependencies[] = "myelophone-core-admin-style";
		}

		wp_enqueue_style(
			"myelophone-seo-admin-style",
			MYELOPHONE_SEO_URL . "assets/css/admin.css",
			$style_dependencies,
			$this->asset_version("assets/css/admin.css"),
			"all",
		);

		if ($is_dashboard) {
			return;
		}

		wp_enqueue_script(
			"myelophone-seo-admin-script",
			MYELOPHONE_SEO_URL . "assets/js/admin.js",
			["jquery"],
			$this->asset_version("assets/js/admin.js"),
			true,
		);

		wp_localize_script("myelophone-seo-admin-script", "mephSeoAdmin", [
			"titleLimit" => 60,
			"descriptionLimit" => 155,
			"siteName" => get_bloginfo("name"),
			"homeUrl" => home_url("/"),
			"variables" => $this->get_preview_variables($post),
			"defaultSettings" => $this->settings->localize_product_template_preset(MephSeo_Settings::defaults()),
			"recommendedSettings" => $this->settings->localize_product_template_preset(MephSeo_Settings::recommended()),
			"i18n" => [
				"enabled" => __("Enabled", "myelophone-seo"),
				"disabled" => __("Disabled", "myelophone-seo"),
				/* translators: %d: number of hidden profile links. */
				"moreProfiles" => __("+%d more", "myelophone-seo"),
			],
		]);

		wp_enqueue_media();
		wp_enqueue_style("dashicons");
	}

	/**
	 * Asset version.
	 *
	 * @param string $relative_path Asset path relative to plugin root.
	 * @return string
	 */
	private function asset_version($relative_path)
	{
		$path = MYELOPHONE_SEO_DIR . ltrim($relative_path, "/\\");

		return file_exists($path) ? (string) filemtime($path) : MYELOPHONE_SEO_VERSION;
	}

	/**
	 * Variables for live admin previews.
	 *
	 * @param WP_Post|null $post Current post.
	 * @return array
	 */
	private function get_preview_variables($post)
	{
		$title = $post instanceof WP_Post ? get_the_title($post) : get_bloginfo("name");
		$title = $title ?: get_bloginfo("name");

		$variables = [
			"%%title%%" => $title,
			"%%sitename%%" => get_bloginfo("name"),
			"%%tagline%%" => get_bloginfo("description"),
			"%%sep%%" => (new MephSeo_Settings())->get("separator", "-"),
			"%%excerpt%%" => $post instanceof WP_Post ? $this->get_preview_excerpt($post) : get_bloginfo("description"),
			"%%year%%" => date_i18n("Y"),
		];

		if (function_exists("wc_get_product")) {
			$variables["%%product_name%%"] = $title;
			$variables["%%brand%%"] = "";
			$variables["%%price%%"] = "";
			$variables["%%price_min%%"] = "";
			$variables["%%sku%%"] = "";
			$variables["%%stock%%"] = "";
			$variables["%%category%%"] = "";
			$variables["%%primary_category%%"] = "";
			$variables["%%wc_shortdesc%%"] = "";

			if ($post instanceof WP_Post && $post->post_type === "product") {
				$product = wc_get_product($post->ID);
				if ($product) {
					$variables["%%product_name%%"] = $product->get_name();
					$variables["%%brand%%"] = $this->get_product_brand_for_preview($post->ID);
					$variables["%%price%%"] = $this->normalize_preview_text($product->get_price_html());
					$variables["%%price_min%%"] = $this->get_product_min_price_for_preview($product);
					$variables["%%sku%%"] = $product->get_sku();
					$variables["%%stock%%"] = $product->is_in_stock() ? __("In stock", "myelophone-seo") : __("Out of stock", "myelophone-seo");
					$variables["%%category%%"] = $this->get_first_term_name_for_preview($post->ID, "product_cat");
					$variables["%%primary_category%%"] = $variables["%%category%%"];
					$variables["%%wc_shortdesc%%"] = wp_trim_words($this->normalize_preview_text($product->get_short_description()), 28);
				}
			}
		}

		return $variables;
	}

	/**
	 * Product brand for admin preview.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_product_brand_for_preview($post_id)
	{
		foreach (["product_brand", "pa_brand", "pwb-brand"] as $taxonomy) {
			if (!taxonomy_exists($taxonomy)) {
				continue;
			}

			$brand = $this->get_first_term_name_for_preview($post_id, $taxonomy);
			if ($brand) {
				return $brand;
			}
		}

		return "";
	}

	/**
	 * Excerpt value for live admin previews.
	 *
	 * @param WP_Post $post Current post.
	 * @return string
	 */
	private function get_preview_excerpt($post)
	{
		$seo_excerpt = get_post_meta($post->ID, "_meph_seo_excerpt", true);
		$value = $seo_excerpt !== "" ? $seo_excerpt : ($post->post_excerpt ?: $post->post_content);

		return wp_trim_words(wp_strip_all_tags($value), 28);
	}

	/**
	 * First term name for admin preview.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private function get_first_term_name_for_preview($post_id, $taxonomy)
	{
		$terms = get_the_terms($post_id, $taxonomy);
		if (empty($terms) || is_wp_error($terms)) {
			return "";
		}

		return $terms[0]->name;
	}

	/**
	 * Minimum product price for live admin previews.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_product_min_price_for_preview($product)
	{
		if (!$product || !method_exists($product, "get_type")) {
			return "";
		}

		if ($product->is_type("variable")) {
			$min = $product->get_variation_price("min", true);
			if ($min !== "") {
				return $this->normalize_preview_text(wc_price($min));
			}
		}

		if ($product->is_type("grouped")) {
			$children = $product->get_children();
			$prices = [];
			foreach ($children as $child_id) {
				$child = wc_get_product($child_id);
				if ($child && $child->get_price() !== "") {
					$prices[] = (float) $child->get_price();
				}
			}
			if ($prices) {
				return $this->normalize_preview_text(wc_price(min($prices)));
			}
		}

		$price = $product->get_price();
		return $price !== "" ? $this->normalize_preview_text(wc_price($price)) : "";
	}

	/**
	 * Normalize preview values into readable plain text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function normalize_preview_text($value)
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

	/**
	 * Notice when Yoast SEO is active.
	 *
	 * @return void
	 */
	public function yoast_conflict_notice()
	{
		if (!current_user_can("manage_options")) {
			return;
		}

		$screen = function_exists("get_current_screen") ? get_current_screen() : null;
		$screen_id = $screen && isset($screen->id) ? (string) $screen->id : "";
		if (strpos($screen_id, "myelophone_page_myelophone-seo") === false && $screen_id !== "plugins") {
			return;
		}

		if (!function_exists("is_plugin_active")) {
			require_once ABSPATH . "wp-admin/includes/plugin.php";
		}

		$yoast_active =
			is_plugin_active("wordpress-seo/wp-seo.php") ||
			is_plugin_active("wordpress-seo-premium/wp-seo-premium.php");

		if (!$yoast_active) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<?php echo esc_html__(
					"Yoast SEO is active. MyelophOne SEO can conflict with Yoast metadata, canonical URLs, schema, breadcrumbs, redirects, or robots rules. Disable duplicated features in one plugin.",
					"myelophone-seo",
				); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin action links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function add_plugin_action_links($links)
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url("admin.php?page=myelophone-seo")),
			esc_html__("SEO Settings", "myelophone-seo"),
		);

		array_unshift($links, $settings_link);

		return $links;
	}

	/**
	 * Register SEO dashboard widget on the main WordPress dashboard.
	 *
	 * @return void
	 */
	public function register_dashboard_widget()
	{
		if (!current_user_can("manage_options")) {
			return;
		}

		wp_add_dashboard_widget(
			"myelophone_dashboard_widget",
			__("MyelophOne", "myelophone-seo"),
			[$this, "render_dashboard_widget"],
		);
	}

	/**
	 * Render standalone SEO dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget()
	{
		echo '<div class="myelophone-dashboard-widget meph-seo-dashboard-widget">';
		do_action("myelophone_dashboard_widget_sections");
		echo "</div>";
	}

	/**
	 * Render SEO section for shared MyelophOne dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget_section()
	{
		$log = get_option(MephSeo_Settings::NOT_FOUND_OPTION, []);
		$count = is_array($log) ? count($log) : 0;
		$total_hits = 0;
		if (is_array($log)) {
			foreach ($log as $entry) {
				$total_hits += isset($entry["hits"]) ? absint($entry["hits"]) : 0;
			}
		}
		$next_run = wp_next_scheduled(MephSeo_SOS::CRON_HOOK);
		$cron_disabled = defined("DISABLE_WP_CRON") && DISABLE_WP_CRON;
		$monitor_urls = array_filter(array_map("trim", preg_split('/\r\n|\r|\n/', (string) $this->settings->get("sos_monitor_urls"))));
		$daily_bits = [];

		if ($next_run && !$cron_disabled) {
			$daily_bits[] = wp_date(get_option("date_format") . " " . get_option("time_format"), $next_run);
		} elseif ($cron_disabled) {
			$daily_bits[] = __("WordPress cron is disabled.", "myelophone-seo");
		} else {
			$daily_bits[] = __("Daily checks are not scheduled yet.", "myelophone-seo");
		}

		if (!empty($monitor_urls)) {
			/* translators: %d: number of URLs configured for daily monitoring. */
			$daily_bits[] = sprintf(_n("%d monitored URL", "%d monitored URLs", count($monitor_urls), "myelophone-seo"), count($monitor_urls));
		}

		if ($this->settings->enabled("sos_check_sitemap_spam") || $this->settings->enabled("sos_sitemap_inflation_alert")) {
			$daily_bits[] = __("sitemap checks enabled", "myelophone-seo");
		}
		?>
		<div class="meph-seo-dashboard-widget-section">
			<div class="meph-seo-widget-main">
				<div>
					<span class="meph-seo-widget-kicker"><?php echo esc_html__("SEO health", "myelophone-seo"); ?></span>
					<p class="meph-seo-widget-count">
						<strong><?php echo esc_html(number_format_i18n($count)); ?></strong>
						<span><?php echo esc_html__("404 URLs logged.", "myelophone-seo"); ?></span>
					</p>
					<p class="meph-seo-widget-meta">
						<?php
						/* translators: %s: total number of logged 404 hits. */
						echo esc_html(sprintf(__("Total hits: %s", "myelophone-seo"), number_format_i18n($total_hits)));
						?>
					</p>
					<?php if (!empty($daily_bits)): ?>
						<p class="meph-seo-widget-meta"><?php echo esc_html(implode(" · ", $daily_bits)); ?></p>
					<?php endif; ?>
				</div>
				<a class="button button-primary" href="<?php echo esc_url(admin_url("admin.php?page=myelophone-seo&tab=not-found")); ?>"><?php echo esc_html__("Open 404 Stats", "myelophone-seo"); ?></a>
			</div>
			<?php if ($this->settings->enabled("sos_quarantine")): ?>
				<div class="meph-seo-widget-alert meph-seo-widget-alert-danger">
					<strong><?php echo esc_html__("SEO quarantine mode", "myelophone-seo"); ?></strong>
					<span><?php echo esc_html__("Public pages return 503 until quarantine is disabled.", "myelophone-seo"); ?></span>
				</div>
			<?php endif; ?>
			<?php if ($this->settings->enabled("global_noindex") || get_option("blog_public") === "0"): ?>
				<div class="meph-seo-widget-alert meph-seo-widget-alert-warning">
					<strong><?php echo esc_html__("Global NoIndex", "myelophone-seo"); ?></strong>
					<span><?php echo esc_html__("Search engines are being asked not to index this site.", "myelophone-seo"); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
