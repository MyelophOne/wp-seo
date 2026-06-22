<?php
/**
 * Redirect manager and 404 logger.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Redirects
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
		add_action("template_redirect", [$this, "maybe_redirect"], 1);
		add_action("template_redirect", [$this, "log_404"], 99);
		add_action("admin_post_meph_seo_save_redirects", [$this, "save_redirects"]);
		add_action("admin_post_meph_seo_clear_404", [$this, "clear_404_log"]);
	}

	/**
	 * Redirect request.
	 *
	 * @return void
	 */
	public function maybe_redirect()
	{
		if (is_admin()) {
			return;
		}

		$request = isset($_SERVER["REQUEST_URI"])
			? esc_url_raw(wp_unslash($_SERVER["REQUEST_URI"]))
			: "";
		$path = strtok($request, "?");
		$redirects = $this->get_redirects();

		foreach ($redirects as $redirect) {
			if (empty($redirect["from"]) || empty($redirect["to"])) {
				continue;
			}

			$target = $this->match_redirect($path, $redirect["from"], $redirect["to"]);
			if (!$target) {
				continue;
			}

			if ($this->is_redirect_loop($target, $redirects)) {
				continue;
			}

			$status = isset($redirect["status"]) && (int) $redirect["status"] === 302 ? 302 : 301;
			wp_safe_redirect($target, $status);
			exit();
		}
	}

	/**
	 * Prevent redirect loops.
	 *
	 * @param string $target Target URL.
	 * @param array  $redirects Redirect rules.
	 * @return bool
	 */
	private function is_redirect_loop($target, $redirects)
	{
		$current = home_url(isset($_SERVER["REQUEST_URI"]) ? esc_url_raw(wp_unslash($_SERVER["REQUEST_URI"])) : "/");
		$current_clean = untrailingslashit($current);
		$target_clean = untrailingslashit($target);

		if (strtolower($current_clean) === strtolower($target_clean)) {
			return true;
		}

		$target_path = wp_parse_url($target, PHP_URL_PATH);
		foreach ($redirects as $redirect) {
			if (empty($redirect["from"]) || empty($redirect["to"])) {
				continue;
			}
			$next = $this->match_redirect($target_path, $redirect["from"], $redirect["to"]);
			if ($next && strtolower(untrailingslashit($next)) === strtolower($current_clean)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match redirects including wildcard.
	 *
	 * @param string $path Path.
	 * @param string $from From.
	 * @param string $to To.
	 * @return string
	 */
	private function match_redirect($path, $from, $to)
	{
		$from = "/" . ltrim($from, "/");
		if (strpos($from, "*") === false) {
			return untrailingslashit($path) === untrailingslashit($from)
				? $this->absolute_url($to)
				: "";
		}

		$pattern = "#^" . str_replace("\\*", "(.*)", preg_quote($from, "#")) . "$#";
		if (!preg_match($pattern, $path, $matches)) {
			return "";
		}

		$replacement = isset($matches[1]) ? $matches[1] : "";

		return $this->absolute_url(str_replace("*", $replacement, $to));
	}

	/**
	 * Absolute URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function absolute_url($url)
	{
		if (preg_match("#^https?://#i", $url)) {
			return $url;
		}

		return home_url("/" . ltrim($url, "/"));
	}

	/**
	 * Log 404 without IP address.
	 *
	 * @return void
	 */
	public function log_404()
	{
		if (!is_404() || is_admin()) {
			return;
		}

		$request = isset($_SERVER["REQUEST_URI"])
			? esc_url_raw(wp_unslash($_SERVER["REQUEST_URI"]))
			: "";
		if (!$request) {
			return;
		}

		$log = get_option(MephSeo_Settings::NOT_FOUND_OPTION, []);
		if (!is_array($log)) {
			$log = [];
		}
		$log = $this->rotate_404_log($log);

		$key = md5($request);
		if (!isset($log[$key])) {
			$log[$key] = [
				"url" => $request,
				"hits" => 0,
				"last_seen" => "",
				"referrer_host" => "",
			];
		}

		$log[$key]["hits"]++;
		$log[$key]["last_seen"] = current_time("mysql");
		if (!empty($_SERVER["HTTP_REFERER"])) {
			$host = wp_parse_url(esc_url_raw(wp_unslash($_SERVER["HTTP_REFERER"])), PHP_URL_HOST);
			$log[$key]["referrer_host"] = $host ? sanitize_text_field($host) : "";
		}

		uasort($log, function ($a, $b) {
			return (int) $b["hits"] <=> (int) $a["hits"];
		});

		$max_entries = max(1, absint($this->settings->get("not_found_max_entries", 2000)));
		update_option(MephSeo_Settings::NOT_FOUND_OPTION, array_slice($log, 0, $max_entries, true), false);
	}

	/**
	 * Save redirects.
	 *
	 * @return void
	 */
	public function save_redirects()
	{
		if (!current_user_can("manage_options")) {
			wp_die(esc_html__("Permission denied.", "myelophone-seo"));
		}

		check_admin_referer("meph_seo_save_redirects");

		$redirects = [];
		$from = isset($_POST["from"]) && is_array($_POST["from"]) ? array_map("sanitize_text_field", wp_unslash($_POST["from"])) : [];
		$to = isset($_POST["to"]) && is_array($_POST["to"]) ? array_map("esc_url_raw", wp_unslash($_POST["to"])) : [];
		$status = isset($_POST["status"]) && is_array($_POST["status"]) ? array_map("absint", wp_unslash($_POST["status"])) : [];

		foreach ($from as $index => $from_value) {
			$from_value = sanitize_text_field($from_value);
			$to_value = isset($to[$index]) ? esc_url_raw($to[$index]) : "";
			if (!$from_value || !$to_value) {
				continue;
			}

			$redirects[] = [
				"from" => $from_value,
				"to" => $to_value,
				"status" => isset($status[$index]) && (int) $status[$index] === 302 ? 302 : 301,
			];
		}

		update_option(MephSeo_Settings::REDIRECTS_OPTION, $redirects, false);
		wp_safe_redirect(admin_url("admin.php?page=myelophone-seo&tab=redirects&updated=1"));
		exit();
	}

	/**
	 * Clear 404 log.
	 *
	 * @return void
	 */
	public function clear_404_log()
	{
		if (!current_user_can("manage_options")) {
			wp_die(esc_html__("Permission denied.", "myelophone-seo"));
		}

		check_admin_referer("meph_seo_clear_404");
		update_option(MephSeo_Settings::NOT_FOUND_OPTION, [], false);
		wp_safe_redirect(admin_url("admin.php?page=myelophone-seo&tab=not-found&updated=1"));
		exit();
	}

	/**
	 * Get redirects.
	 *
	 * @return array
	 */
	public function get_redirects()
	{
		$redirects = get_option(MephSeo_Settings::REDIRECTS_OPTION, []);

		return is_array($redirects) ? $redirects : [];
	}

	/**
	 * Rotate 404 entries by retention window.
	 *
	 * @param array $log Log.
	 * @return array
	 */
	private function rotate_404_log($log)
	{
		$retention_days = max(1, absint($this->settings->get("not_found_retention_days", 90)));
		$threshold = time() - ($retention_days * DAY_IN_SECONDS);

		foreach ($log as $key => $entry) {
			if (empty($entry["last_seen"])) {
				continue;
			}

			$last_seen = strtotime($entry["last_seen"]);
			if ($last_seen && $last_seen < $threshold) {
				unset($log[$key]);
			}
		}

		return $log;
	}
}
