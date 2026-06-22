<?php
/**
 * SEO metabox.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_MetaBox
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
		add_action("add_meta_boxes", [$this, "add_meta_box"]);
		add_action("save_post", [$this, "save"], 10, 2);
	}

	/**
	 * Add metabox.
	 *
	 * @return void
	 */
	public function add_meta_box()
	{
		$post_types = get_post_types(["public" => true], "names");
		foreach ($post_types as $post_type) {
			add_meta_box(
				"meph-seo-meta",
				__("MyelophOne SEO", "myelophone-seo"),
				[$this, "render"],
				$post_type,
				"normal",
				"high",
			);
		}
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render($post)
	{
		wp_nonce_field("meph_seo_meta", "meph_seo_meta_nonce");
		$fields = [
			"_meph_seo_title" => [__("SEO title", "myelophone-seo"), "text", 60],
			"_meph_seo_excerpt" => [__("SEO excerpt", "myelophone-seo"), "textarea", 155],
			"_meph_seo_description" => [__("SEO description", "myelophone-seo"), "textarea", 155],
			"_meph_seo_image" => [__("Primary image", "myelophone-seo"), "image", 0],
			"_meph_seo_social_title" => [__("Social title", "myelophone-seo"), "text", 60],
			"_meph_seo_social_description" => [__("Social description", "myelophone-seo"), "textarea", 155],
			"_meph_seo_social_image" => [__("Social image", "myelophone-seo"), "image", 0],
		];
		$title_value = get_post_meta($post->ID, "_meph_seo_title", true);
		$description_value = get_post_meta($post->ID, "_meph_seo_description", true);
		if ($post->post_type === "product" && $this->settings->enabled("auto_product_recommended")) {
			$title_value = $title_value ?: $this->settings->product_template_for_post("product_title", $post->ID);
			$description_value = $description_value ?: $this->settings->product_template_for_post("product_description", $post->ID);
		}
		?>
		<div class="meph-seo-metabox">
			<div class="meph-seo-preview-grid">
				<div class="meph-seo-preview-card">
					<h3><?php echo esc_html__("Google preview", "myelophone-seo"); ?></h3>
					<?php $this->render_google_devices($post); ?>
				</div>
				<div class="meph-seo-preview-card">
					<h3><?php echo esc_html__("Social preview", "myelophone-seo"); ?></h3>
					<div class="meph-social-preview">
						<div class="meph-social-image" data-social-image-preview data-image-preview-url="<?php echo esc_url($this->get_social_preview_url($post)); ?>"></div>
						<strong data-social-preview-title><?php echo esc_html(MephSeo_Variables::replace(get_post_meta($post->ID, "_meph_seo_social_title", true) ?: get_post_meta($post->ID, "_meph_seo_title", true) ?: get_the_title($post), $post)); ?></strong>
						<p data-social-preview-description><?php echo esc_html(MephSeo_Variables::replace(get_post_meta($post->ID, "_meph_seo_social_description", true) ?: get_post_meta($post->ID, "_meph_seo_description", true) ?: MephSeo_Variables::replace("%%excerpt%%", $post), $post)); ?></p>
					</div>
				</div>
			</div>

			<div class="meph-seo-metabox-grid">
				<div class="meph-seo-main-fields">
					<?php $this->render_field($post, "_meph_seo_title", $fields["_meph_seo_title"], $title_value, "meph-seo-wide-field"); ?>
					<?php $this->render_field($post, "_meph_seo_excerpt", $fields["_meph_seo_excerpt"], get_post_meta($post->ID, "_meph_seo_excerpt", true), "meph-seo-wide-field"); ?>
					<?php $this->render_field($post, "_meph_seo_description", $fields["_meph_seo_description"], $description_value, "meph-seo-wide-field"); ?>
					<?php $this->render_field($post, "_meph_seo_social_title", $fields["_meph_seo_social_title"], get_post_meta($post->ID, "_meph_seo_social_title", true), "meph-seo-wide-field"); ?>
					<?php $this->render_field($post, "_meph_seo_social_description", $fields["_meph_seo_social_description"], get_post_meta($post->ID, "_meph_seo_social_description", true), "meph-seo-wide-field"); ?>
				</div>
				<div class="meph-seo-side-fields">
					<div class="meph-verification-field meph-seo-image-group">
						<?php $this->render_image_field($post, "_meph_seo_image", $fields["_meph_seo_image"][0]); ?>
						<?php $this->render_image_field($post, "_meph_seo_social_image", $fields["_meph_seo_social_image"][0]); ?>
					</div>
					<div class="meph-seo-toggle-group">
						<?php $this->render_switch($post, "_meph_seo_noindex", __("Noindex this page", "myelophone-seo"), __("Ask search engines not to index this URL while still following links.", "myelophone-seo")); ?>
						<?php $this->render_switch($post, "_meph_seo_disable_comments", __("Disable comments for this content", "myelophone-seo"), __("Closes comments and pingbacks only for this post, page, product, or custom post type entry.", "myelophone-seo")); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save metabox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function save($post_id, $post)
	{
		if (!isset($_POST["meph_seo_meta_nonce"])) {
			return;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST["meph_seo_meta_nonce"]));
		if (!wp_verify_nonce($nonce, "meph_seo_meta")) {
			return;
		}

		if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can("edit_post", $post_id)) {
			return;
		}

		$fields = [
			"_meph_seo_title" => "text",
			"_meph_seo_excerpt" => "textarea",
			"_meph_seo_description" => "textarea",
			"_meph_seo_image" => "url",
			"_meph_seo_social_title" => "text",
			"_meph_seo_social_description" => "textarea",
			"_meph_seo_social_image" => "url",
		];

		foreach ($fields as $key => $type) {
			if ($type === "url") {
				$value = isset($_POST[$key]) ? esc_url_raw(wp_unslash($_POST[$key])) : "";
			} elseif ($type === "textarea") {
				$value = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : "";
			} else {
				$value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : "";
			}

			if ($value) {
				update_post_meta($post_id, $key, $value);
			} else {
				delete_post_meta($post_id, $key);
			}
		}

		update_post_meta($post_id, "_meph_seo_noindex", isset($_POST["_meph_seo_noindex"]) ? "1" : "0");
		update_post_meta($post_id, "_meph_seo_disable_comments", isset($_POST["_meph_seo_disable_comments"]) ? "1" : "0");

		if ($post->post_type === "product" && $this->settings->enabled("auto_product_recommended")) {
			$product_title_template = trim((string) $this->settings->product_template_for_post("product_title", $post_id));
			$product_description_template = trim((string) $this->settings->product_template_for_post("product_description", $post_id));
			if ($product_title_template && !get_post_meta($post_id, "_meph_seo_title", true)) {
				update_post_meta($post_id, "_meph_seo_title", $product_title_template);
			}
			if ($product_description_template && !get_post_meta($post_id, "_meph_seo_description", true)) {
				update_post_meta($post_id, "_meph_seo_description", $product_description_template);
			}
		}
	}

	/**
	 * Render a regular SEO text field.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param array   $field Field config.
	 * @param string  $value Field value.
	 * @param string  $class Extra class.
	 * @return void
	 */
	private function render_field($post, $key, $field, $value, $class = "")
	{
		?>
		<div class="meph-verification-field <?php echo esc_attr($class); ?>">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field[0]); ?></label>
			<?php if ($field[1] === "textarea" || in_array($key, ["_meph_seo_title", "_meph_seo_social_title"], true)): ?>
				<textarea id="<?php echo esc_attr($key); ?>" class="large-text meph-seo-counted meph-seo-tall-input<?php echo in_array($key, ["_meph_seo_title", "_meph_seo_social_title"], true) ? " meph-seo-title-input" : ""; ?>" rows="4" name="<?php echo esc_attr($key); ?>" data-limit="<?php echo esc_attr($field[2]); ?>"><?php echo esc_textarea($value); ?></textarea>
			<?php else: ?>
				<input id="<?php echo esc_attr($key); ?>" class="regular-text meph-seo-counted" type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" data-limit="<?php echo esc_attr($field[2]); ?>">
			<?php endif; ?>
			<?php if ($field[2]): ?><p class="description"><span data-counter-for="<?php echo esc_attr($key); ?>">0</span> / <?php echo esc_html($field[2]); ?></p><?php endif; ?>
			<?php $this->render_variable_buttons($post); ?>
			<?php if ($post->post_type === "product" && in_array($key, ["_meph_seo_title", "_meph_seo_description"], true)): ?>
				<button type="button" class="button meph-seo-apply-product-template" data-target="<?php echo esc_attr($key); ?>" data-template="<?php echo esc_attr($key === "_meph_seo_title" ? $this->settings->product_template_for_post("product_title", $post->ID) : $this->settings->product_template_for_post("product_description", $post->ID)); ?>">
					<?php echo esc_html__("Use recommended product template", "myelophone-seo"); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render image URL picker field.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param string  $label Label.
	 * @return void
	 */
	private function render_image_field($post, $key, $label)
	{
		$value = get_post_meta($post->ID, $key, true);
		?>
		<div class="meph-seo-image-field">
			<label class="meph-text-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			<div class="meph-seo-media-row">
				<input id="<?php echo esc_attr($key); ?>" class="regular-text meph-seo-counted meph-seo-image-url" type="url" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" data-limit="0">
				<button type="button" class="button meph-seo-media-button" data-target="<?php echo esc_attr($key); ?>"><?php echo esc_html__("Select", "myelophone-seo"); ?></button>
			</div>
			<div class="meph-seo-image-preview" data-image-preview-for="<?php echo esc_attr($key); ?>" data-image-preview-url="<?php echo esc_url($value); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Render a compact switch row.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $key Meta key.
	 * @param string  $title Title.
	 * @param string  $description Description.
	 * @return void
	 */
	private function render_switch($post, $key, $title, $description)
	{
		?>
		<label class="meph-switch-label">
			<span class="meph-switch">
				<input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(get_post_meta($post->ID, $key, true), "1"); ?>>
				<span class="meph-slider"></span>
			</span>
			<span class="meph-switch-content">
				<span class="meph-switch-title">
					<span class="meph-switch-text"><?php echo esc_html($title); ?></span>
				</span>
				<span class="meph-switch-description"><?php echo esc_html($description); ?></span>
			</span>
		</label>
		<?php
	}

	/**
	 * Render Google preview for desktop and mobile.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	private function render_google_devices($post)
	{
		$fallback_title = $post->post_type === "product"
			? $this->settings->product_template_for_post("product_title", $post->ID)
			: $this->settings->get("default_title");
		$fallback_description = $post->post_type === "product"
			? $this->settings->product_template_for_post("product_description", $post->ID)
			: $this->settings->get("default_description");
		$title = MephSeo_Variables::replace(
			get_post_meta($post->ID, "_meph_seo_title", true) ?: $fallback_title,
			$post,
		);
		$description = MephSeo_Variables::replace(
			get_post_meta($post->ID, "_meph_seo_description", true) ?: $fallback_description,
			$post,
		);

		foreach (["desktop" => __("Laptop", "myelophone-seo"), "mobile" => __("Mobile", "myelophone-seo")] as $device => $label) {
			?>
			<div class="meph-google-device meph-google-<?php echo esc_attr($device); ?>">
				<div class="meph-device-label"><?php echo esc_html($label); ?></div>
				<div class="meph-google-preview">
					<div class="meph-google-url"><?php echo esc_html($this->get_preview_url($post)); ?></div>
					<div class="meph-google-title" data-preview-title><?php echo esc_html($title); ?></div>
					<div class="meph-google-description" data-preview-description><?php echo esc_html($description); ?></div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Variable insertion buttons.
	 *
	 * @return void
	 */
	private function render_variable_buttons($post)
	{
		$variables = [
			"%%title%%",
			"%%sitename%%",
			"%%excerpt%%",
			"%%sep%%",
		];

		if (function_exists("wc_get_product") && $post->post_type === "product") {
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
	 * Pretty preview URL for editor cards.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function get_preview_url($post)
	{
		if (function_exists("get_sample_permalink")) {
			$sample = get_sample_permalink($post->ID);
			if (is_array($sample) && !empty($sample[0])) {
				$slug = !empty($sample[1]) ? $sample[1] : sanitize_title($post->post_title);
				return str_replace(["%postname%", "%pagename%"], $slug, $sample[0]);
			}
		}

		return get_permalink($post);
	}

	/**
	 * Inline image preview style.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	/**
	 * Social preview image URL.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function get_social_preview_url($post)
	{
		$url = get_post_meta($post->ID, "_meph_seo_social_image", true);
		$url = $url ?: get_post_meta($post->ID, "_meph_seo_image", true);
		if (!$url && has_post_thumbnail($post)) {
			$url = get_the_post_thumbnail_url($post, "large");
		}
		$url = $url ?: $this->settings->get("global_social_image", "");

		return $url;
	}
}
