<?php
/**
 * Misc SEO helpers.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Misc
{
	const INDEXNOW_KEY_OPTION = "meph_seo_indexnow_key";

	/**
	 * Settings.
	 *
	 * @var MephSeo_Settings
	 */
	private $settings;

	/**
	 * Rendering guard.
	 *
	 * @var array
	 */
	private $rendering = [];

	/**
	 * Constructor.
	 *
	 * @param MephSeo_Settings $settings Settings.
	 */
	public function __construct($settings)
	{
		$this->settings = $settings;
		add_shortcode("myelophone_content", [$this, "content_shortcode"]);
		add_shortcode("meph_content", [$this, "content_shortcode"]);
		add_action("template_redirect", [$this, "maybe_render_indexnow_key"], 0);
		add_action("save_post", [$this, "maybe_ping_indexnow"], 30, 2);
	}

	/**
	 * Include another post's content.
	 *
	 * @param array  $atts Attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag Shortcode tag.
	 * @return string
	 */
	public function content_shortcode($atts, $content = "", $tag = "myelophone_content")
	{
		if (!$this->settings->enabled("enable_content_shortcode")) {
			return "";
		}

		$atts = shortcode_atts(["id" => 0], $atts, $tag);
		$post_id = absint($atts["id"]);
		if (!$post_id || isset($this->rendering[$post_id])) {
			return "";
		}

		$post = get_post($post_id);
		if (!$post || $post->post_status !== "publish") {
			return "";
		}

		$this->rendering[$post_id] = true;
		$rendered_content = $this->render_included_post_content($post->post_content);
		unset($this->rendering[$post_id]);

		return wp_kses_post($rendered_content);
	}

	/**
	 * Render included post content through a controlled WordPress formatting pipeline.
	 *
	 * @param string $content Raw post content.
	 * @return string
	 */
	private function render_included_post_content($content)
	{
		$rendered = (string) $content;

		if (function_exists("do_blocks")) {
			$rendered = do_blocks($rendered);
		}

		$rendered = wptexturize($rendered);
		$rendered = convert_smilies($rendered);
		$rendered = wpautop($rendered);
		$rendered = shortcode_unautop($rendered);
		$rendered = do_shortcode($rendered);

		return $rendered;
	}

	/**
	 * Serve IndexNow key file.
	 *
	 * @return void
	 */
	public function maybe_render_indexnow_key()
	{
		if (!$this->settings->enabled("enable_indexnow")) {
			return;
		}

		$key = $this->get_indexnow_key();
		$path = isset($_SERVER["REQUEST_URI"]) ? trim((string) wp_parse_url(esc_url_raw(wp_unslash($_SERVER["REQUEST_URI"])), PHP_URL_PATH), "/") : "";
		if ($path !== $key . ".txt") {
			return;
		}

		status_header(200);
		header("Content-Type: text/plain; charset=UTF-8");
		echo esc_html($key);
		exit();
	}

	/**
	 * Ping IndexNow on public content updates.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function maybe_ping_indexnow($post_id, $post)
	{
		if (
			!$this->settings->enabled("enable_indexnow") ||
			wp_is_post_revision($post_id) ||
			!$post instanceof WP_Post ||
			$post->post_status !== "publish" ||
			get_post_meta($post_id, "_meph_seo_noindex", true) === "1"
		) {
			return;
		}

		if (!in_array($post->post_type, get_post_types(["public" => true], "names"), true)) {
			return;
		}

		$key = $this->get_indexnow_key();
		$url = add_query_arg([
			"url" => get_permalink($post_id),
			"key" => $key,
			"keyLocation" => home_url("/" . $key . ".txt"),
		], "https://api.indexnow.org/indexnow");

		wp_remote_get($url, ["timeout" => 10, "blocking" => false]);
	}

	/**
	 * Get or create IndexNow key.
	 *
	 * @return string
	 */
	private function get_indexnow_key()
	{
		$key = get_option(self::INDEXNOW_KEY_OPTION, "");
		if (!$key) {
			$key = wp_generate_password(32, false, false);
			update_option(self::INDEXNOW_KEY_OPTION, $key, false);
		}

		return $key;
	}
}
