<?php
/**
 * SOS monitoring and quarantine.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_SOS
{
	const CRON_HOOK = "myelophone_seo_daily_sos_check";
	const ALERTS_OPTION = "meph_seo_sos_alerts";

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
		add_action("init", [$this, "schedule"]);
		add_action(self::CRON_HOOK, [$this, "run_daily_checks"]);
		add_action("template_redirect", [$this, "maybe_quarantine"], 0);
		add_action("init", [$this, "maybe_block_request"], 0);
	}

	/**
	 * Schedule daily checks.
	 *
	 * @return void
	 */
	public function schedule()
	{
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, "daily", self::CRON_HOOK);
		}
	}

	/**
	 * Run daily SOS checks.
	 *
	 * @return void
	 */
	public function run_daily_checks()
	{
		$this->check_root_files();
		$this->check_monitor_urls();
		if ($this->settings->enabled("sos_check_sitemap_spam") || $this->settings->enabled("sos_sitemap_inflation_alert")) {
			$this->check_sitemap();
		}
	}

	/**
	 * Quarantine normal public pages with 503.
	 *
	 * @return void
	 */
	public function maybe_quarantine()
	{
		if (!$this->settings->enabled("sos_quarantine") || is_admin() || is_404()) {
			return;
		}

		if (!get_transient("meph_seo_sos_quarantine_notice_sent")) {
			$this->notify(__("MyelophOne SEO quarantine mode is active. Public pages are returning 503.", "myelophone-seo"));
			set_transient("meph_seo_sos_quarantine_notice_sent", "1", DAY_IN_SECONDS);
		}

		status_header(503);
		header("Retry-After: 3600");
		nocache_headers();
	}

	/**
	 * Optional request guard.
	 *
	 * @return void
	 */
	public function maybe_block_request()
	{
		if (is_admin()) {
			return;
		}

		$ua = isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"])) : "";
		if (!$ua && $this->settings->enabled("sos_block_empty_user_agent")) {
			status_header(403);
			exit();
		}

		if ($ua && $this->settings->enabled("sos_block_popular_crawlers") && preg_match('/(ahrefsbot|semrushbot|mj12bot|dotbot|petalbot|bytespider|dataforseobot)/i', $ua)) {
			status_header(403);
			exit();
		}

		if ($ua && $this->settings->enabled("sos_block_suspicious_user_agent") && preg_match('/(python-requests|curl|wget|scrapy|httpclient|libwww-perl|go-http-client)/i', $ua)) {
			status_header(403);
			exit();
		}
	}

	/**
	 * Check root file changes and suspicious files.
	 *
	 * @return void
	 */
	private function check_root_files()
	{
		$root = trailingslashit(ABSPATH);
		$tracked = ["index.php", ".htaccess"];
		$state = get_option("meph_seo_sos_root_state", []);
		$new_state = [];

		foreach ($tracked as $file) {
			$path = $root . $file;
			if (!file_exists($path)) {
				continue;
			}
			$new_state[$file] = filesize($path);
			if (isset($state[$file]) && (int) $state[$file] !== (int) $new_state[$file]) {
				/* translators: %s: root file name. */
				$this->alert("root_size_" . $file, sprintf(__("Root file size changed: %s", "myelophone-seo"), $file));
			}
		}

		$files = glob($root . "*");
		foreach ($files as $path) {
			if (!is_file($path)) {
				continue;
			}
			$name = basename($path);
			if (preg_match('/\.(zip|bak|old|backup|sql|tar|gz|7z|rar)$/i', $name) || preg_match('/base64/i', $name)) {
				/* translators: %s: suspicious root file name. */
				$this->alert("root_suspicious_" . md5($name), sprintf(__("Suspicious root file found: %s", "myelophone-seo"), $name));
			}
		}

		update_option("meph_seo_sos_root_state", $new_state, false);
	}

	/**
	 * Check monitored URLs.
	 *
	 * @return void
	 */
	private function check_monitor_urls()
	{
		$urls = array_filter(array_map("trim", preg_split('/\r\n|\r|\n/', (string) $this->settings->get("sos_monitor_urls"))));
		foreach ($urls as $url) {
			$response = wp_remote_get(esc_url_raw($url), ["timeout" => 15, "redirection" => 3]);
			$code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
			if ($code !== 200) {
				/* translators: 1: monitored URL, 2: returned HTTP status code. */
				$this->alert("monitor_" . md5($url . $code), sprintf(__('Monitored URL is not 200: %1$s returned %2$d', "myelophone-seo"), $url, $code));
			}
		}
	}

	/**
	 * Check sitemap spam and inflation.
	 *
	 * @return void
	 */
	private function check_sitemap()
	{
		$response = wp_remote_get(home_url("/sitemap.xml"), ["timeout" => 20]);
		if (is_wp_error($response)) {
			return;
		}

		$body = (string) wp_remote_retrieve_body($response);
		$count = $this->count_public_sitemap_urls();
		if ($this->settings->enabled("sos_check_sitemap_spam") && preg_match('/(casino|viagra|porn|loan|payday|crypto-bonus|\.ru\/|\.cn\/)/i', $body)) {
			$this->alert("sitemap_spam", __("Sitemap contains suspicious spam-like URLs.", "myelophone-seo"));
		}

		$previous = (int) get_option("meph_seo_sos_sitemap_url_count", 0);
		if ($this->settings->enabled("sos_sitemap_inflation_alert") && $previous > 0 && $count > max($previous + 100, (int) ($previous * 1.5))) {
			update_option("meph_seo_sos_inflation_notice", "1", false);
			/* translators: 1: previous sitemap URL count, 2: current sitemap URL count. */
			$this->alert("sitemap_inflation_" . gmdate("Ymd"), sprintf(__('Sitemap URL count increased sharply: %1$d to %2$d.', "myelophone-seo"), $previous, $count));
		}
		update_option("meph_seo_sos_sitemap_url_count", $count, false);
	}

	/**
	 * Count public URLs included in generated sitemap.
	 *
	 * @return int
	 */
	private function count_public_sitemap_urls()
	{
		$total = 0;
		$post_types = get_post_types(["public" => true], "names");
		foreach ($post_types as $post_type => $label) {
			if ($post_type === "attachment" || strpos($post_type, "elementor") !== false || $post_type === "e-landing-page") {
				unset($post_types[$post_type]);
			}
		}
		foreach ($post_types as $post_type) {
			$query = new WP_Query([
				"post_type" => $post_type,
				"post_status" => "publish",
				"posts_per_page" => 1,
				"fields" => "ids",
				"no_found_rows" => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to count sitemap inflation only for indexable entries.
				"meta_query" => [
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
				],
			]);
			$total += (int) $query->found_posts;
		}

		return $total;
	}

	/**
	 * Alert once per key per day.
	 *
	 * @param string $key Key.
	 * @param string $message Message.
	 * @return void
	 */
	private function alert($key, $message)
	{
		$alerts = get_option(self::ALERTS_OPTION, []);
		$today = gmdate("Y-m-d");
		if (isset($alerts[$key]) && $alerts[$key] === $today) {
			return;
		}
		$alerts[$key] = $today;
		update_option(self::ALERTS_OPTION, $alerts, false);
		$this->notify($message);
	}

	/**
	 * Send notification.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function notify($message)
	{
		$message = wp_strip_all_tags($message);
		do_action("myelophone_seo_sos_notify", $message);

		$token = $this->settings->get("sos_telegram_bot_token");
		$chat_id = $this->settings->get("sos_telegram_chat_id");
		if ($token && $chat_id) {
			wp_remote_post("https://api.telegram.org/bot" . rawurlencode($token) . "/sendMessage", [
				"timeout" => 15,
				"body" => ["chat_id" => $chat_id, "text" => $message],
			]);
		}

		$slack = $this->settings->get("sos_slack_webhook_url");
		if ($slack) {
			wp_remote_post($slack, [
				"timeout" => 15,
				"headers" => ["Content-Type" => "application/json"],
				"body" => wp_json_encode(["text" => $message]),
			]);
		}
	}
}
