/**
 * MyelophOne SEO admin.
 *
 * @package MyelophOne SEO
 */

(function ($) {
  "use strict";

  let initialPreviewTitle = "";
  let initialPreviewDescription = "";

  function getTemplateVariables() {
    const variables = $.extend({}, mephSeoAdmin.variables || {});
    const editorTitle = $("#title").val() || variables["%%title%%"];
    const editorExcerpt =
      $("#_meph_seo_excerpt").val() ||
      $("#excerpt").val() ||
      variables["%%excerpt%%"];

    $.each(variables, function (variable, replacement) {
      variables[variable] = decodeHtmlEntities(replacement || "");
    });

    if (editorTitle) {
      variables["%%title%%"] = editorTitle;
      variables["%%product_name%%"] = editorTitle;
    }

    if (editorExcerpt) {
      variables["%%excerpt%%"] = decodeHtmlEntities(editorExcerpt);
    }

    return variables;
  }

  function renderTemplateValue(value) {
    const variables = getTemplateVariables();
    let output = replaceConditionals(value || "", variables);
    $.each(variables, function (variable, replacement) {
      output = output.split(variable).join(replacement || "");
    });

    return cleanPreviewValue(output, variables["%%sep%%"] || "-");
  }

  function replaceVariables(value) {
    return renderTemplateValue(value);
  }

  function replaceConditionals(template, variables) {
    return template.replace(/\[([^\[\]]+)\]/g, function (fullMatch, expression) {
      const coalesce = expression.match(/^(%%[a-z0-9_]+%%)\s*\?\?\s*(.+)$/i);
      if (coalesce) {
        const value = (variables[coalesce[1]] || "").trim();
        const fallback = stripExpressionQuotes(coalesce[2].trim());

        return value || replaceSimpleVariables(fallback, variables);
      }

      const ternary = expression.match(/^(.+?)\s*\?\s*(.+)\s*:\s*(.+)$/i);
      if (ternary) {
        const condition = ternary[1].trim();
        const truthy = stripExpressionQuotes(ternary[2].trim());
        const falsy = stripExpressionQuotes(ternary[3].trim());

        return replaceSimpleVariables(
          evaluateCondition(condition, variables) ? truthy : falsy,
          variables,
        );
      }

      const shortTernary = expression.match(/^(.+?)\s*\?\s*(.+)$/i);
      if (shortTernary) {
        const condition = shortTernary[1].trim();
        const truthy = stripExpressionQuotes(shortTernary[2].trim());

        return evaluateCondition(condition, variables)
          ? replaceSimpleVariables(truthy, variables)
          : "";
      }

      return fullMatch;
    });
  }

  function evaluateCondition(condition, variables) {
    const comparison = condition.match(/^(.+?)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)$/);

    if (comparison) {
      let left = resolveExpressionOperand(comparison[1].trim(), variables);
      const operator = comparison[2];
      let right = resolveExpressionOperand(comparison[3].trim(), variables);

      if ([">", ">=", "<", "<="].indexOf(operator) !== -1) {
        left = $.isNumeric(left) ? parseFloat(left) : 0;
        right = $.isNumeric(right) ? parseFloat(right) : 0;
      }

      switch (operator) {
        case "===":
        case "==":
          return String(left) === String(right);
        case "!==":
        case "!=":
          return String(left) !== String(right);
        case ">":
          return left > right;
        case ">=":
          return left >= right;
        case "<":
          return left < right;
        case "<=":
          return left <= right;
      }
    }

    return String(resolveExpressionOperand(condition, variables) || "").trim() !== "";
  }

  function resolveExpressionOperand(operand, variables) {
    const value = String(operand || "").trim();
    const fn = value.match(/^(lower|upper|trim|length)\((.+)\)$/i);

    if (fn) {
      const resolved = String(resolveExpressionOperand(fn[2].trim(), variables) || "");
      const name = fn[1].toLowerCase();

      if (name === "lower") {
        return resolved.toLowerCase();
      }

      if (name === "upper") {
        return resolved.toUpperCase();
      }

      if (name === "trim") {
        return resolved.trim();
      }

      return resolved.length;
    }

    if (/^%%[a-z0-9_]+%%$/i.test(value)) {
      return String(variables[value] || "").trim();
    }

    if ($.isNumeric(value)) {
      return parseFloat(value);
    }

    return stripExpressionQuotes(value);
  }

  function replaceSimpleVariables(value, variables) {
    let output = value || "";
    $.each(variables, function (variable, replacement) {
      output = output.split(variable).join(replacement || "");
    });

    return output;
  }

  function stripExpressionQuotes(value) {
    if (
      value.length >= 2 &&
      ((value[0] === '"' && value[value.length - 1] === '"') ||
        (value[0] === "'" && value[value.length - 1] === "'"))
    ) {
      return value.slice(1, -1);
    }

    return value;
  }

  function cleanPreviewValue(value, separator) {
    let output = decodeHtmlEntities(value || "");
    output = output.replace(/%%[a-z0-9_]+%%/gi, "");
    output = output.replace(/\s+/g, " ");

    const escapedSeparator = String(separator || "-").replace(
      /[.*+?^${}()|[\]\\]/g,
      "\\$&",
    );

    if (escapedSeparator) {
      output = output.replace(
        new RegExp("(?:\\s*" + escapedSeparator + "\\s*){2,}", "g"),
        " " + separator + " ",
      );
      output = output.replace(
        new RegExp("^\\s*" + escapedSeparator + "\\s*"),
        "",
      );
      output = output.replace(
        new RegExp("\\s*" + escapedSeparator + "\\s*$"),
        "",
      );
      output = output.replace(
        new RegExp("\\s*" + escapedSeparator + "\\s*([.,;:!?])", "g"),
        "$1",
      );
    }

    return output
      .replace(/\s+([.,;:!?])/g, "$1")
      .replace(/([.,;:!?]){2,}/g, "$1")
      .replace(/\(\s*\)|\[\s*\]|\{\s*\}/g, "")
      .replace(/\s+/g, " ")
      .replace(/^[\s\-–—|,:;]+|[\s\-–—|,:;]+$/g, "")
      .trim();
  }

  function decodeHtmlEntities(value) {
    const normalized = String(value || "").replace(/&#(x?[0-9a-f]+);?/gi, "&#$1;");
    const textarea = document.createElement("textarea");
    textarea.innerHTML = normalized;

    return textarea.value.replace(/\u00a0/g, " ").replace(/[–—−]/g, "-");
  }

  function getProfileHost(value) {
    try {
      return new URL(value).host || value;
    } catch (error) {
      return value;
    }
  }

  function updateOrganizationPreview() {
    const organizationName =
      $("#organization_name").val() ||
      $("#organization_name").attr("placeholder") ||
      "";
    const organizationType = $("#local_business_type").val() || "LocalBusiness";
    const localPhone = $("#local_phone").val() || "";
    const sameAs = ($("#same_as").val() || "")
      .split(/\r?\n/)
      .map(function (url) {
        return String(url || "").trim();
      })
      .filter(Boolean);

    $("[data-org-preview-name]").text(organizationName);
    $("[data-org-preview-type]").text(organizationType);
    $("[data-org-preview-phone]").remove();

    if (localPhone) {
      $("[data-org-preview-type]").after(
        $("<span>", {
          "data-org-preview-phone": "",
          text: localPhone,
        }),
      );
    }

    const $sameAs = $("[data-org-preview-sameas]");
    $sameAs.empty();

    sameAs.slice(0, 4).forEach(function (profileUrl) {
      $("<span>").text(getProfileHost(profileUrl)).appendTo($sameAs);
    });

    if (sameAs.length > 4) {
      const moreText = mephSeoAdmin.i18n.moreProfiles || "+%d more";
      $("<span>")
        .text(moreText.replace("%d", sameAs.length - 4))
        .appendTo($sameAs);
    }
  }

  function initImagePreviews() {
    $("[data-image-preview-url]").each(function () {
      const $preview = $(this);
      const url = String($preview.attr("data-image-preview-url") || "").trim();

      if (url) {
        $preview.css("background-image", "url('" + url + "')");
      }
    });
  }

  function optionChecked(key) {
    return $('input[name="meph_seo_options[' + key + ']"][type="checkbox"]').is(
      ":checked",
    );
  }

  function optionValue(key) {
    const $field = $('[name="meph_seo_options[' + key + ']"]')
      .filter("input[type='text'], input[type='url'], textarea, select")
      .first();

    return $field.length ? $field.val() || "" : "";
  }

  function updateRobotsPreview() {
    const $preview = $("[data-robots-preview]");
    if (!$preview.length) {
      return;
    }

    const lines = [];
    if (optionChecked("enable_robots")) {
      lines.push(
        "User-agent: *",
        "Disallow: /wp-admin/",
        "Allow: /wp-admin/admin-ajax.php",
        "Disallow: /wp-login.php",
        "Disallow: /wp-register.php",
        "Disallow: /?s=",
        "Disallow: /search/",
        "Disallow: /*?replytocom=",
        "Disallow: /*&replytocom=",
        "Disallow: /xmlrpc.php",
      );

      const params = optionValue("robots_clean_params")
        .split(",")
        .map(function (param) {
          return String(param || "").trim();
        })
        .filter(Boolean);

      if (params.length) {
        lines.push("Clean-param: " + params.join("&"));
      }

    }

    if (optionChecked("block_intrusive_bots")) {
      lines.push("");
      ["AhrefsBot", "SemrushBot", "MJ12bot", "DotBot", "BLEXBot"].forEach(
        function (bot) {
          lines.push("User-agent: " + bot, "Disallow: /");
        },
      );
    }

    if (optionChecked("block_ai_training")) {
      lines.push("");
      ["GPTBot", "CCBot", "Google-Extended", "ClaudeBot", "Bytespider"].forEach(
        function (bot) {
          lines.push("User-agent: " + bot, "Disallow: /");
        },
      );
    }

    if (optionChecked("enable_robots")) {
      lines.push(
        "",
        "Sitemap: " +
          (mephSeoAdmin.homeUrl || "/").replace(/\/$/, "") +
          (optionChecked("enable_sitemap") ? "/sitemap.xml" : "/wp-sitemap.xml"),
      );
    }

    $preview.text(lines.join("\n") + (lines.length ? "\n" : ""));
  }

  function isSettingsPresetKey(key) {
    return key && key.indexOf("sos_") !== 0;
  }

  function optionValueEnabled(value) {
    return value === "1" || value === 1 || value === true;
  }

  function applyOptionValue(name, value, config) {
    const $fields = $('[name="' + name + '"]');

    if (!$fields.length) {
      return;
    }

    const $checkbox = $fields.filter('input[type="checkbox"]');
    if ($checkbox.length) {
      const enabled = config.disableSwitches ? false : optionValueEnabled(value);
      $fields.filter('input[type="hidden"]').val(enabled ? "1" : "0");
      $checkbox.prop("checked", enabled).trigger("change");
      return;
    }

    $fields
      .filter("input[type='text'], input[type='url'], textarea, select")
      .val(value)
      .trigger("input")
      .trigger("change");
  }

  function applyOptionSettings(settings, options) {
    const config = $.extend(
      {
        disableSwitches: false,
      },
      options || {},
    );

    $.each(settings || {}, function (key, value) {
      if (!isSettingsPresetKey(key)) {
        return;
      }

      const name = "meph_seo_options[" + key + "]";
      if ($.isPlainObject(value)) {
        $.each(value, function (nestedKey, nestedValue) {
          applyOptionValue(name + "[" + nestedKey + "]", nestedValue, config);
        });
        return;
      }

      applyOptionValue(name, value, config);
    });

    updateCounters();
    updatePreviews();
    initImagePreviews();
    updateOrganizationPreview();
    updateRobotsPreview();
  }

  function updateCounters() {
    $(".meph-seo-counted").each(function () {
      const $field = $(this);
      const limit = parseInt($field.data("limit"), 10);
      const id = $field.attr("id");
      const rawValue = $field.val() || "";
      const renderedValue =
        rawValue.indexOf("%%") !== -1 || rawValue.indexOf("[") !== -1
          ? renderTemplateValue(rawValue)
          : rawValue;
      const length = renderedValue.length;
      const $counter = $('[data-counter-for="' + id + '"]');

      if (!$counter.length) {
        return;
      }

      $counter.text(length);
      $counter.removeClass("meph-seo-count-warning meph-seo-count-danger");

      if (limit && length > limit) {
        $counter.addClass("meph-seo-count-danger");
      } else if (limit && length > limit * 0.9) {
        $counter.addClass("meph-seo-count-warning");
      }
    });
  }

  function updatePreviews() {
    const title =
      $("#_meph_seo_title").val() ||
      $("#default_title").val() ||
      $("input[name*='default_title']").val() ||
      initialPreviewTitle ||
      mephSeoAdmin.siteName;
    const description =
      $("#_meph_seo_description").val() ||
      $("#default_description").val() ||
      $("textarea[name*='default_description']").val() ||
      initialPreviewDescription ||
      "";
    const socialTitle = $("#_meph_seo_social_title").val() || title;
    const socialDescription = $("#_meph_seo_social_description").val() || description;

    $("[data-preview-title]").text(renderTemplateValue(title));
    $("[data-preview-description]").text(renderTemplateValue(description));
    $("[data-social-preview-title]").text(renderTemplateValue(socialTitle));
    $("[data-social-preview-description]").text(renderTemplateValue(socialDescription));
  }

  function updateLocalizedTemplatePanels() {
    $("[data-meph-template-language]").each(function () {
      const $select = $(this);
      const language = $select.val();
      const $block = $select.closest(".meph-seo-localized-template-block");

      $block.find("[data-meph-template-panel]").addClass("is-hidden");
      $block
        .find('[data-meph-template-panel="' + language + '"]')
        .removeClass("is-hidden");
    });
  }

  function getVariableTarget($button) {
    const $localizedBlock = $button.closest(".meph-seo-localized-template-block");

    if ($localizedBlock.length) {
      const $activePanel = $localizedBlock.find(
        "[data-meph-template-panel]:not(.is-hidden)",
      );
      const $focused = $activePanel.find("input[type='text'], textarea").filter(":focus");

      return $focused.length
        ? $focused.first()
        : $activePanel.find("input[type='text'], textarea").first();
    }

    return $button
      .closest(".meph-verification-field")
      .find("input[type='text'], textarea")
      .first();
  }

  function init() {
    initialPreviewTitle = $("[data-preview-title]").first().text();
    initialPreviewDescription = $("[data-preview-description]").first().text();

    updateLocalizedTemplatePanels();
    updateCounters();
    updatePreviews();
    updateOrganizationPreview();
    updateRobotsPreview();

    $(document).on("input", ".meph-seo-counted, #title", function () {
      updateCounters();
      updatePreviews();
      updateOrganizationPreview();
      updateRobotsPreview();
    });

    $(document).on(
      "input change",
      "#organization_name, #local_business_type, #local_phone, #same_as",
      updateOrganizationPreview,
    );

    $(document).on("change", "[data-meph-template-language]", function () {
      updateLocalizedTemplatePanels();
      updateCounters();
    });

    $(document).on("change", ".meph-switch input", function () {
      updateRobotsPreview();
    });

    $("#meph-seo-recommended-settings").on("click", function (event) {
      event.preventDefault();
      applyOptionSettings(mephSeoAdmin.recommendedSettings || {});
    });

    $("#meph-seo-restore-defaults").on("click", function (event) {
      event.preventDefault();
      applyOptionSettings(mephSeoAdmin.defaultSettings || {}, {
        disableSwitches: true,
      });
    });

    $(document).on("click", ".meph-seo-variable", function (event) {
      event.preventDefault();
      const variable = $(this).data("variable");
      const $field = getVariableTarget($(this));

      if (!$field.length) {
        return;
      }

      const field = $field.get(0);
      const start = field.selectionStart || $field.val().length;
      const end = field.selectionEnd || $field.val().length;
      const value = $field.val();
      $field
        .val(value.slice(0, start) + variable + value.slice(end))
        .trigger("input")
        .focus();
      field.setSelectionRange(start + variable.length, start + variable.length);
    });

    $(document).on("click", ".meph-seo-media-button", function (event) {
      event.preventDefault();
      const target = $(this).data("target");
      const frame = wp.media({
        title: "Select image",
        button: { text: "Use image" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        const attachment = frame.state().get("selection").first().toJSON();
        $("#" + target).val(attachment.url).trigger("input");
        $('[data-image-preview-for="' + target + '"]').css(
          "background-image",
          "url('" + attachment.url + "')",
        );

        if (target === "_meph_seo_social_image" || target === "_meph_seo_image") {
          $("[data-social-image-preview]").css(
            "background-image",
            "url('" + attachment.url + "')",
          );
        }
      });

      frame.open();
    });

    $(document).on("click", ".meph-seo-remove-redirect", function (event) {
      event.preventDefault();
      const $tbody = $(this).closest("tbody");
      const $row = $(this).closest("tr");

      if ($tbody.find("tr").length <= 1) {
        $row.find("input").val("");
        $row.find("select").val("301");
        return;
      }

      $row.remove();
    });

    $(document).on("click", ".meph-seo-apply-product-template", function (event) {
      event.preventDefault();
      const target = $(this).data("target");
      const template = $(this).data("template");
      $("#" + target).val(template).trigger("input").focus();
    });
  }

  $(document).ready(init);
})(jQuery);
