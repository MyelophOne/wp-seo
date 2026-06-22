<?php
/**
 * URL transliteration.
 *
 * @package MyelophOne SEO
 */

if (!defined("ABSPATH")) {
	exit();
}

class MephSeo_Transliterator
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
		add_filter("sanitize_title", [$this, "sanitize_title"], 9, 3);
	}

	/**
	 * Transliterate new slugs.
	 *
	 * @param string $title Sanitized title.
	 * @param string $raw_title Raw title.
	 * @param string $context Context.
	 * @return string
	 */
	public function sanitize_title($title, $raw_title = "", $context = "save")
	{
		if (!$this->settings->enabled("enable_transliteration") || $context !== "save") {
			return $title;
		}

		$source = $raw_title ?: $title;
		$source = function_exists("mb_strtolower")
			? mb_strtolower($source, "UTF-8")
			: strtolower($source);
		$map = [
			"ą" => "a", "ć" => "c", "ę" => "e", "ł" => "l", "ń" => "n", "ó" => "o", "ś" => "s", "ż" => "z", "ź" => "z",
			"Ą" => "A", "Ć" => "C", "Ę" => "E", "Ł" => "L", "Ń" => "N", "Ó" => "O", "Ś" => "S", "Ż" => "Z", "Ź" => "Z",
			"ä" => "a", "ö" => "o", "ü" => "u", "ß" => "ss", "à" => "a", "á" => "a", "â" => "a", "ã" => "a", "å" => "a", "æ" => "ae",
			"ç" => "c", "è" => "e", "é" => "e", "ê" => "e", "ë" => "e", "ì" => "i", "í" => "i", "î" => "i", "ï" => "i",
			"ñ" => "n", "ò" => "o", "ó" => "o", "ô" => "o", "õ" => "o", "ø" => "o", "ù" => "u", "ú" => "u", "û" => "u", "ý" => "y",
			"а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ё" => "e", "ж" => "zh", "з" => "z", "и" => "i", "й" => "y",
			"к" => "k", "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f",
			"х" => "h", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "", "ы" => "y", "ь" => "", "э" => "e", "ю" => "yu", "я" => "ya",
		];

		$source = strtr($source, $map);

		if (function_exists("remove_accents")) {
			$source = remove_accents($source);
		}

		$source = strtolower($source);
		$source = preg_replace('/[^a-z0-9\s-]/', "", $source);
		$source = preg_replace('/[\s-]+/', "-", $source);

		return trim($source, "-") ?: $title;
	}
}
