=== MyelophOne SEO ===
Contributors: myelophone, aleksivanou
Donate link: https://buymeacoffee.com/aleksivanou
Tags: seo, schema, redirects, robots, woocommerce
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO toolkit for WordPress, WooCommerce and Elementor with metadata, schema, redirects, robots tools, product templates and SEO quarantine mode.

== Description ==

MyelophOne SEO is a free SEO extension for MyelophOne Core. It provides metadata templates, per-page SEO fields, social previews, canonical URLs, robots controls, XML sitemaps, schema output, WooCommerce product SEO, redirects, 404 statistics, IndexNow support, URL transliteration, Elementor page settings, a Copyright widget, and optional SOS monitoring.

The plugin is designed for site owners and developers who want practical SEO controls without duplicated metadata output or a heavy dashboard.

= Key features =

* Metadata for title, description, robots, canonical, OpenGraph and Twitter.
* Inline variables and conditional expressions in editable SEO fields.
* WooCommerce product title and description templates with brand, category, price, SKU, stock and short description variables.
* Auto-fill recommended product SEO for WooCommerce products when product SEO fields are empty.
* JSON-LD for Organization, WebSite, WebPage, Article, Product, BreadcrumbList, LocalBusiness, VideoObject and NewsArticle where data exists.
* XML sitemap index and robots.txt rules for WordPress cleanup, Clean-param rules and crawler controls.
* SEO quarantine mode that returns HTTP 503 for public pages while keeping admin and 404 pages available.
* Redirect manager and 404 statistics without IP address logging.
* Elementor Post Settings integration, including per-page SEO fields and a Copyright widget.

= Translations =

Bundled translations are included for English (United States) and Russian. A POT file is included for community translations.

= Why it helps in AI-era SEO =

Search results and answer engines rely on clean page identity, stable canonical signals, useful structured data and understandable product metadata. MyelophOne SEO helps keep those signals consistent across regular WordPress content, WooCommerce products and Elementor-built pages.

SEO SOS mode includes quarantine, sitemap spike alerts, sitemap spam-pattern checks, monitored URL checks, selected root-file checks, 404 statistics, and request guard options to help detect suspicious SEO changes before they become visible in search results. Conditional variables make large template sets easier to maintain because empty product values can be skipped without manual editing.

== Variables and Conditions ==

SEO title and description templates support:

* `%%title%%`
* `%%sitename%%`
* `%%tagline%%`
* `%%excerpt%%`
* `%%author%%`
* `%%date%%`
* `%%year%%`
* `%%sep%%`
* `%%term_title%%`

WooCommerce templates can also use:

* `%%product_name%%`
* `%%brand%%`
* `%%price%%`
* `%%price_min%%`
* `%%sku%%`
* `%%stock%%`
* `%%category%%`
* `%%primary_category%%`
* `%%wc_shortdesc%%`

The Title separator setting is available as the `%%sep%%` variable.

The `%%excerpt%%` variable uses the MyelophOne SEO excerpt first, then the regular WordPress excerpt, then a trimmed content fallback.

Conditions can be used directly inside editable SEO fields. Examples:

* Fallback: `[%%price_min%% ?? %%price%%]`
* Full ternary: `[%%brand%% ? "(%%brand%%)" : ""]`
* Short ternary: `[%%brand%% ? "(%%brand%%)"]`
* Comparison: `[%%primary_category%% === "Uncategorized" ? "" : %%primary_category%%]`
* Safe function: `[lower(%%primary_category%%) === "uncategorized" ? "" : %%primary_category%%]`

Supported operators: `===`, `!==`, `==`, `!=`, `>`, `>=`, `<`, `<=`.

Supported safe functions: `lower()`, `upper()`, `trim()`, `length()`.

Use quotes for literal condition values and empty strings, for example `"Uncategorized"` or `""`. Output fragments can be unquoted when they are plain text with variables, for example `from %%price_min%%`.

WooCommerce price and description values are converted to plain SEO text before output, so snippets do not contain HTML entities such as `&nbsp;` or numeric currency entity fragments.

== WooCommerce SEO ==

Default Recommended Product Title:

`Buy %%title%% %%brand%% online %%sep%% %%sitename%%`

Default Recommended Product Description:

`Order online %%title%% [%%primary_category%% === "Uncategorized" ? "" : %%primary_category%%] %%brand%% at %%sitename%% [%%price_min%% ? from %%price_min%% : ""]. %%wc_shortdesc%%`

When Auto-fill recommended product SEO is enabled, products with empty SEO title and description fields can receive the configured recommended templates. Existing custom product SEO fields are not overwritten.

== Elementor Integration ==

MyelophOne SEO is compatible with Elementor and WooCommerce. To manage SEO for an Elementor page:

1. Open the page with Elementor.
2. Click the page settings gear.
3. Open the MyelophOne SEO section.
4. Edit SEO title, description, image, social fields, noindex and comments settings.

The plugin also adds an Elementor Copyright element under the MyelophOne category. It is intended for quickly outputting a current copyright line with owner, start year and rights text.

= Elementor field priority =

MyelophOne SEO stores per-page SEO values in regular WordPress post meta. The SEO controls shown in Elementor Post Settings are an editing interface for the same values used by the regular WordPress SEO metabox.

When a page is saved in Elementor, the MyelophOne SEO controls from Elementor Post Settings are synchronized into the regular SEO post meta. On the frontend, MyelophOne SEO reads the regular SEO post meta, not a separate Elementor-only copy. If the same SEO value is changed in both places before saving, the last saved editor wins.

These fields do not change standard Elementor content controls such as page title widgets, headings, page layout, or the WordPress featured image. They affect MyelophOne SEO metadata output only. Image fields are metadata overrides: when empty, MyelophOne SEO can fall back to the WordPress featured image.

== SEO Quarantine and SOS Monitoring ==

SEO quarantine mode returns HTTP 503 for normal public pages and keeps admin requests and 404 responses available. It is intended for emergency situations, staging cleanup or suspected SEO spam incidents.

The SOS tab can optionally monitor configured URLs, sitemap URL count, sitemap spam patterns, selected root file changes, and suspicious request patterns. Telegram and Slack notifications are sent only when the corresponding token, chat ID or webhook URL is entered by the site administrator.

== Privacy and External Services ==

MyelophOne SEO does not track visitors and does not collect IP addresses in its 404 statistics.

The plugin can make external HTTP requests only when related features are enabled or configured:

* IndexNow is an external URL-submission protocol used to notify participating search engines about changed public content. When IndexNow is enabled and a public post is published or updated, the plugin sends the updated URL, the generated IndexNow key, and the key location URL to `https://api.indexnow.org/indexnow`. IndexNow information: https://www.indexnow.org/terms and https://www.indexnow.org/privacy.
* SOS monitored URLs are requested by the WordPress site itself when a site administrator enters URLs to monitor. The request is sent to each configured URL during the daily SOS check to verify that it returns HTTP 200. The destination service depends on the URL entered by the administrator.
* Telegram Bot API is used to send SOS notifications only when a site administrator enters a Telegram bot token and chat ID. The plugin sends the notification text and chat ID to `https://api.telegram.org/` when an SOS alert is triggered. Telegram Terms of Service: https://telegram.org/tos. Telegram Privacy Policy: https://telegram.org/privacy.
* Slack incoming webhooks are used to send SOS notifications only when a site administrator enters a Slack webhook URL. The plugin sends the notification text to the configured Slack webhook URL when an SOS alert is triggered. Slack Terms of Service: https://slack.com/terms-of-service. Slack Privacy Policy: https://slack.com/privacy-policy.

No external JavaScript or CSS is loaded by the plugin.

== Installation ==

1. Install and activate MyelophOne Core.
2. Upload the `myelophone-seo` folder to `/wp-content/plugins/` or install the plugin archive through the WordPress admin.
3. Activate MyelophOne SEO.
4. Open MyelophOne -> SEO.
5. Configure only the modules you want to use.

== Frequently Asked Questions ==

= Does MyelophOne SEO require MyelophOne Core? =

Yes. MyelophOne SEO is an extension plugin and depends on MyelophOne Core for the shared MyelophOne admin experience.

= Does it work with Elementor? =

Yes. SEO fields are available in the regular WordPress editor and in Elementor Post Settings.

= Does it work with WooCommerce? =

Yes. Product templates support WooCommerce variables for product title, brand, price, minimum price, SKU, stock, category, primary category and short description.

= Can I use conditions in editable SEO fields? =

Yes. You can use fallback, ternary and comparison expressions directly in title and description templates.

= What is SEO quarantine mode? =

SEO quarantine mode returns HTTP 503 for normal public pages while leaving 404 and admin responses available. It is intended as an emergency SEO protection mode.

= Can I use it with another SEO plugin? =

It can be installed with another SEO plugin, but duplicate metadata, schema, canonical, sitemap, robots or redirect output should be avoided. Use one SEO output system where possible.

== Changelog ==

= 1.0.0 =
* Initial release with metadata, canonical, OpenGraph and Twitter tags.
* Per-post, per-page, product and custom post type SEO fields.
* Template variables and conditional expressions.
* WooCommerce product SEO templates, variables and auto-fill.
* Elementor Post Settings integration and Copyright widget.
* JSON-LD schema for common WordPress and WooCommerce content.
* XML sitemap index, robots.txt cleanup, Clean-param rules and crawler controls.
* Redirect manager, 404 statistics, IndexNow, URL transliteration and SOS monitoring.
* Translation catalogs for English and Russian.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.

== Support ==

Use the WordPress.org support forum for public support requests. Development and author information is available from the plugin author profile.

== Donate ==

If MyelophOne SEO saves you time, you can support the author at https://buymeacoffee.com/aleksivanou.

== Credits ==

Developed by Aliaksandr Ivanou for the MyelophOne plugin ecosystem.
