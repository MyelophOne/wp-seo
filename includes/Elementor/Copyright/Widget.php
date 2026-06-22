<?php
/**
 * Elementor copyright widget.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Elementor_Copyright_Widget extends \Elementor\Widget_Base
{
	public function get_name()
	{
		return "meph_copyright";
	}

	public function get_title()
	{
		return __("Copyright", "myelophone-seo");
	}

	public function get_icon()
	{
		return "eicon-text";
	}

	public function get_categories()
	{
		return ["myelophone"];
	}

	public function get_keywords()
	{
		return [
			"myelophone",
			"copyright",
			"legal",
			"footer",
		];
	}

	protected function register_controls()
	{
		$this->start_controls_section("content_section", [
			"label" => __("Content", "myelophone-seo"),
		]);

		$this->add_control("owner", [
			"label" => __("Owner", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXT,
			"default" => get_bloginfo("name"),
		]);

		$this->add_control("start_year", [
			"label" => __("Start year", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::NUMBER,
			"default" => date_i18n("Y"),
		]);

		$this->add_control("rights", [
			"label" => __("Rights text", "myelophone-seo"),
			"type" => \Elementor\Controls_Manager::TEXT,
			"default" => __("All rights reserved.", "myelophone-seo"),
		]);

		$this->end_controls_section();
	}

	protected function render()
	{
		$settings = $this->get_settings_for_display();
		$current_year = date_i18n("Y");
		$start_year = !empty($settings["start_year"]) ? (string) absint($settings["start_year"]) : $current_year;
		$year = $start_year && $start_year !== $current_year ? $start_year . "-" . $current_year : $current_year;

		printf(
			'<p class="meph-copyright">&copy; %s %s. %s</p>',
			esc_html($year),
			esc_html($settings["owner"]),
			esc_html($settings["rights"]),
		);
	}
}
