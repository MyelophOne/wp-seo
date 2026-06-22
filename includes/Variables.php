<?php
/**
 * Template variables.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Variables
{
	/**
	 * Replace variables in a template.
	 *
	 * @param string      $template Template.
	 * @param WP_Post|int $post Post.
	 * @param array       $extra Extra values.
	 * @return string
	 */
	public static function replace($template, $post = null, $extra = [])
	{
		$post = $post ? get_post($post) : get_post();
		$settings = new MephSeo_Settings();
		$sep = $settings->get("separator", "-");
		$title = $post ? get_the_title($post) : get_bloginfo("name");
		$excerpt = $post ? self::get_excerpt($post) : get_bloginfo("description");
		$author = $post ? get_the_author_meta("display_name", $post->post_author) : "";
		$term_title = "";

		if (is_category() || is_tag() || is_tax()) {
			$term = get_queried_object();
			if ($term && !is_wp_error($term)) {
				$term_title = $term->name;
			}
		}

		$vars = [
			"%%title%%" => $title,
			"%%sitename%%" => get_bloginfo("name"),
			"%%tagline%%" => get_bloginfo("description"),
			"%%sep%%" => $sep,
			"%%excerpt%%" => $excerpt,
			"%%author%%" => $author,
			"%%date%%" => $post ? get_the_date("", $post) : date_i18n(get_option("date_format")),
			"%%year%%" => date_i18n("Y"),
			"%%term_title%%" => $term_title,
			"%%product_name%%" => $title,
			"%%price%%" => "",
			"%%price_min%%" => "",
			"%%sku%%" => "",
			"%%brand%%" => "",
			"%%stock%%" => "",
			"%%category%%" => $post ? self::get_primary_term_name($post->ID, "category") : "",
			"%%primary_category%%" => $post ? self::get_primary_term_name($post->ID, "category") : "",
			"%%wc_shortdesc%%" => "",
		];

		if ($post && function_exists("wc_get_product") && $post->post_type === "product") {
			$product = wc_get_product($post->ID);
			if ($product) {
				$brand = self::get_product_brand($post->ID);
				$vars["%%product_name%%"] = $product->get_name();
				$vars["%%price%%"] = self::get_product_price($product);
				$vars["%%price_min%%"] = self::get_product_min_price($product);
				$vars["%%sku%%"] = $product->get_sku();
				$vars["%%brand%%"] = $brand;
				$vars["%%stock%%"] = $product->is_in_stock()
					? __("In stock", "myelophone-seo")
					: __("Out of stock", "myelophone-seo");
				$vars["%%category%%"] = self::get_primary_term_name($post->ID, "product_cat");
				$vars["%%primary_category%%"] = self::get_primary_term_name($post->ID, "product_cat");
				$vars["%%wc_shortdesc%%"] = wp_trim_words(self::normalize_text_value($product->get_short_description()), 28);
			}
		}

		$vars = array_merge($vars, $extra);
		$vars = apply_filters("myelophone_seo_variables", $vars, $template, $post);
		$value = self::replace_conditionals((string) $template, $vars);
		$value = strtr($value, $vars);
		$value = self::clean_replaced_value($value, $sep);

		return apply_filters("myelophone_seo_variable_replaced_value", $value, $template, $vars, $post);
	}

	/**
	 * Replace bracketed conditional expressions.
	 *
	 * Supported:
	 * [%%brand%% ?? ""]
	 * [%%brand%% ? "(%%brand%%)" : "fallback"]
	 * [%%brand%% ? "(%%brand%%)"]
	 * [%%primary_category%% === "Uncategorized" ? "" : %%primary_category%%]
	 * [lower(%%primary_category%%) === "uncategorized" ? "" : %%primary_category%%]
	 *
	 * @param string $template Template.
	 * @param array  $vars Variables.
	 * @return string
	 */
	private static function replace_conditionals($template, $vars)
	{
		return preg_replace_callback('/\[([^\[\]]+)\]/', function ($matches) use ($vars) {
			$expression = trim($matches[1]);

			if (preg_match('/^(%%[a-z0-9_]+%%)\s*\?\?\s*(.+)$/i', $expression, $coalesce)) {
				$variable = $coalesce[1];
				$fallback = self::strip_expression_quotes(trim($coalesce[2]));
				$value = isset($vars[$variable]) ? trim((string) $vars[$variable]) : "";

				return $value !== "" ? $value : $fallback;
			}

			if (preg_match('/^(.+?)\s*\?\s*(.+)\s*:\s*(.+)$/i', $expression, $ternary)) {
				$condition = trim($ternary[1]);
				$truthy = self::strip_expression_quotes(trim($ternary[2]));
				$falsy = self::strip_expression_quotes(trim($ternary[3]));

				return self::evaluate_condition($condition, $vars) ? strtr($truthy, $vars) : strtr($falsy, $vars);
			}

			if (preg_match('/^(.+?)\s*\?\s*(.+)$/i', $expression, $short_ternary)) {
				$condition = trim($short_ternary[1]);
				$truthy = self::strip_expression_quotes(trim($short_ternary[2]));

				return self::evaluate_condition($condition, $vars) ? strtr($truthy, $vars) : "";
			}

			return $matches[0];
		}, $template);
	}

	/**
	 * Evaluate a safe condition expression.
	 *
	 * @param string $condition Condition.
	 * @param array  $vars Variables.
	 * @return bool
	 */
	private static function evaluate_condition($condition, $vars)
	{
		if (preg_match('/^(.+?)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)$/', $condition, $comparison)) {
			$left = self::resolve_expression_operand(trim($comparison[1]), $vars);
			$operator = $comparison[2];
			$right = self::resolve_expression_operand(trim($comparison[3]), $vars);

			if (in_array($operator, [">", ">=", "<", "<="], true)) {
				$left = is_numeric($left) ? (float) $left : 0;
				$right = is_numeric($right) ? (float) $right : 0;
			}

			switch ($operator) {
				case "===":
				case "==":
					return (string) $left === (string) $right;
				case "!==":
				case "!=":
					return (string) $left !== (string) $right;
				case ">":
					return $left > $right;
				case ">=":
					return $left >= $right;
				case "<":
					return $left < $right;
				case "<=":
					return $left <= $right;
			}
		}

		return trim((string) self::resolve_expression_operand($condition, $vars)) !== "";
	}

	/**
	 * Resolve a safe operand for conditional expressions.
	 *
	 * @param string $operand Operand.
	 * @param array  $vars Variables.
	 * @return string|float
	 */
	private static function resolve_expression_operand($operand, $vars)
	{
		$operand = trim($operand);

		if (preg_match('/^(lower|upper|trim|length)\((.+)\)$/i', $operand, $function)) {
			$value = (string) self::resolve_expression_operand(trim($function[2]), $vars);
			$name = strtolower($function[1]);

			if ($name === "lower") {
				return function_exists("mb_strtolower") ? mb_strtolower($value) : strtolower($value);
			}

			if ($name === "upper") {
				return function_exists("mb_strtoupper") ? mb_strtoupper($value) : strtoupper($value);
			}

			if ($name === "trim") {
				return trim($value);
			}

			return function_exists("mb_strlen") ? mb_strlen($value) : strlen($value);
		}

		if (preg_match('/^%%[a-z0-9_]+%%$/i', $operand)) {
			return isset($vars[$operand]) ? trim((string) $vars[$operand]) : "";
		}

		if (is_numeric($operand)) {
			return (float) $operand;
		}

		return self::strip_expression_quotes($operand);
	}

	/**
	 * Strip wrapping quotes from expression values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function strip_expression_quotes($value)
	{
		if (
			(strlen($value) >= 2) &&
			(($value[0] === '"' && substr($value, -1) === '"') ||
				($value[0] === "'" && substr($value, -1) === "'"))
		) {
			return substr($value, 1, -1);
		}

		return $value;
	}

	/**
	 * Clean unresolved variables, empty separators, and punctuation.
	 *
	 * @param string $value Value.
	 * @param string $separator Title separator.
	 * @return string
	 */
	private static function clean_replaced_value($value, $separator)
	{
		$value = self::normalize_text_value($value);
		$value = preg_replace('/%%[a-z0-9_]+%%/i', "", $value);
		$value = preg_replace('/\s+/', " ", $value);

		$separator = trim($separator);
		if ($separator !== "") {
			$quoted = preg_quote($separator, "/");
			$value = preg_replace('/(?:\s*' . $quoted . '\s*){2,}/', " " . $separator . " ", $value);
			$value = preg_replace('/^\s*' . $quoted . '\s*/', "", $value);
			$value = preg_replace('/\s*' . $quoted . '\s*$/', "", $value);
			$value = preg_replace('/\s*' . $quoted . '\s*([.,;:!?])/', "$1", $value);
		}

		$value = preg_replace('/\s+([.,;:!?])/', "$1", $value);
		$value = preg_replace('/([.,;:!?]){2,}/', "$1", $value);
		$value = preg_replace('/\(\s*\)|\[\s*\]|\{\s*\}/', "", $value);
		$value = preg_replace('/\s+/', " ", $value);

		return trim($value, " \t\n\r\0\x0B-–—|,:;");
	}

	/**
	 * Normalize HTML-heavy variable values into plain readable SEO text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function normalize_text_value($value)
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
	 * Excerpt fallback.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function get_excerpt($post)
	{
		$seo_excerpt = get_post_meta($post->ID, "_meph_seo_excerpt", true);
		if ($seo_excerpt !== "") {
			return wp_trim_words($seo_excerpt, 28);
		}

		if (!empty($post->post_excerpt)) {
			return wp_trim_words($post->post_excerpt, 28);
		}

		return wp_trim_words(strip_shortcodes($post->post_content), 28);
	}

	/**
	 * Product brand fallback.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_product_brand($post_id)
	{
		$taxonomies = ["product_brand", "pa_brand", "pwb-brand"];

		foreach ($taxonomies as $taxonomy) {
			if (!taxonomy_exists($taxonomy)) {
				continue;
			}

			$name = self::get_primary_term_name($post_id, $taxonomy);
			if ($name) {
				return $name;
			}
		}

		return "";
	}

	/**
	 * Get first term name.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private static function get_primary_term_name($post_id, $taxonomy)
	{
		$terms = get_the_terms($post_id, $taxonomy);

		if (empty($terms) || is_wp_error($terms)) {
			return "";
		}

		return $terms[0]->name;
	}

	/**
	 * SEO-friendly product price including variable product ranges.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function get_product_price($product)
	{
		if (!$product || !method_exists($product, "get_type")) {
			return "";
		}

		if ($product->is_type("variable")) {
			$min = $product->get_variation_price("min", true);
			$max = $product->get_variation_price("max", true);

			if ($min !== "" && $max !== "" && (float) $min !== (float) $max) {
				return sprintf(
					/* translators: %s: Product minimum price */
					__("from %s", "myelophone-seo"),
					self::normalize_text_value(wc_price($min)),
				);
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
				return sprintf(
					/* translators: %s: Product minimum price */
					__("from %s", "myelophone-seo"),
					self::normalize_text_value(wc_price(min($prices))),
				);
			}
		}

		return self::normalize_text_value($product->get_price_html());
	}

	/**
	 * SEO-friendly minimum product price without a "from" prefix.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function get_product_min_price($product)
	{
		if (!$product || !method_exists($product, "get_type")) {
			return "";
		}

		if ($product->is_type("variable")) {
			$min = $product->get_variation_price("min", true);
			if ($min !== "") {
				return self::normalize_text_value(wc_price($min));
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
				return self::normalize_text_value(wc_price(min($prices)));
			}
		}

		$price = $product->get_price();
		return $price !== "" ? self::normalize_text_value(wc_price($price)) : "";
	}
}
