<?php
/**
 * Admin page.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Admin_Page
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
		add_action("admin_menu", [$this, "register_page"], 30);
		add_action("admin_post_meph_seo_apply_permalink", [$this, "apply_permalink_structure"]);
		add_action("admin_post_meph_seo_dismiss_sitemap_inflation", [$this, "dismiss_sitemap_inflation"]);
		add_action("admin_post_meph_seo_enable_indexing", [$this, "enable_site_indexing"]);
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_page()
	{
		add_submenu_page(
			"myelophone-core",
			__("MyelophOne SEO", "myelophone-seo"),
			__("SEO", "myelophone-seo"),
			"manage_options",
			MephSeo_Settings::PAGE,
			[$this, "render"],
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private function current_tab()
	{
		$tab = filter_input(INPUT_GET, "tab", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$tab = $tab ? htmlspecialchars_decode($tab, ENT_QUOTES) : "";

		if ($tab === "archives" || $tab === "robots" || $tab === "appearance") {
			return "settings";
		}

		if ($tab === "shopify") {
			return "integrations";
		}

		return array_key_exists($tab, $this->tabs()) ? $tab : "dashboard";
	}

	/**
	 * Tab URL.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	private function tab_url($tab)
	{
		return add_query_arg(
			[
				"page" => MephSeo_Settings::PAGE,
				"tab" => $tab,
			],
			admin_url("admin.php"),
		);
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public function render()
	{
		$tab = $this->current_tab();
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html__("MyelophOne SEO", "myelophone-seo"); ?>
				<span class="meph-version-badge">v<?php echo esc_html(MYELOPHONE_SEO_VERSION); ?></span>
			</h1>

			<?php $this->render_admin_notices(); ?>

			<nav class="meph-tabs">
				<ul class="meph-tabs-nav">
					<?php foreach ($this->tabs() as $key => $label): ?>
						<li>
							<a href="<?php echo esc_url($this->tab_url($key)); ?>" class="<?php echo $tab === $key ? "current" : ""; ?>">
								<?php echo esc_html($label); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="meph-tab-content">
				<?php
		if ($tab === "settings") {
			$this->render_settings();
		} elseif ($tab === "redirects") {
			$this->render_redirects();
		} elseif ($tab === "not-found") {
			$this->render_404();
		} elseif ($tab === "integrations") {
			$this->render_integrations();
		} elseif ($tab === "sos") {
			$this->render_sos();
		} else {
			$this->render_dashboard();
		}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	private function tabs()
	{
		return [
			"dashboard" => __("Dashboard", "myelophone-seo"),
			"settings" => __("Settings", "myelophone-seo"),
			"redirects" => __("Redirects", "myelophone-seo"),
			"not-found" => __("404 Stats", "myelophone-seo"),
			"integrations" => __("Integrations", "myelophone-seo"),
			"sos" => __("SOS", "myelophone-seo"),
		];
	}

	/**
	 * Render page notices.
	 *
	 * @return void
	 */
	private function render_admin_notices()
	{
		$settings_updated = filter_input(INPUT_GET, "settings-updated", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$updated = filter_input(INPUT_GET, "updated", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if ($settings_updated || $updated) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html__("MyelophOne SEO settings saved successfully.", "myelophone-seo"); ?></p>
			</div>
			<?php
		}

		if (get_option("blog_public") === "0") {
			?>
			<div class="notice notice-warning">
				<p>
					<?php echo esc_html__("Search engines are currently discouraged from indexing this site. Disable WordPress noindex to make the site visible to search engines.", "myelophone-seo"); ?>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url("admin-post.php?action=meph_seo_enable_indexing"), "meph_seo_enable_indexing")); ?>"><?php echo esc_html__("Allow search engines now", "myelophone-seo"); ?></a>
				</p>
			</div>
			<?php
		}

		if ($this->settings->enabled("global_noindex")) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php echo esc_html__("Global NoIndex is enabled in MyelophOne SEO. Search engines are being asked not to index this site.", "myelophone-seo"); ?>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url("admin-post.php?action=meph_seo_enable_indexing"), "meph_seo_enable_indexing")); ?>"><?php echo esc_html__("Allow search engines now", "myelophone-seo"); ?></a>
				</p>
			</div>
			<?php
		}

		if ($this->settings->enabled("sos_quarantine")) {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html__("SEO quarantine mode is enabled. Public pages return 503 until quarantine is disabled.", "myelophone-seo"); ?></p>
			</div>
			<?php
		}

		if (get_option("meph_seo_sos_inflation_notice") === "1") {
			?>
			<div class="notice notice-warning">
				<p>
					<?php echo esc_html__("Sitemap inflation alert: the sitemap URL count increased sharply during the last SOS check.", "myelophone-seo"); ?>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url("admin-post.php?action=meph_seo_dismiss_sitemap_inflation"), "meph_seo_dismiss_sitemap_inflation")); ?>"><?php echo esc_html__("Dismiss", "myelophone-seo"); ?></a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Dashboard.
	 *
	 * @return void
	 */
	private function render_dashboard()
	{
		$log = get_option(MephSeo_Settings::NOT_FOUND_OPTION, []);
		$redirects = get_option(MephSeo_Settings::REDIRECTS_OPTION, []);
		$variables_description = sprintf(
			/* translators: 1: title variable, 2: site name variable, 3: excerpt variable, 4: separator variable, 5: brand variable, 6: price variable, 7: primary category variable. */
			__('Use %1$s, %2$s, %3$s, %4$s, %5$s, %6$s, %7$s and more.', "myelophone-seo"),
			"<code>%%title%%</code>",
			"<code>%%sitename%%</code>",
			"<code>%%excerpt%%</code>",
			"<code>%%sep%%</code>",
			"<code>%%brand%%</code>",
			"<code>%%price%%</code>",
			"<code>%%primary_category%%</code>",
		);
		?>
		<div class="meph-seo-dashboard">
			<div class="meph-stats-grid">
				<?php $this->stat_card(__("Metadata", "myelophone-seo"), $this->settings->enabled("enable_metadata") ? __("On", "myelophone-seo") : __("Off", "myelophone-seo"), __("Titles, descriptions, canonical and robots meta.", "myelophone-seo")); ?>
				<?php $this->stat_card(__("Schema", "myelophone-seo"), $this->settings->enabled("enable_schema") ? __("On", "myelophone-seo") : __("Off", "myelophone-seo"), __("Unified JSON-LD graph for pages, products and breadcrumbs.", "myelophone-seo")); ?>
				<?php $this->stat_card(__("Redirects", "myelophone-seo"), number_format_i18n(is_array($redirects) ? count($redirects) : 0), __("Active redirect rules.", "myelophone-seo")); ?>
				<?php $this->stat_card(__("404 URLs", "myelophone-seo"), number_format_i18n(is_array($log) ? count($log) : 0), __("Logged missing URLs for redirect planning and cleanup.", "myelophone-seo")); ?>
			</div>

			<div class="meph-about-section">
				<h2 class="meph-about-title"><?php echo esc_html__("SEO Toolkit", "myelophone-seo"); ?></h2>
				<p class="meph-about-lead">
					<?php echo esc_html__("Manage metadata, schema, social previews, WooCommerce product SEO, canonical URLs, XML sitemaps, IndexNow, redirects, 404 monitoring, robots.txt rules, archive controls, URL transliteration, and SEO SOS tools: emergency 503 mode, sitemap spike alerts, suspicious root-file checks, monitored URLs, and request guards.", "myelophone-seo"); ?>
				</p>
				<div class="meph-about-grid meph-about-features-grid">
					<?php $this->feature("dashicons-tag", __("Recommended product snippets", "myelophone-seo"), __("Used automatically for WooCommerce products when a product does not have custom SEO fields. The default template keeps product name, brand, price and availability in a search-friendly order.", "myelophone-seo")); ?>
					<?php $this->feature("dashicons-editor-code", __("Variables", "myelophone-seo"), wp_kses_post($variables_description)); ?>
					<?php $this->feature("dashicons-controls-repeat", __("Elementor compatible", "myelophone-seo"), __("The metabox saves regular post meta and works alongside Elementor-edited content.", "myelophone-seo")); ?>
				</div>
			</div>

			<?php $this->donate_link(); ?>
		</div>
		<?php
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private function render_settings()
	{
		$this->open_form();
		$this->render_settings_actions();
		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Core SEO", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->switch_field("enable_metadata", __("Enable metadata output", "myelophone-seo"), __("Outputs title, meta description, robots, canonical, OpenGraph and Twitter tags.", "myelophone-seo"));
		$this->switch_field("enable_schema", __("Enable JSON-LD schema", "myelophone-seo"), __("Adds Organization, WebSite, WebPage, Article, Product, BreadcrumbList, LocalBusiness, VideoObject and NewsArticle where data exists.", "myelophone-seo"));
		$this->switch_field("enable_social", __("Enable social graph tags", "myelophone-seo"), __("Uses dedicated social fields when present, otherwise falls back to title, description and image.", "myelophone-seo"));
		$this->switch_field("enable_transliteration", __("Transliterate new URLs", "myelophone-seo"), __("Converts Polish, Cyrillic and accented letters in newly generated slugs to clean Latin URLs.", "myelophone-seo"));
		$this->text_field("separator", __("Title separator", "myelophone-seo"), "-", 0, false, __("Use it in editable SEO fields with the %%sep%% variable.", "myelophone-seo"));
		$this->switch_field("enable_breadcrumbs", __("Enable breadcrumb schema", "myelophone-seo"), __("Adds BreadcrumbList JSON-LD for pages and archives.", "myelophone-seo"));
		$this->render_verification_notice();
		$this->text_field("default_title", __("Default title fallback", "myelophone-seo"), "%%title%% %%sep%% %%sitename%%", 60, true);
		$this->textarea_field("default_description", __("Default description fallback", "myelophone-seo"), 155, true);
		if ($this->settings->has_product_template_languages()) {
			$this->localized_product_template_fields();
		} else {
			$this->text_field("product_title", __("Recommended product title", "myelophone-seo"), "Buy %%title%% %%brand%% online %%sep%% %%sitename%%", 60, true);
			$this->textarea_field("product_description", __("Recommended product description", "myelophone-seo"), 155, true);
		}
		echo '<div class="meph-seo-switch-stack">';
		$this->switch_field("auto_product_recommended", __("Auto-fill recommended product SEO", "myelophone-seo"), __("When a new WooCommerce product has empty SEO fields, prefill it with the recommended product title and description templates from Core SEO, if those templates are set.", "myelophone-seo"));
		$this->switch_field("global_noindex", __("Disable site indexing", "myelophone-seo"), __("Adds global noindex to MyelophOne SEO robots meta output. Use only for staging, private or temporarily hidden sites.", "myelophone-seo"));
		echo "</div>";
		$this->image_field("global_social_image", __("Global social image", "myelophone-seo"));
		echo "</div></div>";

		$this->render_organization_graph_section();

		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Robots and Archives", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->switch_field("enable_robots", __("Add optimized WordPress robots.txt rules", "myelophone-seo"), __("Adds the standard WordPress cleanup rules shown in the preview. Bot-specific rules below are controlled separately.", "myelophone-seo"));
		$this->switch_field("block_intrusive_bots", __("Block aggressive SEO crawlers", "myelophone-seo"), __("Adds robots.txt rules for common high-volume SEO crawlers.", "myelophone-seo"));
		$this->switch_field("block_ai_training", __("Opt out of AI training use", "myelophone-seo"), __("Adds robots.txt rules for AI training crawlers while still allowing AI assistants to fetch public information when they identify themselves normally.", "myelophone-seo"));
		$this->switch_field("enable_sitemap", __("Generate XML sitemap", "myelophone-seo"), __("Generates a sitemap index with sub-sitemaps for public post types, including custom post types.", "myelophone-seo"));
		$this->textarea_field("robots_clean_params", __("Robots clean-param list", "myelophone-seo"));
		echo '<div class="meph-seo-full-row"><pre class="meph-seo-code" data-robots-preview>' . esc_html($this->robots_preview()) . "</pre></div>";
		$this->text_field("archive_title", __("Archive title fallback", "myelophone-seo"), "", 60, true);
		$this->textarea_field("archive_description", __("Archive description fallback", "myelophone-seo"), 155, true);
		$this->switch_field("noindex_search", __("Noindex internal search pages", "myelophone-seo"), __("Recommended for thin internal search result pages.", "myelophone-seo"));
		$this->switch_field("noindex_author_archives", __("Noindex author archives", "myelophone-seo"), __("Useful for single-author sites or duplicate archive pages.", "myelophone-seo"));
		$this->switch_field("disable_author_archives", __("Disable author archives", "myelophone-seo"), __("Redirect author archive URLs to the homepage to avoid thin duplicate author pages.", "myelophone-seo"));
		$this->switch_field("noindex_date_archives", __("Noindex date archives", "myelophone-seo"), __("Usually recommended because date archives often duplicate category and post content.", "myelophone-seo"));
		$this->switch_field("noindex_tag_archives", __("Noindex tag archives", "myelophone-seo"), __("Enable when tag pages are thin, duplicated, or not curated.", "myelophone-seo"));
		$this->switch_field("noindex_paginated_pages", __("Noindex paginated pages", "myelophone-seo"), __("Apply noindex to page 2 and later for archives and paginated content when you do not want paginated URLs indexed.", "myelophone-seo"));
		$this->switch_field("disable_attachment_urls", __("Disable attachment URLs", "myelophone-seo"), __("Redirect media attachment pages to their file URL or parent content to prevent thin media pages from being indexed.", "myelophone-seo"));
		echo "</div></div>";

		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Maintenance", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->text_field("not_found_max_entries", __("404 log max entries", "myelophone-seo"), "2000");
		$this->text_field("not_found_retention_days", __("404 log retention days", "myelophone-seo"), "90");
		echo "</div></div>";
		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Misc", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->switch_field("enable_content_shortcode", __("Enable content include shortcode", "myelophone-seo"), __("Adds [myelophone_content id=\"123\"] and [meph_content id=\"123\"] to insert another post, page, or custom post content. Pages that only reuse content should usually be noindex.", "myelophone-seo"));
		$this->switch_field("enable_indexnow", __("Enable IndexNow", "myelophone-seo"), __("Automatically pings IndexNow-compatible search engines when public content is published or updated. The key is generated automatically.", "myelophone-seo"));
		echo '<div class="meph-seo-link-row"><span class="meph-text-label">' . esc_html__("Recommended permalink structure", "myelophone-seo") . '</span><a class="meph-seo-inline-link" href="' . esc_url(wp_nonce_url(admin_url("admin-post.php?action=meph_seo_apply_permalink"), "meph_seo_apply_permalink")) . '">' . esc_html__("Apply /%postname%", "myelophone-seo") . "</a></div>";
		echo "</div></div>";
		$this->close_form();
	}

	private function render_settings_actions()
	{
		?>
		<div class="meph-settings-actions meph-seo-full-row">
			<button type="button" class="button button-secondary" id="meph-seo-recommended-settings">
				<?php echo esc_html__("Recommended Settings", "myelophone-seo"); ?>
			</button>
			<button type="button" class="button button-secondary" id="meph-seo-restore-defaults">
				<?php echo esc_html__("Restore Defaults", "myelophone-seo"); ?>
			</button>
			<p class="description">
				<?php echo esc_html__('To save changes, click the "Save Changes" button below.', "myelophone-seo"); ?>
			</p>
		</div>
		<?php
	}

	private function render_verification_notice()
	{
		$url = admin_url("admin.php?page=myelophone-core-info&tab=verification");
		?>
		<div class="meph-seo-link-row meph-seo-full-row">
			<span>
				<strong><?php echo esc_html__("Search Engine Verification", "myelophone-seo"); ?></strong>
				<span class="meph-seo-inline-description">
					<?php echo esc_html__("Add verification codes from search engines and social platforms in MyelophOne Core. Those meta tags are added to the head section.", "myelophone-seo"); ?>
				</span>
			</span>
			<a class="meph-seo-inline-link" href="<?php echo esc_url($url); ?>"><?php echo esc_html__("Open verification settings", "myelophone-seo"); ?></a>
		</div>
		<?php
	}

	private function render_sos()
	{
		$this->open_form();
		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("SOS notifications", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->text_field("sos_telegram_bot_token", __("Telegram bot token", "myelophone-seo"), "123456:ABC...");
		$this->text_field("sos_telegram_chat_id", __("Telegram chat ID", "myelophone-seo"), "-100...");
		$this->text_field("sos_slack_webhook_url", __("Slack webhook URL", "myelophone-seo"), "https://hooks.slack.com/...");
		echo "</div></div>";

		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Daily checks", "myelophone-seo");
		if (defined("DISABLE_WP_CRON") && DISABLE_WP_CRON) {
			echo ' <span class="meph-seo-heading-warning">' . esc_html__("WordPress cron is disabled", "myelophone-seo") . "</span>";
		}
		echo "</h2><div class=\"meph-seo-sos-daily-layout\">";
		$this->textarea_field("sos_monitor_urls", __("URLs to monitor daily", "myelophone-seo"), 0, false, __("Add one URL per line. MyelophOne SEO alerts when a URL does not return HTTP 200.", "myelophone-seo"));
		echo '<div class="meph-seo-sos-daily-switches">';
		$this->switch_field("sos_check_sitemap_spam", __("Check sitemap for spam links", "myelophone-seo"), __("Alerts when the sitemap contains obvious spam patterns or external suspicious links.", "myelophone-seo"));
		$this->switch_field("sos_sitemap_inflation_alert", __("Sitemap inflation alert", "myelophone-seo"), __("Alerts when sitemap URL count grows by more than 50% or by more than 100 URLs compared with the previous daily check.", "myelophone-seo"));
		$this->switch_field("sos_quarantine", __("SEO quarantine mode", "myelophone-seo"), __("Returns 503 for normal public pages while leaving 404 and other error responses untouched.", "myelophone-seo"));
		echo "</div></div></div>";

		echo '<div class="meph-seo-section meph-seo-full-row"><h2>' . esc_html__("Request guard", "myelophone-seo") . "</h2><div class=\"meph-settings-grid\">";
		$this->switch_field("sos_block_empty_user_agent", __("Block empty user agents", "myelophone-seo"), __("Blocks requests without a User-Agent header. Keep disabled if you expect legacy clients.", "myelophone-seo"));
		$this->switch_field("sos_block_suspicious_user_agent", __("Block suspicious non-browser user agents", "myelophone-seo"), __("Blocks obvious scripted or malformed agents while trying to avoid rare real browsers.", "myelophone-seo"));
		$this->switch_field("sos_block_popular_crawlers", __("Block popular crawlers", "myelophone-seo"), __("Blocks selected high-volume crawlers at request time. Use carefully.", "myelophone-seo"));
		echo "</div></div>";
		$this->close_form();
	}

	private function render_organization_graph_section()
	{
		$organization_name = $this->settings->get("organization_name") ?: get_bloginfo("name");
		$organization_logo = $this->settings->get("organization_logo");
		$local_business_type = $this->settings->get("local_business_type") ?: "LocalBusiness";
		$local_phone = $this->settings->get("local_phone");
		$same_as = array_filter(array_map("trim", preg_split('/\r\n|\r|\n/', (string) $this->settings->get("same_as"))));
		?>
		<div class="meph-seo-section meph-seo-full-row meph-seo-organization-section">
			<h2><?php echo esc_html__("Organization and Social Graph", "myelophone-seo"); ?></h2>
			<div class="meph-seo-organization-layout">
				<div class="meph-seo-organization-fields">
					<?php
					$this->text_field("organization_name", __("Organization name", "myelophone-seo"), get_bloginfo("name"));
					$this->image_field("organization_logo", __("Organization logo", "myelophone-seo"));
					$this->select_field("local_business_type", __("Local business schema type", "myelophone-seo"), $this->local_business_types());
					$this->text_field("local_phone", __("Local phone", "myelophone-seo"), "+48...");
					$this->textarea_field("local_address", __("Local address", "myelophone-seo"));
					$this->textarea_field("same_as", __("SameAs profile URLs", "myelophone-seo"), 0, false, __("Add one profile URL per line, for example your official social media, marketplace, directory, or organization pages.", "myelophone-seo"));
					?>
				</div>
				<div class="meph-seo-organization-preview" aria-label="<?php echo esc_attr__("Organization preview", "myelophone-seo"); ?>">
					<div class="meph-seo-org-preview-logo" data-image-preview-for="organization_logo" data-image-preview-url="<?php echo esc_url($organization_logo); ?>"></div>
					<div class="meph-seo-org-preview-body">
						<strong data-org-preview-name><?php echo esc_html($organization_name); ?></strong>
						<span data-org-preview-type><?php echo esc_html($local_business_type); ?></span>
						<?php if ($local_phone): ?>
							<span data-org-preview-phone><?php echo esc_html($local_phone); ?></span>
						<?php endif; ?>
						<div class="meph-seo-org-preview-links" data-org-preview-sameas>
							<?php foreach (array_slice($same_as, 0, 4) as $profile_url): ?>
								<span><?php echo esc_html(wp_parse_url($profile_url, PHP_URL_HOST) ?: $profile_url); ?></span>
							<?php endforeach; ?>
							<?php if (count($same_as) > 4): ?>
								<span><?php
								/* translators: %d: number of hidden SameAs profile links. */
								echo esc_html(sprintf(__("+%d more", "myelophone-seo"), count($same_as) - 4));
								?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirects tab.
	 *
	 * @return void
	 */
	private function render_redirects()
	{
		$redirects = get_option(MephSeo_Settings::REDIRECTS_OPTION, []);
		if (!is_array($redirects)) {
			$redirects = [];
		}
		$redirects[] = ["from" => "", "to" => "", "status" => 301];
		?>
		<form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>" class="meph-settings-form">
			<input type="hidden" name="action" value="meph_seo_save_redirects">
			<?php wp_nonce_field("meph_seo_save_redirects"); ?>
			<div class="meph-system-info meph-seo-redirect-table">
				<table>
					<thead><tr><th><?php echo esc_html__("Old URL", "myelophone-seo"); ?></th><th><?php echo esc_html__("New URL", "myelophone-seo"); ?></th><th><?php echo esc_html__("Status", "myelophone-seo"); ?></th><th><?php echo esc_html__("Actions", "myelophone-seo"); ?></th></tr></thead>
					<tbody>
					<?php foreach ($redirects as $redirect): ?>
						<tr>
							<td><input type="text" name="from[]" value="<?php echo esc_attr($redirect["from"]); ?>" placeholder="/old/*"></td>
							<td><input type="text" name="to[]" value="<?php echo esc_attr($redirect["to"]); ?>" placeholder="/new/*"></td>
							<td>
								<select name="status[]">
									<option value="301" <?php selected((int) $redirect["status"], 301); ?>>301</option>
									<option value="302" <?php selected((int) $redirect["status"], 302); ?>>302</option>
								</select>
							</td>
							<td>
								<button type="button" class="button meph-seo-remove-redirect">
									<?php echo esc_html__("Remove", "myelophone-seo"); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="description">
				<?php echo esc_html__("Remove deletes the row from this form. Click Save redirects to apply changes.", "myelophone-seo"); ?>
			</p>
			<?php submit_button(__("Save redirects", "myelophone-seo")); ?>
		</form>
		<?php
	}

	/**
	 * 404 tab.
	 *
	 * @return void
	 */
	private function render_404()
	{
		$log = get_option(MephSeo_Settings::NOT_FOUND_OPTION, []);
		if (!is_array($log)) {
			$log = [];
		}
		?>
		<div class="meph-system-info">
			<table>
				<thead><tr><th><?php echo esc_html__("URL", "myelophone-seo"); ?></th><th><?php echo esc_html__("Hits", "myelophone-seo"); ?></th><th><?php echo esc_html__("Last seen", "myelophone-seo"); ?></th><th><?php echo esc_html__("Referrer host", "myelophone-seo"); ?></th></tr></thead>
				<tbody>
				<?php if (empty($log)): ?>
					<tr><td colspan="4"><?php echo esc_html__("No 404 results logged yet.", "myelophone-seo"); ?></td></tr>
				<?php endif; ?>
				<?php foreach ($log as $entry): ?>
					<tr>
						<td><?php echo esc_html($entry["url"]); ?></td>
						<td><?php echo esc_html(number_format_i18n($entry["hits"])); ?></td>
						<td><?php echo esc_html($entry["last_seen"]); ?></td>
						<td><?php echo esc_html($entry["referrer_host"]); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
			<input type="hidden" name="action" value="meph_seo_clear_404">
			<?php wp_nonce_field("meph_seo_clear_404"); ?>
			<?php submit_button(__("Clear 404 log", "myelophone-seo"), "secondary"); ?>
		</form>
		<?php
	}

	/**
	 * Shopify tab.
	 *
	 * @return void
	 */
	private function render_integrations()
	{
		?>
		<div class="meph-about-section">
			<h2 class="meph-about-title"><?php echo esc_html__("Shopify", "myelophone-seo"); ?></h2>
			<p class="meph-about-lead"><?php echo esc_html__("Use these Shopify-native snippets as a ready integration pack: add the head snippet to theme.liquid and the robots rules to robots.txt.liquid. The Liquid code keeps canonical, descriptions and OpenGraph aligned with Shopify data instead of hard-coded WordPress values.", "myelophone-seo"); ?></p>
			<h3><?php echo esc_html__("theme.liquid head snippet", "myelophone-seo"); ?></h3>
			<pre class="meph-seo-code">{{ page_title }}{% if current_tags %} - {{ current_tags | join: ', ' }}{% endif %} - {{ shop.name }}
&lt;meta name="description" content="{{ page_description | default: shop.description | escape }}"&gt;
&lt;link rel="canonical" href="{{ canonical_url }}"&gt;
&lt;meta property="og:title" content="{{ page_title | escape }}"&gt;
&lt;meta property="og:description" content="{{ page_description | default: shop.description | escape }}"&gt;
&lt;meta property="og:url" content="{{ canonical_url }}"&gt;</pre>
			<h3><?php echo esc_html__("robots.txt.liquid additions", "myelophone-seo"); ?></h3>
			<pre class="meph-seo-code">User-agent: *
Disallow: /cart
Disallow: /checkout
Disallow: /orders
Disallow: /admin
Sitemap: {{ shop.url }}/sitemap.xml</pre>
		</div>
		<?php
	}

	private function open_form()
	{
		echo '<form method="post" action="options.php" class="meph-settings-form meph-seo-form">';
		settings_fields(MephSeo_Settings::PAGE);
		echo '<div class="meph-settings-grid">';
	}

	private function close_form()
	{
		echo '</div>';
		submit_button();
		echo '</form>';
	}

	private function switch_field($key, $title, $description)
	{
		$name = MephSeo_Settings::OPTION . "[" . $key . "]";
		$value = $this->settings->get($key, "0");
		?>
		<label class="meph-switch-label">
			<span class="meph-switch">
				<input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
				<input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, "1"); ?>>
				<span class="meph-slider"></span>
			</span>
			<span class="meph-switch-content">
				<span class="meph-switch-title">
					<span class="meph-switch-text"><?php echo esc_html($title); ?></span>
					<span class="meph-switch-status"><?php echo $value === "1" ? esc_html__("Enabled", "myelophone-seo") : esc_html__("Disabled", "myelophone-seo"); ?></span>
				</span>
				<span class="meph-switch-description"><?php echo esc_html($description); ?></span>
			</span>
		</label>
		<?php
	}

	private function text_field($key, $label, $placeholder = "", $limit = 0, $show_variables = false, $description = "")
	{
		$name = MephSeo_Settings::OPTION . "[" . $key . "]";
		$is_title_field = in_array($key, ["default_title", "product_title", "archive_title"], true);
		?>
		<div class="meph-verification-field">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			<?php if ($is_title_field): ?>
				<textarea id="<?php echo esc_attr($key); ?>" class="large-text meph-seo-counted meph-seo-title-input" rows="2" name="<?php echo esc_attr($name); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" data-limit="<?php echo esc_attr($limit); ?>"><?php echo esc_textarea($this->settings->get($key)); ?></textarea>
			<?php else: ?>
				<?php $input_type = in_array($key, ["sos_telegram_bot_token", "sos_slack_webhook_url"], true) ? "password" : "text"; ?>
				<input id="<?php echo esc_attr($key); ?>" class="regular-text meph-seo-counted" type="<?php echo esc_attr($input_type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($this->settings->get($key)); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" data-limit="<?php echo esc_attr($limit); ?>"<?php if ($input_type === "password"): ?> autocomplete="off"<?php endif; ?>>
			<?php endif; ?>
			<?php if ($description): ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
			<?php if ($limit): ?><p class="description"><span data-counter-for="<?php echo esc_attr($key); ?>">0</span> / <?php echo esc_html($limit); ?></p><?php endif; ?>
			<?php if ($show_variables) { $this->variable_buttons(); } ?>
		</div>
		<?php
	}

	private function textarea_field($key, $label, $limit = 0, $show_variables = false, $description = "")
	{
		$name = MephSeo_Settings::OPTION . "[" . $key . "]";
		?>
		<div class="meph-verification-field">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			<textarea id="<?php echo esc_attr($key); ?>" class="large-text meph-seo-counted" rows="4" name="<?php echo esc_attr($name); ?>" data-limit="<?php echo esc_attr($limit); ?>"><?php echo esc_textarea($this->settings->get($key)); ?></textarea>
			<?php if ($description): ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
			<?php if ($limit): ?><p class="description"><span data-counter-for="<?php echo esc_attr($key); ?>">0</span> / <?php echo esc_html($limit); ?></p><?php endif; ?>
			<?php if ($show_variables) { $this->variable_buttons(); } ?>
		</div>
		<?php
	}

	private function localized_product_template_fields()
	{
		$languages = $this->settings->product_template_languages();
		$active_language = (string) array_key_first($languages);
		?>
		<div class="meph-seo-localized-template-block meph-seo-full-row">
			<div class="meph-seo-localized-template-header">
				<label class="meph-text-label" for="meph-seo-product-template-language"><?php echo esc_html__("Recommended product templates language", "myelophone-seo"); ?></label>
				<select id="meph-seo-product-template-language" data-meph-template-language>
					<?php foreach ($languages as $language): ?>
						<option value="<?php echo esc_attr($language["code"]); ?>"><?php echo esc_html($language["label"]); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php foreach ($languages as $language): ?>
				<?php
				$code = $language["code"];
				?>
				<div class="meph-seo-localized-template-panel<?php echo $code === $active_language ? "" : " is-hidden"; ?>" data-meph-template-panel="<?php echo esc_attr($code); ?>">
					<?php
					$this->localized_product_template_field(
						"product_titles",
						$code,
						__("Recommended product title", "myelophone-seo"),
						$this->settings->product_template("product_title", $code),
						60,
						2,
						"Buy %%title%% %%brand%% online %%sep%% %%sitename%%",
					);
					$this->localized_product_template_field(
						"product_descriptions",
						$code,
						__("Recommended product description", "myelophone-seo"),
						$this->settings->product_template("product_description", $code),
						155,
						4,
					);
					?>
				</div>
			<?php endforeach; ?>

			<?php $this->variable_buttons(); ?>
		</div>
		<?php
	}

	private function localized_product_template_field($key, $language, $label, $value, $limit, $rows, $placeholder = "")
	{
		$id = $key . "_" . sanitize_key($language);
		$name = MephSeo_Settings::OPTION . "[" . $key . "][" . sanitize_key($language) . "]";
		?>
		<div class="meph-verification-field">
			<label class="meph-text-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
			<textarea id="<?php echo esc_attr($id); ?>" class="large-text meph-seo-counted<?php echo $key === "product_titles" ? " meph-seo-title-input" : ""; ?>" rows="<?php echo esc_attr($rows); ?>" name="<?php echo esc_attr($name); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" data-limit="<?php echo esc_attr($limit); ?>"><?php echo esc_textarea($value); ?></textarea>
			<p class="description"><span data-counter-for="<?php echo esc_attr($id); ?>">0</span> / <?php echo esc_html($limit); ?></p>
		</div>
		<?php
	}

	private function image_field($key, $label)
	{
		$name = MephSeo_Settings::OPTION . "[" . $key . "]";
		$value = $this->settings->get($key);
		$preview_class = "organization_logo" === $key ? " meph-seo-logo-preview" : "";
		?>
		<div class="meph-verification-field">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			<div class="meph-seo-media-row">
				<input id="<?php echo esc_attr($key); ?>" class="regular-text meph-seo-image-url" type="url" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
				<button type="button" class="button meph-seo-media-button" data-target="<?php echo esc_attr($key); ?>"><?php echo esc_html__("Select", "myelophone-seo"); ?></button>
			</div>
			<div class="meph-seo-image-preview<?php echo esc_attr($preview_class); ?>" data-image-preview-for="<?php echo esc_attr($key); ?>" data-image-preview-url="<?php echo esc_url($value); ?>"></div>
		</div>
		<?php
	}

	private function select_field($key, $label, $options)
	{
		$name = MephSeo_Settings::OPTION . "[" . $key . "]";
		$value = $this->settings->get($key);
		?>
		<div class="meph-verification-field">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			<select id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($name); ?>">
				<?php foreach ($options as $option => $option_label): ?>
					<option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>><?php echo esc_html($option_label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	private function stat_card($title, $value, $label)
	{
		?>
		<div class="meph-stat-card">
			<h3><?php echo esc_html($title); ?></h3>
			<div class="meph-stat-value"><?php echo esc_html($value); ?></div>
			<div class="meph-stat-label"><?php echo esc_html($label); ?></div>
		</div>
		<?php
	}

	private function feature($icon, $title, $description)
	{
		?>
		<div class="meph-about-card">
			<h3 class="meph-about-card-title"><span class="dashicons <?php echo esc_attr($icon); ?>"></span><?php echo esc_html($title); ?></h3>
			<p class="meph-about-card-desc"><?php echo wp_kses($description, ["code" => []]); ?></p>
		</div>
		<?php
	}

	private function donate_link()
	{
		$url = add_query_arg(
			[
				"utm_source" => wp_parse_url(home_url(), PHP_URL_HOST),
				"utm_medium" => "plugin",
				"utm_campaign" => "myelophone-seo",
			],
			"https://www.buymeacoffee.com/aleksivanou",
		);
		?>
		<p class="meph-about-footer-text meph-mt-1">
			<?php echo wp_kses_post(sprintf(
				/* translators: %s: linked author username. */
				__("If MyelophOne SEO saves you time, you can support the author %s.", "myelophone-seo"),
				'<a href="https://github.com/aleksivanou" target="_blank" rel="noopener">aleksivanou</a>',
			)); ?>
		</p>
		<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="meph-about-donate-btn">
			<img src="<?php echo esc_url(defined("MYELOPHONE_CORE_URL") ? MYELOPHONE_CORE_URL . "assets/img/default-yellow.png" : MYELOPHONE_SEO_URL . "assets/img/default-yellow.png"); ?>" alt="Buy Me a Coffee" class="meph-about-donate-img">
		</a>
		<?php
	}

	private function robots_preview()
	{
		return MephSeo_Robots::build_robots_txt($this->settings, true);
	}

	private function variable_buttons()
	{
		$variables = [
			"%%title%%",
			"%%sitename%%",
			"%%excerpt%%",
			"%%year%%",
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

		echo '<div class="meph-seo-variable-chips">';
		foreach ($variables as $variable) {
			printf(
				'<button type="button" class="button meph-seo-variable" data-variable="%1$s" title="%2$s"><code>%1$s</code></button>',
				esc_attr($variable),
				esc_attr__('[%%brand%% ? "(%%brand%%)" : ""] and [%%price%% ?? ""] are supported.', "myelophone-seo"),
			);
		}
		echo "</div>";
	}

	/**
	 * Apply recommended permalink structure.
	 *
	 * @return void
	 */
	public function apply_permalink_structure()
	{
		if (!current_user_can("manage_options")) {
			wp_die(esc_html__("Permission denied.", "myelophone-seo"));
		}

		check_admin_referer("meph_seo_apply_permalink");
		update_option("permalink_structure", "/%postname%");
		flush_rewrite_rules();
		wp_safe_redirect(admin_url("admin.php?page=myelophone-seo&tab=settings&updated=1"));
		exit();
	}

	public function dismiss_sitemap_inflation()
	{
		if (!current_user_can("manage_options")) {
			wp_die(esc_html__("Permission denied.", "myelophone-seo"));
		}

		check_admin_referer("meph_seo_dismiss_sitemap_inflation");
		delete_option("meph_seo_sos_inflation_notice");
		wp_safe_redirect(admin_url("admin.php?page=myelophone-seo&tab=sos"));
		exit();
	}

	public function enable_site_indexing()
	{
		if (!current_user_can("manage_options")) {
			wp_die(esc_html__("Permission denied.", "myelophone-seo"));
		}

		check_admin_referer("meph_seo_enable_indexing");
		update_option("blog_public", "1");
		$options = get_option(MephSeo_Settings::OPTION, []);
		if (is_array($options)) {
			$options["global_noindex"] = "0";
			update_option(MephSeo_Settings::OPTION, $options, false);
		}
		wp_safe_redirect(admin_url("admin.php?page=myelophone-seo&updated=1"));
		exit();
	}

	private function local_business_types()
	{
		return [
			"LocalBusiness" => "LocalBusiness",
			"Store" => "Store",
			"Restaurant" => "Restaurant",
			"ProfessionalService" => "ProfessionalService",
			"MedicalBusiness" => "MedicalBusiness",
			"LegalService" => "LegalService",
			"RealEstateAgent" => "RealEstateAgent",
			"AutomotiveBusiness" => "AutomotiveBusiness",
			"HealthAndBeautyBusiness" => "HealthAndBeautyBusiness",
			"HomeAndConstructionBusiness" => "HomeAndConstructionBusiness",
		];
	}
}
