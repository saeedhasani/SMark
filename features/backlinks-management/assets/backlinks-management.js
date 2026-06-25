/* global SMarkBacklinksManagement */
(function ($) {
  "use strict";

  var cfg = window.SMarkBacklinksManagement || {};
  var ajaxUrl = window.ajaxurl || cfg.ajaxUrl || "";
  var isFa = String(cfg.lang || "").toLowerCase() === "fa";
  var i18n = window.BMT_I18N || {};

  var t = {
    loading: isFa ? "در حال بارگذاری…" : "Loading…",
    failedLoad: isFa ? "خطا در بارگذاری لینک‌ها." : "Failed to load links.",
    confirmBulkDelete: isFa ? "آیا از حذف آیتم‌های انتخاب‌شده مطمئن هستید؟" : "Delete selected items?",
    selectProjectFirst: isFa ? "لطفاً ابتدا پروژه را انتخاب کنید." : "Please select a project first.",
    filterApplied: "✅",
    saving: i18n.saving || (isFa ? "در حال ذخیره…" : "Saving..."),
    addProspects: i18n.addProspects || (isFa ? "افزودن پروسپکت" : "Add Prospects"),
    analyzing: isFa ? "در حال تحلیل..." : "Analyzing...",
    analysisDone: isFa ? "تحلیل تموم شد" : "Analysis completed.",
    analysisFailed: isFa ? "خطا در تحلیل" : "Analysis failed.",
    eachLineOneLink:
      i18n.eachLineOneLink ||
      (isFa ? "هر خط باید فقط شامل یک لینک معتبر باشد." : "Each line must contain only one valid link."),
    invalidUrl: i18n.invalidUrl || (isFa ? "URL نامعتبر: " : "Invalid URL: "),
    linksSaved:
      i18n.linksSaved || (isFa ? "✅ لینک‌ها با موفقیت ذخیره شدند!" : "✅ Links saved successfully!"),
    opportunitiesEmpty: isFa ? "Ø¨Ø±Ø§ÛŒ Ù…Ø´Ø§Ù‡Ø¯Ù‡ ÙØ±ØµØªâ€ŒÙ‡Ø§ ÛŒÚ© ØµÙØ­Ù‡ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯." : "Select a page to see backlink opportunities.",
  };
  if (isFa) {
    t.loading = "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0631\u06af\u0630\u0627\u0631\u06cc...";
    t.failedLoad = "\u062e\u0637\u0627 \u062f\u0631 \u0628\u0627\u0631\u06af\u0630\u0627\u0631\u06cc \u0644\u06cc\u0646\u06a9\u200c\u0647\u0627.";
    t.confirmBulkDelete = "\u0622\u06cc\u0627 \u0627\u0632 \u062d\u0630\u0641 \u0622\u06cc\u062a\u0645\u200c\u0647\u0627\u06cc \u0627\u0646\u062a\u062e\u0627\u0628\u200c\u0634\u062f\u0647 \u0645\u0637\u0645\u0626\u0646 \u0647\u0633\u062a\u06cc\u062f\u061f";
    t.selectProjectFirst = "\u0644\u0637\u0641\u0627\u064b \u0627\u0628\u062a\u062f\u0627 \u067e\u0631\u0648\u0698\u0647 \u0631\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f.";
    t.filterApplied = "\u2705";
    t.saving = i18n.saving || "\u062f\u0631 \u062d\u0627\u0644 \u0630\u062e\u06cc\u0631\u0647...";
    t.addProspects = i18n.addProspects || "\u0627\u0641\u0632\u0648\u062f\u0646 \u067e\u0631\u0627\u0633\u067e\u06a9\u062a";
    t.analyzing = "\u062f\u0631 \u062d\u0627\u0644 \u0622\u0646\u0627\u0644\u06cc\u0632...";
    t.analysisDone = "\u0622\u0646\u0627\u0644\u06cc\u0632 \u062a\u0645\u0627\u0645 \u0634\u062f";
    t.analysisFailed = "\u062e\u0637\u0627 \u062f\u0631 \u0622\u0646\u0627\u0644\u06cc\u0632";
    t.eachLineOneLink = i18n.eachLineOneLink || "\u0647\u0631 \u062e\u0637 \u0628\u0627\u06cc\u062f \u0641\u0642\u0637 \u0634\u0627\u0645\u0644 \u06cc\u06a9 \u0644\u06cc\u0646\u06a9 \u0645\u0639\u062a\u0628\u0631 \u0628\u0627\u0634\u062f.";
    t.invalidUrl = i18n.invalidUrl || "URL \u0646\u0627\u0645\u0639\u062a\u0628\u0631: ";
    t.linksSaved = i18n.linksSaved || "\u2705 \u0644\u06cc\u0646\u06a9\u200c\u0647\u0627 \u0628\u0627 \u0645\u0648\u0641\u0642\u06cc\u062a \u0630\u062e\u06cc\u0631\u0647 \u0634\u062f\u0646\u062f!";
    t.opportunitiesEmpty = "\u0628\u0631\u0627\u06cc \u0645\u0634\u0627\u0647\u062f\u0647 \u0641\u0631\u0635\u062a\u200c\u0647\u0627 \u06cc\u06a9 \u0635\u0641\u062d\u0647 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f.";
  }

  var tableColumnCount = 7;
  var statusClassNames = "bmt-status-pending bmt-status-in-progress bmt-status-acquired bmt-status-rejected";

  function syncStatusSelectColor($select) {
    var status = String($select.val() || "").replace(/_/g, "-");
    $select.removeClass(statusClassNames);
    if (status) {
      $select.addClass("bmt-status-" + status);
    }
  }

  function syncStatusSelectColors($scope) {
    ($scope || $(document))
      .find('select[name="status[]"], #bmt-filter-status')
      .each(function () {
        syncStatusSelectColor($(this));
      });
  }

  function findTargetOptionByLabel(label) {
    var normalized = String(label || "").trim().toLowerCase();
    if (!normalized) return null;

    var match = null;
    $("#bmt-target-pages-list option").each(function () {
      var value = String($(this).attr("value") || "").trim().toLowerCase();
      if (value === normalized) {
        match = {
          id: parseInt($(this).data("id"), 10) || 0,
          label: String($(this).attr("value") || ""),
        };
        return false;
      }
    });
    return match;
  }

  function findTargetOptionById(id) {
    var normalizedId = parseInt(id, 10);
    if (!Number.isFinite(normalizedId) || normalizedId <= 0) return null;

    var match = null;
    $("#bmt-target-pages-list option").each(function () {
      var optionId = parseInt($(this).data("id"), 10) || 0;
      if (optionId === normalizedId) {
        match = {
          id: optionId,
          label: String($(this).attr("value") || ""),
        };
        return false;
      }
    });
    return match;
  }

  function readPickerIds($picker) {
    var raw = String($picker.find(".bmt-target-page-ids").val() || "").trim();
    if (!raw) return [];
    return raw
      .split(",")
      .map(function (value) {
        return parseInt(value, 10);
      })
      .filter(function (value) {
        return Number.isFinite(value) && value > 0;
      });
  }

  function writePickerIds($picker, ids) {
    ids = (ids || []).filter(function (value, index, arr) {
      return Number.isFinite(value) && value > 0 && arr.indexOf(value) === index;
    });
    $picker.find(".bmt-target-page-ids").val(ids.join(","));
  }

  function renderPickerTag(option) {
    if (!option || !option.id) return "";
    return (
      '<button type="button" class="bmt-target-tag" data-id="' +
      String(option.id) +
      '">' +
      '<span class="bmt-target-tag-label">' +
      String(option.label || "") +
      "</span>" +
      '<span class="bmt-target-tag-remove">\u00d7</span>' +
      "</button>"
    );
  }

  function addTargetToPicker($picker, option) {
    if (!$picker || !$picker.length || !option || !option.id) return false;

    var ids = readPickerIds($picker);
    if (ids.indexOf(option.id) !== -1) {
      $picker.find(".bmt-target-page-input").val("");
      return false;
    }

    ids.push(option.id);
    writePickerIds($picker, ids);
    $picker.find(".bmt-target-page-tags").append(renderPickerTag(option));
    $picker.find(".bmt-target-page-input").val("");
    return true;
  }

  function saveTargetPicker($picker) {
    var linkId = parseInt($picker.data("link-id"), 10);
    if (!Number.isFinite(linkId) || linkId <= 0) {
      return;
    }

    var ids = readPickerIds($picker);
    var $input = $picker.find(".bmt-target-page-input");
    $input.prop("disabled", true);

    $.post(ajaxUrl, {
      action: "bmt_update_target_page",
      link_id: linkId,
      target_post_ids: ids,
    })
      .fail(function () {
        showToast(t.failedLoad, "error");
        reloadLinks();
      })
      .always(function () {
        $input.prop("disabled", false).val("");
      });
  }

  function syncTargetInputState($input) {
    if (!$input || !$input.length) return null;

    var value = String($input.val() || "").trim();
    var $picker = $input.closest(".bmt-target-page-filter");
    var $hidden = $picker.find("#bmt-filter-target-page");
    var match = findTargetOptionByLabel(value);

    if (match) {
      $hidden.val(String(match.id));
      $input.val(match.label);
      return match;
    }

    if (!value) {
      $hidden.val("0");
      return { id: 0, label: "" };
    }

    $hidden.val("0");
    return null;
  }

  function normalizeUrlInput(raw) {
    var s = String(raw || "").trim();
    if (!s) return "";

    s = s.replace(/^[\s"'`]+|[\s"'`]+$/g, "");

    // Strip leading slashes if it looks like a domain was pasted with "/" prefix (e.g. "/example.com/path").
    var stripped = s.replace(/^\/+/, "");
    if (stripped !== s && /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(stripped)) {
      s = stripped;
    }

    if (/^\/\//.test(s)) {
      s = "https:" + s;
    }

    if (/^www\./i.test(s)) {
      s = "https://" + s;
    }

    // If scheme is missing but it looks like a hostname, default to https://
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(s) && /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(s)) {
      s = "https://" + s;
    }

    return s;
  }

  function showToast(message, type) {
    try {
      var $existing = $("#bmt-toast");
      if ($existing.length) {
        $existing.remove();
      }

      var $toast = $('<div id="bmt-toast" class="bmt-toast" role="status" aria-live="polite"></div>');
      $toast.addClass(type === "error" ? "is-error" : "is-success");
      var $body = $('<div class="bmt-toast__body"></div>').text(String(message || ""));
      var $close = $('<button type="button" class="bmt-toast__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>');
      $close.on("click", function () {
        $toast.removeClass("is-visible");
        setTimeout(function () {
          $toast.remove();
        }, 250);
      });
      $toast.append($body, $close);
      $("body").append($toast);

      setTimeout(function () {
        $toast.addClass("is-visible");
      }, 10);
    } catch (e) {}
  }

  function getSelectedLinkIds() {
    return $(".bmt-row-checkbox:checked")
      .map(function () {
        return parseInt($(this).data("id"), 10);
      })
      .get()
      .filter(function (v) {
        return Number.isFinite(v) && v > 0;
      });
  }

  function updateAnalyzeButtonState() {
    try {
      var ids = getSelectedLinkIds();
      $("#bmt-analyze-links").prop("disabled", !ids.length);
    } catch (e) {}
  }

  function getPerPage() {
    var raw = String($("#bmt-per-page").val() || "");
    var v = parseInt(raw, 10);
    if (v !== 100 && v !== 200 && v !== 500) v = 100;
    return v;
  }

  function getCurrentPage() {
    var raw = String($("#bmt_links_container .bmt-pagination").attr("data-page") || "1");
    var v = parseInt(raw, 10);
    if (!Number.isFinite(v) || v <= 0) v = 1;
    return v;
  }

  function fixFooterLayout() {
    var wpBody = document.querySelector("#wpbody");
    var wpBodyContent = document.querySelector("#wpbody-content");
    var wrap = document.querySelector(".wrap.smark-backlinks-management-page");
    var mainContent = document.querySelector(".smark-backlinks-management-content");
    var footer = document.querySelector(".smark-version-footer");

    try {
      if (wpBody && wpBodyContent) {
        wpBodyContent.style.minHeight = getComputedStyle(wpBody).height;
        wpBodyContent.style.float = "none";
        wpBodyContent.style.paddingBottom = "0";
        wpBodyContent.style.height = "";
      }

      if (wpBody && wrap) {
        wrap.style.minHeight = getComputedStyle(wpBody).height;
        wrap.style.float = "none";
        wrap.style.height = "";
      }

      if (wrap) {
        wrap.style.display = "flex";
        wrap.style.flexDirection = "column";
      }

      if (mainContent) {
        mainContent.style.flex = "1";
        mainContent.style.display = "flex";
        mainContent.style.flexDirection = "column";
        mainContent.style.minHeight = "0";
      }

      if (footer) {
        footer.style.marginTop = "auto";
      }
    } catch (e) {}
  }

  function ensureProjectSelected(projectId) {
    try {
      var $sel = $("#bmt_project");
      if (!$sel.length || !projectId) {
        return;
      }
      if ($sel.find('option[value="' + projectId + '"]').length === 0) {
        $sel.append($("<option/>").val(projectId).text(String(projectId)));
      }
      $sel.val(String(projectId));
    } catch (e) {}
  }

  function getProjectId() {
    var pid = "";
    try {
      pid = String($("#bmt_project").val() || "").trim();
    } catch (e) {}
    if (!pid && window.BMT_EMBEDDED && window.BMT_EMBEDDED.projectId) {
      pid = String(window.BMT_EMBEDDED.projectId);
    }
    return pid;
  }

  function reloadLinks(opts) {
    var filterOutreach = String($("#bmt-filter-outreach").val() || "");
    var filterStatus = String($("#bmt-filter-status").val() || "");
    var filterAnalysis = String($("#bmt-filter-analysis").val() || "");
    var filterTargetPostId = parseInt($("#bmt-filter-target-page").val(), 10);
    if (!Number.isFinite(filterTargetPostId) || filterTargetPostId < 0) filterTargetPostId = 0;
    var perPage = getPerPage();
    var page =
      opts && Object.prototype.hasOwnProperty.call(opts, "page")
        ? parseInt(opts.page, 10)
        : getCurrentPage();
    if (!Number.isFinite(page) || page <= 0) page = 1;

    var data = {
      action: "bmt_get_project_links",
      filter_outreach:
        opts && Object.prototype.hasOwnProperty.call(opts, "filter_outreach")
          ? opts.filter_outreach
          : filterOutreach,
      filter_status:
        opts && Object.prototype.hasOwnProperty.call(opts, "filter_status")
          ? opts.filter_status
          : filterStatus,
      filter_analysis:
        opts && Object.prototype.hasOwnProperty.call(opts, "filter_analysis")
          ? opts.filter_analysis
          : filterAnalysis,
      filter_target_post_id:
        opts && Object.prototype.hasOwnProperty.call(opts, "filter_target_post_id")
          ? opts.filter_target_post_id
          : filterTargetPostId,
      per_page: perPage,
      page: page,
    };

    try {
      $("#bmt-table-controls").show();
      $("#bmt_links_container").html(
        '<table id="bmt-prospect-table" class="widefat fixed striped"><tbody><tr><td colspan="' + tableColumnCount + '">' +
          t.loading +
          "</td></tr></tbody></table>"
      );
    } catch (e) {}

    $.post(ajaxUrl, data)
      .done(function (resp) {
        $("#bmt_links_container").html(resp);
        syncStatusSelectColors($("#bmt_links_container"));
        updateAnalyzeButtonState();
        loadOpportunities();
      })
      .fail(function () {
        $("#bmt_links_container").html(
          '<table id="bmt-prospect-table" class="widefat fixed striped"><tbody><tr><td colspan="' + tableColumnCount + '">' +
            t.failedLoad +
            "</td></tr></tbody></table>"
        );
        updateAnalyzeButtonState();
      });
  }

  function loadOpportunities() {
    var targetPostId = parseInt($("#bmt-opportunities-target-id").val(), 10);
    if (!Number.isFinite(targetPostId) || targetPostId <= 0) {
      $("#bmt-opportunities-results").html("");
      return;
    }

    $("#bmt-opportunities-results").html(
      '<div class="bmt-opportunities-empty">' + t.loading + "</div>"
    );

    $.post(ajaxUrl, {
      action: "bmt_get_opportunities",
      target_post_id: targetPostId,
    })
      .done(function (resp) {
        $("#bmt-opportunities-results").html(resp);
      })
      .fail(function () {
        $("#bmt-opportunities-results").html(
          '<div class="bmt-opportunities-empty">' + t.failedLoad + "</div>"
        );
      });
  }

  function getInitialTargetPostId() {
    var id = parseInt(cfg.initialTargetPostId || 0, 10);
    if (!Number.isFinite(id) || id <= 0) {
      id = parseInt((window.BMT_EMBEDDED && window.BMT_EMBEDDED.initialTargetPostId) || 0, 10);
    }
    if (!Number.isFinite(id) || id <= 0) {
      try {
        var params = new URLSearchParams(window.location.search || "");
        id = parseInt(params.get("target_post_id") || "0", 10);
      } catch (e) {
        id = 0;
      }
    }
    return Number.isFinite(id) && id > 0 ? id : 0;
  }

  function applyInitialTargetFilter() {
    var targetPostId = getInitialTargetPostId();
    if (!targetPostId) {
      return;
    }

    var match = findTargetOptionById(targetPostId);
    var label = match && match.label ? match.label : "";

    $("#bmt-filter-target-page").val(String(targetPostId));
    $("#bmt-opportunities-target-id").val(String(targetPostId));
    if (label) {
      $("#bmt-filter-target-page-label").val(label);
      $("#bmt-opportunities-target-label").val(label);
    }
  }

  function closePopup($el) {
    try {
      $el.fadeOut(150);
    } catch (e) {
      $el.hide();
    }
  }

  function openPopup($el) {
    try {
      $el.fadeIn(150);
    } catch (e) {
      $el.show();
    }
  }

  function bindBmtHandlers() {
    // Add prospects popup.
    $(document).on("click", "#bmt-add-prospects", function () {
      openPopup($("#bmt-popup"));
    });
    $(document).on("click", "#bmt-close-popup", function () {
      closePopup($("#bmt-popup"));
    });

    // Submit links (prospects).
    $(document).on("click", "#bmt-submit-links", function () {
      var $btn = $("#bmt-submit-links");
      $btn.prop("disabled", true).text(t.saving);

      var textarea = String($("#bmt-link-textarea").val() || "").trim();
      var lines = textarea.split(/[\r\n]+/).filter(function (line) {
        return String(line || "").trim() !== "";
      });

      var hasMultiLinks = lines.some(function (line) {
        var count = (String(line || "").match(/https?:\/\//g) || []).length;
        return count > 1;
      });

      if (hasMultiLinks) {
        $("#bmt-error").text(t.eachLineOneLink).show();
        $btn.prop("disabled", false).text(t.addProspects);
        return;
      }

      $("#bmt-error").hide();
      $("#bmt-success").hide();

      var payloadLinks = lines
        .map(function (url) {
          try {
            var original = String(url || "").trim();
            var normalized = normalizeUrlInput(original);
            var link = new URL(normalized);
            var domain = String(link.hostname || "").replace(/^www\./i, "");
            if (!domain) {
              return null;
            }
            return {
              domain: domain,
              example: normalized,
              comment: "Add Your Comment!",
              outreach_strategy: "email",
              status: "pending",
            };
          } catch (e) {
            $("#bmt-error").text(t.invalidUrl + url).show();
            return null;
          }
        })
        .filter(Boolean);

      if (!payloadLinks.length) {
        $btn.prop("disabled", false).text(t.addProspects);
        return;
      }

      $.post(ajaxUrl, {
        action: "bmt_save_prospects",
        links: payloadLinks,
      })
        .done(function (response) {
          $btn.prop("disabled", false).text(t.addProspects);
          if (response && response.success) {
            $("#bmt-success").text(t.linksSaved).show();
            $("#bmt-link-textarea").val("");
            closePopup($("#bmt-popup"));
            reloadLinks();
          } else {
            var msg =
              response && response.data && response.data.message
                ? String(response.data.message)
                : t.failedLoad;
            $("#bmt-error").text(msg).show();
          }
        })
        .fail(function () {
          $btn.prop("disabled", false).text(t.addProspects);
          $("#bmt-error").text(t.failedLoad).show();
        });
    });

    // Edit URL.
    $(document).on("click", ".bmt-edit-url", function () {
      var id = String($(this).data("id") || "");
      var url = String($(this).data("url") || "");
      $("#bmt-edit-link-id").val(id);
      $("#bmt-edit-url-input").val(url);
      openPopup($("#bmt-edit-popup"));
    });
    $(document).on("click", "#bmt-edit-close", function () {
      closePopup($("#bmt-edit-popup"));
    });
    $(document).on("click", "#bmt-save-url-btn", function () {
      var linkId = String($("#bmt-edit-link-id").val() || "");
      var newUrl = String($("#bmt-edit-url-input").val() || "").trim();
      if (!linkId || !newUrl) {
        return;
      }
      $.post(ajaxUrl, {
        action: "bmt_update_link_url",
        link_id: linkId,
        new_url: newUrl,
      }).done(function () {
        closePopup($("#bmt-edit-popup"));
        reloadLinks();
      });
    });

    // Edit comment.
    $(document).on("click", ".bmt-edit-comment", function () {
      var id = String($(this).data("id") || "");
      var comment = String($(this).data("comment") || "");
      $("#bmt-edit-comment-id").val(id);
      $("#bmt-edit-comment-input").val(comment);
      openPopup($("#bmt-comment-popup"));
    });
    $(document).on("click", "#bmt-comment-close", function () {
      closePopup($("#bmt-comment-popup"));
    });
    $(document).on("click", "#bmt-save-comment-btn", function () {
      var linkId = String($("#bmt-edit-comment-id").val() || "");
      var newComment = String($("#bmt-edit-comment-input").val() || "");
      if (!linkId) {
        return;
      }
      $.post(ajaxUrl, {
        action: "bmt_update_link_comment",
        link_id: linkId,
        new_comment: newComment,
      }).done(function () {
        closePopup($("#bmt-comment-popup"));
        reloadLinks();
      });
    });

    // Update outreach + status (server-side).
    $(document).on(
      "change",
      'select[name="outreach_strategy[]"], select[name="status[]"]',
      function () {
        var $row = $(this).closest("tr");
        var linkId = String($row.find(".bmt-row-checkbox").data("id") || "");
        if (!linkId) {
          return;
        }

        var outreach = String($row.find('select[name="outreach_strategy[]"]').val() || "");
        if (outreach === "__add_new__") {
          return;
        }
        var status = String($row.find('select[name="status[]"]').val() || "");
        syncStatusSelectColor($row.find('select[name="status[]"]'));

        $.post(ajaxUrl, {
          action: "bmt_update_outreach_status",
          link_id: linkId,
          outreach_strategy: outreach,
          status: status,
        });
      }
    );

    $(document).on("input", ".bmt-target-page-filter .bmt-target-page-input", function () {
      syncTargetInputState($(this));
    });

    $(document).on("input", "#bmt-opportunities-target-label", function () {
      var $input = $(this);
      var match = findTargetOptionByLabel($input.val());
      $("#bmt-opportunities-target-id").val(match ? String(match.id) : "0");
    });

    $(document).on("change", "#bmt-opportunities-target-label", function () {
      var $input = $(this);
      var match = findTargetOptionByLabel($input.val());
      if (match) {
        $input.val(match.label);
        $("#bmt-opportunities-target-id").val(String(match.id));
      } else if (!String($input.val() || "").trim()) {
        $("#bmt-opportunities-target-id").val("0");
      }
      loadOpportunities();
    });

    $(document).on("change", ".bmt-target-page-picker .bmt-target-page-input", function () {
      var $input = $(this);
      var $picker = $input.closest(".bmt-target-page-picker");
      var match = findTargetOptionByLabel($input.val());
      if (!match) {
        return;
      }
      var added = addTargetToPicker($picker, match);
      if (added) {
        saveTargetPicker($picker);
      }
    });

    $(document).on("click", ".bmt-target-tag", function () {
      var $tag = $(this);
      var $picker = $tag.closest(".bmt-target-page-picker");
      var removeId = parseInt($tag.data("id"), 10);
      var ids = readPickerIds($picker).filter(function (id) {
        return id !== removeId;
      });
      writePickerIds($picker, ids);
      $tag.remove();
      saveTargetPicker($picker);
    });

    // Bulk actions: select all.
    $(document).on("change", "#bmt-check-all", function () {
      var checked = !!$(this).prop("checked");
      $(".bmt-row-checkbox").prop("checked", checked);
      updateAnalyzeButtonState();
    });

    $(document).on("change", ".bmt-row-checkbox", function () {
      updateAnalyzeButtonState();
    });

    // Analyze selected backlinks.
    $(document).on("click", "#bmt-analyze-links", function () {
      var ids = getSelectedLinkIds();
      if (!ids.length) {
        updateAnalyzeButtonState();
        return;
      }

      var $btn = $("#bmt-analyze-links");
      var originalText = String($btn.text() || "");
      $btn.prop("disabled", true).text(t.analyzing);

      $.post(ajaxUrl, { action: "bmt_analyze_links", link_ids: ids })
        .done(function (resp) {
          if (resp && resp.success) {
            reloadLinks();
            try {
              if (resp.data && Array.isArray(resp.data.errors) && resp.data.errors.length) {
                showToast(t.analysisFailed, "error");
              } else {
                showToast(t.analysisDone, "success");
              }
            } catch (e) {
              showToast(t.analysisDone, "success");
            }
          } else {
            showToast(t.analysisFailed, "error");
          }
        })
        .fail(function () {
          showToast(t.analysisFailed, "error");
        })
        .always(function () {
          $btn.text(originalText);
          updateAnalyzeButtonState();
        });
    });

    // Remove labels.
    $(document).on("click", ".bmt-label", function () {
      var linkId = parseInt($(this).data("id"), 10);
      var label = String($(this).data("label") || "");
      if (!Number.isFinite(linkId) || linkId <= 0 || !label) {
        return;
      }
      $.post(ajaxUrl, { action: "bmt_remove_link_label", link_id: linkId, label: label }).done(function () {
        reloadLinks();
      });
    });

    // Bulk actions: apply.
    $(document).on("click", "#bmt-bulk-apply", function () {
      var action = String($("#bmt-bulk-select").val() || "");
      if (action !== "delete") {
        return;
      }

      var ids = $(".bmt-row-checkbox:checked")
        .map(function () {
          return parseInt($(this).data("id"), 10);
        })
        .get()
        .filter(function (v) {
          return Number.isFinite(v) && v > 0;
        });

      if (!ids.length) {
        return;
      }

      if (!window.confirm(t.confirmBulkDelete)) {
        return;
      }

      $("#bmt-bulk-status").html(
        '<span class="spinner is-active" style="float:none;margin:0;"></span>'
      );
      $.post(ajaxUrl, {
        action: "bmt_bulk_delete_links",
        link_ids: ids,
      }).done(function (resp) {
        if (resp && resp.success) {
          reloadLinks();
        }
        $("#bmt-bulk-status").html('<span class="bmt-checkmark">✅</span>');
        setTimeout(function () {
          $("#bmt-bulk-status").fadeOut(300, function () {
            $(this).html("").show();
          });
        }, 2000);
      });
    });

    // Filters.
    $(document).on("change", "#bmt-filter-status", function () {
      syncStatusSelectColor($(this));
    });

    $(document).on("click", "#bmt-filter-apply", function () {
      var outreach = String($("#bmt-filter-outreach").val() || "");
      var status = String($("#bmt-filter-status").val() || "");
      var analysis = String($("#bmt-filter-analysis").val() || "");
      var filterMatch = syncTargetInputState($("#bmt-filter-target-page-label"));
      var targetPostId = filterMatch ? parseInt(filterMatch.id, 10) : 0;
      syncStatusSelectColor($("#bmt-filter-status"));

      $("#bmt-filter-status-box").html(
        '<span class="spinner is-active" style="float:none;margin:0;"></span>'
      );
      reloadLinks({ filter_outreach: outreach, filter_status: status, filter_analysis: analysis, filter_target_post_id: targetPostId, page: 1 });
      $("#bmt-filter-status-box").html(
        '<span class="bmt-checkmark">' + t.filterApplied + "</span>"
      );
      setTimeout(function () {
        $("#bmt-filter-status-box").fadeOut(300, function () {
          $(this).html("").show();
        });
      }, 2000);
    });

    // Search (client-side).
    $(document).on("click", "#bmt-search-btn", function () {
      var keyword = String($("#bmt-search-input").val() || "").toLowerCase();
      $("#bmt-prospect-table tbody tr").each(function () {
        var $tr = $(this);
        var domain = String($tr.find(".bmt-domain-title").text() || $tr.find("td:nth-child(2)").text() || "").toLowerCase();
        var url = String($tr.find(".bmt-url-text").text() || "").toLowerCase();
        var target = String($tr.find(".bmt-target-page-tags").text() || "").toLowerCase();
        var comment = String($tr.find(".bmt-comment-text").text() || "").toLowerCase();
        if (
          !keyword ||
          domain.indexOf(keyword) !== -1 ||
          url.indexOf(keyword) !== -1 ||
          target.indexOf(keyword) !== -1 ||
          comment.indexOf(keyword) !== -1
        ) {
          $tr.show();
        } else {
          $tr.hide();
        }
      });
    });

    // Per-page change.
    $(document).on("change", "#bmt-per-page", function () {
      try {
        if (window.localStorage) {
          localStorage.setItem("bmt_per_page", String(getPerPage()));
        }
      } catch (e) {}
      reloadLinks({ page: 1 });
    });

    // Pagination.
    $(document).on("click", ".bmt-page-prev", function () {
      var current = getCurrentPage();
      reloadLinks({ page: Math.max(1, current - 1) });
    });
    $(document).on("click", ".bmt-page-next", function () {
      var current = getCurrentPage();
      reloadLinks({ page: current + 1 });
    });
    $(document).on("keyup", "#bmt-search-input", function () {
      $("#bmt-search-btn").trigger("click");
    });

    // Add outreach strategy.
    var customOutreachOptions = [];

    $(document).on("change", 'select[name="outreach_strategy[]"]', function () {
      var $select = $(this);
      if (String($select.val() || "") === "__add_new__") {
        openPopup($("#bmt-outreach-popup"));
        $select.data("trigger", true);
      }
    });
    $(document).on("change", "#bmt-filter-outreach", function () {
      if (String($(this).val() || "") === "__add_new__") {
        openPopup($("#bmt-outreach-popup"));
        $(this).data("triggerFilter", true);
      }
    });

    $(document).on("click", "#bmt-close-outreach-popup", function () {
      closePopup($("#bmt-outreach-popup"));
      $("#bmt-new-outreach-input").val("");
      $('select[name="outreach_strategy[]"]').each(function () {
        if (String($(this).val() || "") === "__add_new__") {
          $(this).val("");
        }
        $(this).removeData("trigger");
      });
      if (String($("#bmt-filter-outreach").val() || "") === "__add_new__") {
        $("#bmt-filter-outreach").val("");
      }
      $("#bmt-filter-outreach").removeData("triggerFilter");
    });

    $(document).on("click", "#bmt-save-new-outreach", function () {
      var newOption = String($("#bmt-new-outreach-input").val() || "").trim();
      if (!newOption) {
        return;
      }

      var sanitizedValue = newOption.toLowerCase().replace(/\s+/g, "_");

      $.post(ajaxUrl, {
        action: "bmt_save_outreach_strategy",
        label: newOption,
        slug: sanitizedValue,
      });

      if (customOutreachOptions.indexOf(sanitizedValue) === -1) {
        customOutreachOptions.push(sanitizedValue);

        $('select[name="outreach_strategy[]"]').each(function () {
          var $sel = $(this);
          if (!$sel.find('option[value="' + sanitizedValue + '"]').length) {
            $("<option/>")
              .val(sanitizedValue)
              .text(newOption)
              .insertBefore($sel.find('option[value="__add_new__"]'));
          }
        });

        var $filterSel = $("#bmt-filter-outreach");
        if ($filterSel.length && !$filterSel.find('option[value="' + sanitizedValue + '"]').length) {
          $("<option/>")
            .val(sanitizedValue)
            .text(newOption)
            .insertBefore($filterSel.find('option[value="__add_new__"]'));
        }
      }

      $('select[name="outreach_strategy[]"]').each(function () {
        if ($(this).data("trigger")) {
          $(this).val(sanitizedValue).trigger("change");
          $(this).removeData("trigger");
        }
      });
      if ($("#bmt-filter-outreach").data("triggerFilter")) {
        $("#bmt-filter-outreach").val(sanitizedValue);
        $("#bmt-filter-outreach").removeData("triggerFilter");
      }

      $("#bmt-new-outreach-input").val("");
      closePopup($("#bmt-outreach-popup"));
    });
  }

  $(function () {
    var embedded =
      !!(window.BMT_EMBEDDED && window.BMT_EMBEDDED.embedded) ||
      $(".smark-backlinks-management-page").length > 0;
    if (embedded) {
      $("#bmt-table-controls").show();
      try {
        var saved = window.localStorage ? localStorage.getItem("bmt_per_page") : "";
        if (saved && $("#bmt-per-page").length) {
          $("#bmt-per-page").val(String(saved));
        }
      } catch (e) {}
      applyInitialTargetFilter();
      reloadLinks();
      loadOpportunities();
    }

    bindBmtHandlers();
    syncStatusSelectColors($(document));
    updateAnalyzeButtonState();

    fixFooterLayout();
    setTimeout(fixFooterLayout, 100);
    setTimeout(fixFooterLayout, 500);
    window.addEventListener("resize", function () {
      fixFooterLayout();
    });

    $(document).on("change", "#SMARK_language_select", function () {
      var language = String($(this).val() || "").trim();
      if (!cfg.ajaxUrl || !cfg.nonce || !language) {
        return;
      }
      $.post(cfg.ajaxUrl, {
        action: "SMARK_cm_save_language",
        nonce: cfg.nonce,
        language: language,
      }).done(function (resp) {
        if (resp && resp.success) {
          window.location.reload();
        }
      });
    });
  });
})(jQuery);
