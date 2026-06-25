(function ($) {
  const cfg = window.SMarkContentManagement || {};
  const strings = cfg.strings || {};
  const urlParams = new URLSearchParams(window.location.search || "");
  const focusPostId = parseInt(String(urlParams.get("focus_post_id") || "0"), 10) || 0;
  const focusKeyword = String(urlParams.get("smark_kw") || "").trim();
  const currentProjectId = parseInt(String(cfg.currentProjectId || "0"), 10) || 0;

  // Classic WordPress editor (TinyMCE/Quicktags) draft editor id in SERP modal.
  const serpDraftEditorId = "cm_serp_draft";
  let suppressDraftSave = false;
  let lastDraftSelection = { text: "", subTitle: "", headingRect: null, headingDirRtl: false, ts: 0 };

  function getTinyMceEditor() {
    try {
      if (!window.tinymce || typeof window.tinymce.get !== "function") return null;
      return window.tinymce.get(serpDraftEditorId) || null;
    } catch (e) {}
    return null;
  }

  function findClosestHeadingFromRange(rng) {
    try {
      if (!rng) return null;
      const a = rng.commonAncestorContainer || rng.startContainer || null;
      const b = rng.startContainer || null;
      const c = rng.endContainer || null;
      return findClosestHeading(a) || findClosestHeading(b) || findClosestHeading(c) || null;
    } catch (e) {}
    return null;
  }

  function readTinyMceSelection(ed) {
    const out = { collapsed: true, text: "", heading: null };
    try {
      if (!ed || !ed.selection) return out;

      const rng = ed.selection.getRng ? ed.selection.getRng() : null;
      if (!rng) return out;
      out.collapsed = !!rng.collapsed;
      out.heading = findClosestHeadingFromRange(rng);

      try {
        out.text = String(ed.selection.getContent ? ed.selection.getContent({ format: "text" }) : "").trim();
      } catch (e) {
        out.text = "";
      }
    } catch (e) {}
    return out;
  }

  function getCookie(name) {
    const n = String(name || "");
    if (!n) return "";
    const parts = String(document.cookie || "").split(";");
    for (const p of parts) {
      const kv = p.trim();
      if (!kv) continue;
      const idx = kv.indexOf("=");
      if (idx <= 0) continue;
      const k = kv.slice(0, idx).trim();
      if (k === n) return kv.slice(idx + 1);
    }
    return "";
  }

  function setCookie(name, value, days) {
    const n = String(name || "");
    if (!n) return;
    const v = String(value || "");
    const d = typeof days === "number" && days > 0 ? days : 7;
    const expires = new Date(Date.now() + d * 24 * 60 * 60 * 1000).toUTCString();
    document.cookie = `${n}=${v}; expires=${expires}; path=/wp-admin/; samesite=lax`;
  }

  function getDraftKey(postId) {
    const pid = parseInt(String(postId || "0"), 10) || 0;
    return `smark_cm_serp_draft_${String(currentProjectId || 0)}_${String(pid || 0)}`;
  }

  function hashKeyPart(input) {
    const s = String(input || "");
    let h = 5381;
    for (let i = 0; i < s.length; i++) {
      h = ((h << 5) + h) ^ s.charCodeAt(i);
      h = h >>> 0;
    }
    return h.toString(16);
  }

  function getTitleKey(postId, keyword) {
    const pid = parseInt(String(postId || "0"), 10) || 0;
    const kw = String(keyword || "").trim();
    return `smark_cm_serp_title_${String(currentProjectId || 0)}_${String(pid || 0)}_${hashKeyPart(kw)}`;
  }

  function loadSerpTitle(postId, keyword) {
    const key = getTitleKey(postId, keyword);
    if (!key) return "";

    try {
      const raw = window.localStorage ? window.localStorage.getItem(key) : "";
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed.title === "string") {
          return parsed.title;
        }
      }
    } catch (e) {}

    try {
      const rawCookie = getCookie(key);
      if (!rawCookie || rawCookie === "LS") return "";
      return decodeURIComponent(rawCookie);
    } catch (e) {}

    return "";
  }

  function saveSerpTitle(postId, keyword, title) {
    const key = getTitleKey(postId, keyword);
    if (!key) return;
    const t = String(title || "");
    const payload = JSON.stringify({ title: t, updatedAt: Date.now() });

    try {
      if (window.localStorage) {
        window.localStorage.setItem(key, payload);
      }
    } catch (e) {}

    try {
      const encoded = encodeURIComponent(t);
      if (encoded.length <= 3500) {
        setCookie(key, encoded, 7);
      } else {
        setCookie(key, "LS", 7);
      }
    } catch (e) {}
  }

  function getCurrentSerpKeyword() {
    return String($("#cmSerpKeywordValue").text() || "").trim();
  }

  function resizeSerpTitleTextarea() {
    const el = document.getElementById(serpContentTitleInputId);
    if (!el) return;
    try {
      el.style.height = "auto";
      const next = Math.min(Math.max(el.scrollHeight || 0, 38), 120);
      el.style.height = `${next}px`;
    } catch (e) {}
  }

  function restoreSerpTitleIntoInput() {
    if (serpMode !== "create") return;
    const pid = parseInt(String(currentReviewPostId || "0"), 10) || 0;
    const kw = getCurrentSerpKeyword();
    if (!pid && !kw) return;

    const saved = loadSerpTitle(pid, kw);
    if (saved) {
      try {
        $(`#${serpContentTitleInputId}`).val(saved);
      } catch (e) {}
      resizeSerpTitleTextarea();
    }
  }

  function saveSerpTitleFromInput() {
    if (serpMode !== "create") return;
    const pid = parseInt(String(currentReviewPostId || "0"), 10) || 0;
    const kw = getCurrentSerpKeyword();
    if (!pid && !kw) return;
    const v = String($(`#${serpContentTitleInputId}`).val() || "");
    saveSerpTitle(pid, kw, v);
  }

  function loadDraft(postId) {
    const key = getDraftKey(postId);
    if (!key) return "";

    // Prefer localStorage (no size limit like cookies).
    try {
      const raw = window.localStorage ? window.localStorage.getItem(key) : "";
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed.content === "string") {
          return parsed.content;
        }
      }
    } catch (e) {}

    // Fallback to cookie (may be truncated).
    try {
      const rawCookie = getCookie(key);
      if (!rawCookie || rawCookie === "LS") return "";
      return decodeURIComponent(rawCookie);
    } catch (e) {}

    return "";
  }

  function saveDraft(postId, html) {
    const key = getDraftKey(postId);
    if (!key) return;
    const content = String(html || "");
    const payload = JSON.stringify({ content: content, updatedAt: Date.now() });

    try {
      if (window.localStorage) {
        window.localStorage.setItem(key, payload);
      }
    } catch (e) {}

    // Cookies are limited (~4KB). Store if small; otherwise store a marker and rely on localStorage.
    try {
      const encoded = encodeURIComponent(content);
      if (encoded.length <= 3500) {
        setCookie(key, encoded, 7);
      } else {
        setCookie(key, "LS", 7);
      }
    } catch (e) {}
  }

  let draftSaveTimer = null;
  function scheduleDraftSave() {
    clearTimeout(draftSaveTimer);
    draftSaveTimer = setTimeout(() => {
      if (suppressDraftSave) return;
      const pid = parseInt(String(currentReviewPostId || "0"), 10) || 0;
      if (pid <= 0) return;

      const html = getDraftHtml();

      saveDraft(pid, html);
    }, 250);
  }

  let draftMetaTimer = null;
  function scheduleDraftMetaRefresh() {
    clearTimeout(draftMetaTimer);
    draftMetaTimer = setTimeout(() => {
      if (suppressDraftSave) return;
      try {
        updateDraftHeadingsSet();
        refreshUsageBadges();
      } catch (e) {}
    }, 250);
  }

  function saveCurrentDraftNow() {
    try {
      if (suppressDraftSave) return;
      const pid = parseInt(String(currentReviewPostId || "0"), 10) || 0;
      if (pid <= 0) return;
      saveDraft(pid, getDraftHtml());
    } catch (e) {}
  }

  function applyUpdatedAtStatus($cell, rawDateTime) {
    $cell.removeClass("is-fresh is-stale");
    if (!rawDateTime) {
      return;
    }

    const normalized = String(rawDateTime).replace(" ", "T");
    const updatedAt = new Date(normalized);
    if (isNaN(updatedAt.getTime())) {
      return;
    }

    const now = new Date();
    const diffMs = now.getTime() - updatedAt.getTime();
    const diffDays = diffMs / (1000 * 60 * 60 * 24);

    // > 6 months ~ 183 days
    if (diffDays > 183) {
      $cell.addClass("is-stale");
    } else {
      $cell.addClass("is-fresh");
    }
  }

  function showNotification(message, type) {
    const t = type || "info";
    let $notice = $(".smark-notification");
    if (!$notice.length) {
      $notice = $('<div class="smark-notification" role="status" aria-live="polite" />').appendTo("body");
    }
    $notice
      .removeClass("success error info visible rtl")
      .addClass(t)
      .empty();

    const $body = $('<div class="smark-notification__body" />').text(message);
    const $close = $(
      '<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>'
    );
    $close.on("click", () => {
      clearTimeout($notice.data("timeout"));
      $notice.removeClass("visible");
      setTimeout(() => {
        if (!$notice.hasClass("visible")) {
          $notice.remove();
        }
      }, 300);
    });
    $notice.append($body, $close).addClass("visible");

    const isRTL =
      $(".wrap.smark-content-management-page").hasClass("rtl") ||
      $(".wrap.smark-content-management-page").attr("data-lang") === "fa";
    if (isRTL) {
      $notice.addClass("rtl");
    }

    clearTimeout($notice.data("timeout"));
    $notice.data("timeout", null);
  }

  function request(action, data, method) {
    const m = method || "GET";
    const payload = Object.assign({}, data || {}, {
      action: action,
      nonce: cfg.nonce,
    });
    return $.ajax({
      url: cfg.ajaxUrl,
      method: m,
      data: m === "GET" ? payload : $.param(payload),
      dataType: "json",
    });
  }

  function ensureWpLinkDialogDetached() {
    // WordPress prints the link dialog markup inside #wpfooter, but this page hides #wpfooter.
    // Detach it to body so link edit/remove works inside our custom modal + TinyMCE.
    try {
      const $wrap = $("#wp-link-wrap");
      if ($wrap.length && !$wrap.parent().is("body")) {
        $("body").append($wrap);
      }

      // If backdrop exists, keep it as a direct child of body as well.
      const $backdrop = $("#wp-link-backdrop");
      if ($backdrop.length && !$backdrop.parent().is("body")) {
        $("body").append($backdrop);
      }
    } catch (e) {}
  }

  function fixFooterLayout() {
    const wpBody = document.querySelector("#wpbody");
    const wpBodyContent = document.querySelector("#wpbody-content");
    const wrap = document.querySelector(".wrap.smark-content-management-page");
    const mainContent = document.querySelector(".smark-content-management-content");
    const footer = document.querySelector(".smark-version-footer");

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

  function renderSelected(items) {
    const $tbody = $("#cmSelectedTable tbody");
    const $empty = $("#cmEmptyState");
    $tbody.empty();

    if (!items || !items.length) {
      $empty.show();
      $("#cmSelectedTable").hide();
      return;
    }

    $empty.hide();
    $("#cmSelectedTable").show();

    items.forEach((item) => {
      const title = item.title || "(no title)";
      const type = item.typeLabel || item.type || "";
      const status = item.status || "";
      const updatedAt = item.updatedAt || "";
      const $tr = $("<tr/>");
      const $titleCell = $("<td/>");
      if (item.editUrl) {
        $titleCell.append(
          $("<a/>", {
            href: item.editUrl,
            text: title,
            target: "_blank",
            rel: "noopener noreferrer",
          })
        );
      } else {
        $titleCell.text(title);
      }
      $tr.append($titleCell);
      $tr.append($("<td/>").text(type));
      $tr.append($("<td/>").text(status));

      const $updatedCell = $("<td/>").addClass("cm-updated-at").text(updatedAt);
      applyUpdatedAtStatus($updatedCell, updatedAt);
      $tr.append($updatedCell);

      const $actions = $("<div/>").addClass("cm-actions");
      const $removeBtn = $("<button/>", {
        type: "button",
        class: "cm-action-btn cm-remove",
        text: strings.remove || "Remove",
      }).attr("data-id", String(item.id));

      // Show "Review for editing" if flagged by Keyword Research OR user came from the KR "Edit content" action.
      const needsReview = !!item.needsReview;
      const isFocused =
        focusPostId > 0 && parseInt(String(item.id || "0"), 10) === focusPostId;
      const isFresh = $updatedCell.hasClass("is-fresh");
      if ((needsReview || isFocused) && !isFresh) {
        const reviewKeywords = Array.isArray(item.reviewKeywords)
          ? item.reviewKeywords.map((k) => String(k || "").trim()).filter(Boolean)
          : [];
        const $reviewBtn = $("<button/>", {
          type: "button",
          class: "cm-action-btn cm-review",
          text: strings.review_edit || "Review",
        })
          .attr("data-edit-url", String(item.editUrl || ""))
          .attr("data-view-url", String(item.viewUrl || ""))
          .attr("data-post-id", String(item.id || ""))
          .attr("data-keywords", reviewKeywords.join("||"))
          .attr("data-smark-cost", "1")
          .attr("title", cfg.lang === "fa" ? "شامل ۱ مارک" : "Includes 1 Mark");
        try {
          $reviewBtn.append($("<span/>", { class: "cm-smark-cost-badge", text: "1" }).attr("aria-hidden", "true"));
        } catch (e) {}
        $actions.append($reviewBtn);

        const $findInfographicBtn = $("<button/>", {
          type: "button",
          class: "cm-action-btn cm-find-infographic",
          text: strings.find_infographic || (isRTL ? "پیدا کردن اینفوگرافیک" : "Find infographic"),
        }).attr("data-post-id", String(item.id || ""));
        $actions.append($findInfographicBtn);
      }

      $actions.append($removeBtn);
      $tr.append($("<td/>").append($actions));
      $tbody.append($tr);
    });
  }

  let selectedItemsCache = [];
  let createItemsCache = [];

  function getSelectedFilterQuery() {
    return String($("#cmSelectedSearch").val() || "").trim().toLowerCase();
  }

  function applySelectedFilter() {
    const q = getSelectedFilterQuery();

    let base = selectedItemsCache;
    if (focusPostId > 0) {
      base = base.filter((item) => parseInt(String(item.id || "0"), 10) === focusPostId);
    }

    if (!q) {
      renderSelected(base);
      return;
    }

    const filtered = base.filter((item) => {
      const title = String(item.title || "").toLowerCase();
      const type = String(item.typeLabel || item.type || "").toLowerCase();
      const status = String(item.status || "").toLowerCase();
      return title.includes(q) || type.includes(q) || status.includes(q);
    });
    renderSelected(filtered);
  }

  function loadSelected() {
    return request(
      "SMARK_cm_get_selected",
      { focus_post_id: focusPostId || 0, smark_kw: focusKeyword || "" },
      "GET"
    )
      .done((res) => {
        if (!res || !res.success) {
          showNotification(strings.error || "Error", "error");
          return;
        }
        selectedItemsCache = (res.data && res.data.items) || [];
        applySelectedFilter();
      })
      .fail(() => showNotification(strings.error || "Error", "error"));
  }

  function renderCreateItems(items) {
    const $tbody = $("#cmCreateTable tbody");
    const $empty = $("#cmCreateEmptyState");
    $tbody.empty();

    if (!items || !items.length) {
      $empty.show();
      $("#cmCreateTable").hide();
      return;
    }

    $empty.hide();
    $("#cmCreateTable").show();

    const postTypes = Array.isArray(cfg.postTypes) ? cfg.postTypes : [];
    const typeOptions = postTypes.filter((t) => t && t.name && t.name !== "all");

    items.forEach((item) => {
      const keyword = String(item.keyword || "").trim();
      if (!keyword) return;

      const postType = String(item.postType || "post");
      const status = String(item.status || "");
      const updatedAt = String(item.updatedAt || "");
      const editUrl = String(item.editUrl || "");
      const postId = parseInt(String(item.postId || "0"), 10) || 0;

      const $tr = $("<tr/>");
      const $titleCell = $("<td/>");
      if (editUrl) {
        $titleCell.append(
          $("<a/>", {
            href: editUrl,
            text: keyword,
            target: "_blank",
            rel: "noopener noreferrer",
          })
        );
      } else {
        $titleCell.text(keyword);
      }
      $tr.append($titleCell);

      const isCreatedDraft = postId > 0 && String(status || "").toLowerCase() === "draft";
      if (isCreatedDraft) {
        const matched = typeOptions.find((t) => String(t.name) === postType);
        const label = matched ? String(matched.label || matched.name) : postType;
        $tr.append($("<td/>").text(label));
      } else {
        const $typeSelect = $("<select/>", { class: "cm-create-type" })
          .attr("data-keyword", keyword)
          .attr("aria-label", "Type");

        typeOptions.forEach((t) => {
          $typeSelect.append($("<option/>", { value: String(t.name), text: String(t.label || t.name) }));
        });
        $typeSelect.val(postType);
        $tr.append($("<td/>").append($typeSelect));
      }

      $tr.append($("<td/>").text(status || "—"));

      const $updatedCell = $("<td/>").addClass("cm-updated-at").text(updatedAt || "—");
      if (updatedAt) {
        applyUpdatedAtStatus($updatedCell, updatedAt);
      }
      $tr.append($updatedCell);

      const $actions = $("<div/>").addClass("cm-actions");
      if (postId <= 0) {
        const $createBtn = $("<button/>", {
          type: "button",
          class: "cm-action-btn cm-create-item",
          text: cfg.lang === "fa" ? "ایجاد آیتم" : "Create item",
        }).attr("data-keyword", keyword);
        $actions.append($createBtn);
      } else if (editUrl) {
        if (String(status || "").toLowerCase() === "draft") {
          const $writeBtn = $("<button/>", {
            type: "button",
            class: "cm-action-btn cm-review cm-write-content",
            text: cfg.lang === "fa" ? "نگارش محتوا" : "Write content",
          })
            .attr("data-edit-url", editUrl)
            .attr("data-view-url", "")
            .attr("data-post-id", String(postId || ""))
            .attr("data-keywords", keyword)
            .attr("data-smark-cost", "1")
            .attr("title", cfg.lang === "fa" ? "شامل ۱ مارک" : "Includes 1 Mark");
          try {
            $writeBtn.append($("<span/>", { class: "cm-smark-cost-badge", text: "1" }).attr("aria-hidden", "true"));
          } catch (e) {}
          $actions.append($writeBtn);
        }

        const $openBtn = $("<a/>", {
          class: "cm-action-btn cm-open-item",
          text: cfg.lang === "fa" ? "ویرایش" : "Edit",
          href: editUrl,
          target: "_blank",
          rel: "noopener noreferrer",
        });
        $actions.append($openBtn);
      }

      $tr.append($("<td/>").append($actions));
      $tbody.append($tr);
    });
  }

  function getCreateFilterQuery() {
    return String($("#cmCreateSearch").val() || "").trim().toLowerCase();
  }

  function applyCreateFilter() {
    const q = getCreateFilterQuery();
    if (!q) {
      renderCreateItems(createItemsCache);
      return;
    }

    const filtered = (createItemsCache || []).filter((it) => {
      const k = String(it.keyword || "").toLowerCase();
      const t = String(it.postType || "").toLowerCase();
      const s = String(it.status || "").toLowerCase();
      return k.includes(q) || t.includes(q) || s.includes(q);
    });
    renderCreateItems(filtered);
  }

  function loadCreateItems() {
    return request("SMARK_cm_get_create_items", {}, "GET")
      .done((res) => {
        if (!res || !res.success) {
          showNotification(strings.error || "Error", "error");
          return;
        }
        createItemsCache = (res.data && res.data.items) || [];
        applyCreateFilter();

        const moved = (res.data && res.data.moved_post_ids) || [];
        if (Array.isArray(moved) && moved.length) {
          loadSelected();
          showNotification(cfg.lang === "fa" ? "آیتم‌های پابلیش‌شده به مدیریت محتوا منتقل شدند." : "Published items moved to Content Management.", "success");
        }
      })
      .fail(() => showNotification(strings.error || "Error", "error"));
  }

  function addCreateItemFromQueryIfAny() {
    try {
      const params = new URLSearchParams(window.location.search || "");
      const kw = String(params.get("cm_create_kw") || "").trim();
      if (!kw) return;

      request("SMARK_cm_add_create_item", { keyword: kw }, "POST")
        .done((res) => {
          if (!res || !res.success) {
            const msg =
              (res && res.data && res.data.message) ||
              (cfg.lang === "fa" ? "این مورد از قبل موجود است." : "Already exists.");
            showNotification(String(msg), "error");
            return;
          }
          showNotification(cfg.lang === "fa" ? "به ایجاد محتوا اضافه شد." : "Added to content creation.", "success");
          loadCreateItems();
        })
        .fail((xhr) => {
          const status = xhr && xhr.status ? parseInt(String(xhr.status), 10) : 0;
          if (status === 503) {
            const msg =
              (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
              (cfg.lang === "fa"
                ? "ارتباط با سرور مرکزی برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید."
                : "Could not connect to the central server. Please try again in a moment.");
            showNotification(String(msg), "warning");
            return;
          }
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (cfg.lang === "fa" ? "این مورد از قبل موجود است." : "Already exists.");
          showNotification(String(msg), "error");
        })
        .always(() => {
          try {
            params.delete("cm_create_kw");
            const qs = params.toString();
            const next = `${window.location.pathname}${qs ? "?" + qs : ""}`;
            window.history.replaceState({}, "", next);
          } catch (e) {}
        });
    } catch (e) {}
  }

  function openModal() {
    $("#cmPickerModal").addClass("is-open").attr("aria-hidden", "false");
  }

  function closeModal() {
    $("#cmPickerModal").removeClass("is-open").attr("aria-hidden", "true");
  }

  const serpContentTitleBlockId = "cmSerpContentTitleBlock";
  const serpContentTitleInputId = "cmSerpContentTitleInput";
  let serpMode = "edit"; // "edit" | "create"

  function setSerpMode(mode) {
    serpMode = String(mode || "").toLowerCase() === "create" ? "create" : "edit";

    const $modal = $("#cmSerpModal");
    $modal.attr("data-cm-mode", serpMode);

    const $block = $(`#${serpContentTitleBlockId}`);
    if ($block.length) {
      const isCreate = serpMode === "create";
      $block.toggle(isCreate);
      if (!isCreate) {
        try {
          $(`#${serpContentTitleInputId}`).val("");
        } catch (e) {}
      }
    }
  }

  function openSerpModal() {
    $("#cmSerpModal").addClass("is-open").attr("aria-hidden", "false");
    ensureWpLinkDialogDetached();
  }

  function closeSerpModal() {
    // Persist draft before closing/clearing UI state.
    try {
      saveCurrentDraftNow();
    } catch (e) {}

    try {
      saveSerpTitleFromInput();
    } catch (e) {}

    $("#cmSerpModal").removeClass("is-open").attr("aria-hidden", "true");
    $("#cmSerpLoading").hide();
    $("#cmSerpError").hide().text("");
    $("#cmSerpResults").empty();
    $("#cmSerpKeywordValue").text("");
    currentReviewPostId = 0;
    $("#cmSerpAiWrite").hide();
    setSerpMode("edit");

    try {
      suppressDraftSave = true;
      setDraftHtml("");
    } catch (e) {}
    suppressDraftSave = false;

    draftHeadingsSet = new Set();
  }

  function setSerpLoading(loading) {
    $("#cmSerpLoading").toggle(!!loading);
  }

  function setSerpError(message) {
    const msg = String(message || "").trim();
    if (!msg) {
      $("#cmSerpError").hide().text("");
      return;
    }
    $("#cmSerpError").show().text(msg);
  }

  let serpRenderToken = 0;
  let currentReviewPostId = 0;
  const ourHeadingsCache = new Map(); // postId -> Set(normalized heading text)
  let draftHeadingsSet = new Set(); // normalized heading text present in editor draft

  function normalizeHeadingText(text) {
    return String(text || "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
  }

  function getOurHeadingsSet() {
    return ourHeadingsCache.get(currentReviewPostId) || new Set();
  }

  function extractHeadingsFromHtml(html) {
    const src = String(html || "");
    if (!src) return [];

    const out = [];
    const re = /<h([1-6])[^>]*>([\s\S]*?)<\/h\1>/gi;
    let m;
    while ((m = re.exec(src))) {
      const txt = $("<div/>").html(m[2]).text();
      const normalized = normalizeHeadingText(txt);
      if (normalized) out.push(normalized);
      if (out.length >= 200) break;
    }
    return out;
  }

  function getDraftHtml() {
    try {
      const ed = getTinyMceEditor();
      if (ed && typeof ed.getContent === "function" && !ed.isHidden()) {
        return String(ed.getContent({ format: "raw" }) || "");
      }
    } catch (e) {}

    try {
      const ta = document.getElementById(serpDraftEditorId);
      if (ta) return String(ta.value || "");
    } catch (e) {}

    return "";
  }

  function setDraftHtml(html) {
    const content = String(html || "");

    try {
      const ta = document.getElementById(serpDraftEditorId);
      if (ta) ta.value = content;
    } catch (e) {}

    try {
      const ed = getTinyMceEditor();
      if (ed && typeof ed.setContent === "function") {
        ed.setContent(content);
      }
    } catch (e) {}
  }

  function updateDraftHeadingsSet() {
    const html = getDraftHtml();
    draftHeadingsSet = new Set(extractHeadingsFromHtml(html));
  }

  function isHeadingUsedInDraft(text) {
    const normalized = normalizeHeadingText(text);
    if (!normalized) return false;
    return draftHeadingsSet.has(normalized);
  }

  function countHeadings(headings) {
    const list = Array.isArray(headings) ? headings : [];
    return list.filter((h) => h && String(h.text || "").trim()).length;
  }

  function renderHeadingsList(headings) {
    const list = Array.isArray(headings) ? headings : [];
    const $ul = $("<ul/>").addClass("cm-serp-headings-list");
    const ourSet = getOurHeadingsSet();

    list.forEach((h) => {
      const level = String(h.level || "").toLowerCase();
      const text = String(h.text || "").trim();
      if (!text) return;
      const cls = level === "h1" ? "cm-serp-h1" : level === "h2" ? "cm-serp-h2" : "cm-serp-h3";
      const badgeText = level === "h1" ? "H1" : level === "h2" ? "H2" : "H3";
      const $li = $("<li/>").addClass(cls);
      $li.append($("<span/>").addClass("cm-serp-h-badge").text(badgeText));

      const isDraftUsed = isHeadingUsedInDraft(text);
      const isUsed = ourSet.has(normalizeHeadingText(text));
      const $useBadge = $("<span/>")
        .addClass("cm-serp-use-badge")
        .addClass(isDraftUsed ? "is-draft-used" : isUsed ? "is-used" : "is-todo")
        .text(isDraftUsed ? (strings.used || "Used") : isUsed ? (strings.used || "Used") : (strings.use || "Use"));
      $li.append($useBadge);

      $li.append($("<span/>").addClass("cm-serp-h-text").text(text));
      $ul.append($li);
    });

    return $ul;
  }

  function insertIntoEditor(html) {
    const content = String(html || "");
    if (!content) return;

    try {
      const ed = getTinyMceEditor();
      if (ed && typeof ed.execCommand === "function" && !ed.isHidden()) {
        ed.execCommand("mceInsertContent", false, content);
        if (typeof ed.nodeChanged === "function") {
          ed.nodeChanged();
        }
        scheduleDraftSave();
        updateDraftHeadingsSet();
        refreshUsageBadges();
        return;
      }
    } catch (e) {}

    try {
      const ta = document.getElementById(serpDraftEditorId);
      if (!ta) return;

      const start = typeof ta.selectionStart === "number" ? ta.selectionStart : ta.value.length;
      const end = typeof ta.selectionEnd === "number" ? ta.selectionEnd : ta.value.length;
      const before = ta.value.slice(0, start);
      const after = ta.value.slice(end);
      ta.value = before + content + after;
      ta.focus();
      const nextPos = start + content.length;
      if (typeof ta.setSelectionRange === "function") {
        ta.setSelectionRange(nextPos, nextPos);
      }

      scheduleDraftSave();
      updateDraftHeadingsSet();
      refreshUsageBadges();
    } catch (e) {}
  }

  function ensureAiWriteButton() {
    if ($("#cmSerpAiWrite").length) return;
    const $btn = $("<button/>", {
      type: "button",
      id: "cmSerpAiWrite",
      class: "cm-serp-ai-write",
      text: (cfg.lang === "fa" ? "نگارش AI" : "AI Write"),
    });

    $btn.on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      let selectedText = "";
      let subTitle = "";
      let draftHtml = "";

      try {
        // Prefer cached selection from TinyMCE (button click usually blurs the editor).
        if (lastDraftSelection && Date.now() - (lastDraftSelection.ts || 0) < 30000) {
          selectedText = String(lastDraftSelection.text || "").trim();
          subTitle = String(lastDraftSelection.subTitle || "").trim();
        }

        if (!selectedText || !subTitle) {
          const ed = getTinyMceEditor();
          if (ed && !ed.isHidden()) {
            const sel = readTinyMceSelection(ed);
            selectedText = selectedText || String(sel.text || "").trim();
            const heading = sel.heading;
            if (heading && !subTitle) {
              subTitle = String(heading.textContent || "").replace(/\s+/g, " ").trim();
            }
          }
        }

        if (!selectedText || !subTitle) {
          const sel = window.getSelection ? window.getSelection() : null;
          if (sel && sel.rangeCount > 0) {
            selectedText = selectedText || String(sel.toString() || "").trim();
            const heading = findClosestHeading(sel.anchorNode);
            if (heading && !subTitle) {
              subTitle = String(heading.textContent || "").replace(/\s+/g, " ").trim();
            }
          }
        }
      } catch (e) {}

      draftHtml = getDraftHtml();

      const keyword = String($("#cmSerpKeywordValue").text() || "").trim();

      request(
        "SMARK_cm_ai_write_seo_content",
        {
          post_id: currentReviewPostId || 0,
          keyword: keyword,
          selected_text: selectedText,
          sub_title: subTitle || selectedText,
          draft_html: draftHtml,
        },
        "POST"
      )
        .done(async (res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }

          const manualAi = !!(res.data && parseInt(String(res.data.manual_ai || "0"), 10));
          if (!manualAi) {
            showNotification(strings.ai_manual_required || (cfg.lang === "fa" ? "این پروژه هوش مصنوعی دستی ندارد." : "Manual AI is not enabled."), "info");
            return;
          }

          const prompt = String((res.data && res.data.prompt) || "");
          if (!prompt) {
            showNotification(strings.ai_prompt_not_found || (cfg.lang === "fa" ? "پرامپت پیدا نشد." : "Prompt not found."), "error");
            return;
          }

          await openChatGptWithPrompt(prompt);
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    $("body").append($btn);
  }

  async function openChatGptWithPrompt(prompt) {
    const p = String(prompt || "");
    if (!p) return;

    try {
      // Always include the prompt in the URL so ChatGPT opens prefilled.
      // (The prompt is also copied to clipboard as a fallback.)
      const url = `https://chatgpt.com/?q=${encodeURIComponent(p)}`;
      window.open(url, "_blank", "noopener,noreferrer");
    } catch (err) {}

    let copied = false;
    try {
      if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
        await navigator.clipboard.writeText(p);
        copied = true;
      }
    } catch (err) {}

    if (!copied) {
      try {
        window.prompt(cfg.lang === "fa" ? "پرامپت را کپی کنید:" : "Copy the prompt:", p);
      } catch (err) {}
    }

    showNotification(strings.ai_opened_chatgpt || (cfg.lang === "fa" ? "ChatGPT باز شد؛ پرامپت کپی شد." : "ChatGPT opened; prompt copied."), "success");
  }

  function findClosestHeading(node) {
    let el = node;
    for (let i = 0; i < 8 && el; i++) {
      if (el.nodeType === 1) {
        const tag = String(el.tagName || "").toLowerCase();
        if (/^h[1-6]$/.test(tag)) return el;
      }
      el = el.parentNode;
    }
    return null;
  }

  function isRtlText(text) {
    return /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/.test(String(text || ""));
  }

  function updateAiWriteButtonFromTinyMce(ed) {
    const $btn = $("#cmSerpAiWrite");
    if (!$btn.length) return false;

    try {
      const sel = readTinyMceSelection(ed);
      if (!sel || sel.collapsed) {
        return false;
      }

      const heading = sel.heading;
      if (!heading) {
        return false;
      }

      const iframe = document.getElementById(`${serpDraftEditorId}_ifr`);
      const iframeRect = iframe ? iframe.getBoundingClientRect() : { top: 0, left: 0 };
      const rect = heading.getBoundingClientRect();
      const dirRtl = isRtlText(heading.textContent);

      lastDraftSelection = {
        text: String(sel.text || "").trim(),
        subTitle: String(heading.textContent || "").replace(/\s+/g, " ").trim(),
        headingRect: rect,
        headingDirRtl: dirRtl,
        ts: Date.now(),
      };

      const top = iframeRect.top + rect.top + 2;
      const left = iframeRect.left + (dirRtl ? rect.left + 4 : rect.right - 4);
      $btn.css({ top: `${top}px`, left: `${left}px`, transform: dirRtl ? "none" : "translateX(-100%)" });
      $btn.show();
      return true;
    } catch (e) {}

    return false;
  }

  function updateAiWriteButton() {
    const $btn = $("#cmSerpAiWrite");
    if (!$btn.length) return;

    if (!$("#cmSerpModal").hasClass("is-open")) {
      $btn.hide();
      return;
    }

    try {
      // Prefer TinyMCE selection (Visual tab) even if editor lost focus (clicking the button).
      const ed = getTinyMceEditor();
      if (ed && !ed.isHidden() && updateAiWriteButtonFromTinyMce(ed)) return;

      // Fallback: selection in main document (Text tab, or outside editor)
      const sel = window.getSelection ? window.getSelection() : null;
      if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
        $btn.hide();
        return;
      }

      const heading = findClosestHeading(sel.anchorNode);
      if (!heading) {
        $btn.hide();
        return;
      }

      const rect = heading.getBoundingClientRect();
      const top = rect.top + 2;
      const dirRtl = isRtlText(heading.textContent);
      $btn.css({
        top: `${top}px`,
        left: `${dirRtl ? rect.left + 4 : rect.right - 4}px`,
        transform: dirRtl ? "none" : "translateX(-100%)",
      });
      $btn.show();
    } catch (e) {
      $btn.hide();
    }
  }

  function restoreDraftIntoEditor() {
    const pid = parseInt(String(currentReviewPostId || "0"), 10) || 0;
    if (pid <= 0) return;

    const draft = loadDraft(pid);
    if (!draft) return;

    function isEffectivelyEmptyHtml(html) {
      const src = String(html || "").trim();
      if (!src) return true;

      try {
        const div = document.createElement("div");
        div.innerHTML = src;

        // If there's any meaningful embedded content, consider non-empty.
        if (div.querySelector("img,iframe,video,audio,table,ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,pre")) {
          return false;
        }

        const text = String(div.textContent || "")
          .replace(/\u00a0/g, " ")
          .replace(/\u200b/g, "")
          .replace(/\s+/g, " ")
          .trim();
        return !text;
      } catch (e) {}

      // Fallback: crude strip.
      return !src.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim();
    }

    // Only restore if editor is empty to avoid overwriting current content.
    const apply = () => {
      try {
        const ta = document.getElementById(serpDraftEditorId);
        if (!ta) return false;

        const current = String(getDraftHtml() || "").trim();
        if (isEffectivelyEmptyHtml(current)) {
          setDraftHtml(draft);
          updateDraftHeadingsSet();
          refreshUsageBadges();
        }
        return true;
      } catch (e) {}
      return false;
    };

    // TinyMCE may not be ready immediately; retry briefly.
    let attempts = 0;
    const tick = () => {
      attempts += 1;
      const ok = apply();
      if (!ok && attempts < 20) {
        setTimeout(tick, 150);
      }
    };
    tick();
  }

  function toggleResultHeadings($result, open) {
    const $panel = $result.find(".cm-serp-headings-panel").first();
    const $btn = $result.find(".cm-serp-headings-badge").first();
    const shouldOpen = typeof open === "boolean" ? open : !$panel.hasClass("is-open");

    if (shouldOpen) {
      $panel.addClass("is-open").attr("aria-hidden", "false");
      $btn.attr("aria-expanded", "true");
    } else {
      $panel.removeClass("is-open").attr("aria-hidden", "true");
      $btn.attr("aria-expanded", "false");
    }
  }

  function fetchAndRenderHeadingsForResult($result, renderToken) {
    const url = String($result.attr("data-url") || "").trim();
    if (!url) return;

    if ($result.data("headingsLoaded") || $result.data("headingsLoading")) {
      return;
    }

    $result.data("headingsLoading", true);
    const $badge = $result.find(".cm-serp-headings-badge").first();
    $badge.addClass("is-loading").text("…");

    request("SMARK_cm_fetch_headings", { url: url }, "GET")
      .done((res) => {
        if (renderToken !== serpRenderToken) return;
        if (!res || !res.success) {
          throw new Error("failed");
        }
        const headings = (res.data && res.data.headings) || [];
        $result.data("headingsLoaded", true);
        $result.data("headingsLoading", false);
        $result.data("headings", headings);

        const n = countHeadings(headings);
        $badge.removeClass("is-loading").text(String(n));

        const $panel = $result.find(".cm-serp-headings-panel").first();
        $panel.empty().append(renderHeadingsList(headings));
      })
      .fail((xhr) => {
        if (renderToken !== serpRenderToken) return;
        $result.data("headingsLoaded", true);
        $result.data("headingsLoading", false);
        $badge.removeClass("is-loading").text("—");
        const msg =
          (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
          strings.error ||
          "Error";
        const $panel = $result.find(".cm-serp-headings-panel").first();
        $panel.empty().append($("<div/>").addClass("cm-serp-headings-error").text(String(msg)));
      });
  }

  function prefetchHeadings(items, renderToken) {
    const urls = (Array.isArray(items) ? items : [])
      .map((it) => String((it && it.link) || "").trim())
      .filter(Boolean);

    const seen = new Set();
    const queue = urls.filter((u) => (seen.has(u) ? false : (seen.add(u), true)));

    let active = 0;
    const max = 3;

    function next() {
      if (renderToken !== serpRenderToken) return;
      while (active < max && queue.length) {
        const url = queue.shift();
        const $result = $(`.cm-serp-result[data-url="${CSS.escape(url)}"]`).first();
        if (!$result.length) continue;
        active += 1;
        $result.data("headingsLoading", true);
        const $badge = $result.find(".cm-serp-headings-badge").first();
        $badge.addClass("is-loading").text("…");

        request("SMARK_cm_fetch_headings", { url: url }, "GET")
          .done((res) => {
            if (renderToken !== serpRenderToken) return;
            const headings = (res && res.success && res.data && res.data.headings) || [];
            $result.data("headingsLoaded", true);
            $result.data("headingsLoading", false);
            $result.data("headings", headings);
            $badge.removeClass("is-loading").text(String(countHeadings(headings)));
            const $panel = $result.find(".cm-serp-headings-panel").first();
            $panel.empty().append(renderHeadingsList(headings));
          })
          .fail(() => {
            if (renderToken !== serpRenderToken) return;
            $result.data("headingsLoaded", true);
            $result.data("headingsLoading", false);
            $badge.removeClass("is-loading").text("—");
          })
          .always(() => {
            active -= 1;
            next();
          });
      }
    }

    next();
  }

  function renderSerpResults(items) {
    const $wrap = $("#cmSerpResults");
    $wrap.empty();

    const list = Array.isArray(items) ? items : [];
    if (!list.length) {
      $wrap.append($("<div/>").addClass("cm-serp-empty").text(strings.serp_no_results || "No results"));
      return;
    }

    const renderToken = ++serpRenderToken;

    list.forEach((item, idx) => {
      const link = String(item.link || "").trim();
      const title = String(item.title || "").trim();

      const $card = $("<div/>").addClass("cm-serp-result");
      const $row = $("<div/>").addClass("cm-serp-result__row");
      $row.append($("<div/>").addClass("cm-serp-index").text(String(idx + 1)));

      $card.attr("data-url", link);

      if (link) {
        $row.append(
          $("<a/>", {
            class: "cm-serp-url",
            href: link,
            target: "_blank",
            rel: "noopener noreferrer",
            text: link,
          })
        );
      } else {
        $row.append($("<div/>").addClass("cm-serp-url").text("(no url)"));
      }

      const $badge = $("<button/>", {
        type: "button",
        class: "cm-serp-headings-badge",
        text: "…",
      })
        .attr("aria-expanded", "false")
        .attr("title", "Headings");
      $row.append($badge);

      $card.append($row);

      if (title) {
        $card.append($("<div/>").addClass("cm-serp-title").text(title));
      }

      const $panel = $("<div/>", { class: "cm-serp-headings-panel" })
        .attr("aria-hidden", "true");
      $card.append($panel);

      $wrap.append($card);
    });

    // Prefetch headings in background (throttled)
    prefetchHeadings(list, renderToken);
  }

  function refreshUsageBadges() {
    const ourSet = getOurHeadingsSet();
    if (!ourSet || !ourSet.size) {
      // still re-render to apply draft usage changes
    }

    $(".cm-serp-result").each(function () {
      const $result = $(this);
      const headings = $result.data("headings");
      if (!Array.isArray(headings)) return;
      const $panel = $result.find(".cm-serp-headings-panel").first();
      if (!$panel.length) return;
      $panel.empty().append(renderHeadingsList(headings));
    });
  }

  function loadOurHeadings(postId) {
    const pid = parseInt(String(postId || "0"), 10) || 0;
    if (pid <= 0) {
      return $.Deferred().resolve().promise();
    }

    if (ourHeadingsCache.has(pid)) {
      return $.Deferred().resolve().promise();
    }

    return request("SMARK_cm_get_post_headings", { post_id: pid }, "GET")
      .done((res) => {
        if (!res || !res.success) return;
        const list = (res.data && res.data.headings) || [];
        const set = new Set(list.map((t) => normalizeHeadingText(t)).filter(Boolean));
        ourHeadingsCache.set(pid, set);
        refreshUsageBadges();
      })
      .fail(() => {});
  }

  function bindDraftEditorWatchers() {
    const onDraftChange = () => {
      scheduleDraftSave();
      scheduleDraftMetaRefresh();
    };

    // Text tab watcher (textarea input).
    $(document).off("input.smarkSerpDraft keyup.smarkSerpDraft", `#${serpDraftEditorId}`);
    $(document).on("input.smarkSerpDraft keyup.smarkSerpDraft", `#${serpDraftEditorId}`, function () {
      onDraftChange();
    });

    // Selection watcher (for AI Write button positioning).
    $(document).off("selectionchange.smarkSerpSel");
    $(document).on("selectionchange.smarkSerpSel", function () {
      updateAiWriteButton();
    });

    // Visual tab watcher (TinyMCE).
    let attempts = 0;
    const bindTiny = () => {
      const ed = getTinyMceEditor();
      if (!ed) return false;
      if (ed.__smarkBound) return true;
      ed.__smarkBound = true;

      const onAny = () => {
        onDraftChange();
        updateAiWriteButton();
      };

      try {
        ed.on("keyup change NodeChange SetContent Undo Redo mouseup touchend SelectionChange", onAny);
      } catch (e) {}

      return true;
    };

    const tick = () => {
      attempts += 1;
      if (bindTiny() || attempts >= 20) return;
      setTimeout(tick, 150);
    };
    tick();
  }

  function loadSerpForKeyword(keyword) {
    const kw = String(keyword || "").trim();
    if (!kw) {
      setSerpError(strings.error || "Error");
      return $.Deferred().reject().promise();
    }

    setSerpError("");
    setSerpLoading(true);
    const renderToken = ++serpRenderToken;
    return request("SMARK_cm_serp_preview", { keyword: kw }, "GET")
      .done((res) => {
        if (renderToken !== serpRenderToken) return;
        if (!res || !res.success) {
          const msg =
            (res && res.data && res.data.message) ||
            strings.error ||
            "Error";
          if (String(msg).toLowerCase().includes("not configured")) {
            setSerpError(strings.serp_not_configured || msg);
          } else {
            setSerpError(msg);
          }
          renderSerpResults([]);
          return;
        }
        const items = (res.data && res.data.items) || [];
        renderSerpResults(items);
      })
      .fail((xhr) => {
        if (renderToken !== serpRenderToken) return;
        const msg =
          (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
          strings.error ||
          "Error";
        if (String(msg).toLowerCase().includes("not configured")) {
          setSerpError(strings.serp_not_configured || msg);
        } else {
          setSerpError(msg);
        }
        renderSerpResults([]);
      })
      .always(() => setSerpLoading(false));
  }

  function setLoading(loading) {
    $("#cmLoading").toggle(!!loading);
  }

  function renderResults(items) {
    const $tbody = $("#cmResultsTable tbody");
    $tbody.empty();
    (items || []).forEach((item) => {
      const $tr = $("<tr/>");
      const $check = $('<input type="checkbox" class="cm-select" />').val(
        String(item.id)
      );
      $tr.append($("<td/>").addClass("cm-col-check").append($check));
      $tr.append($("<td/>").text(item.title || "(no title)"));
      $tr.append($("<td/>").text(item.typeLabel || item.type || ""));
      $tr.append($("<td/>").text(item.status || ""));
      const updatedAt = item.updatedAt || "";
      const $updatedCell = $("<td/>").addClass("cm-updated-at").text(updatedAt);
      applyUpdatedAtStatus($updatedCell, updatedAt);
      $tr.append($updatedCell);
      $tbody.append($tr);
    });
  }

  function loadResults() {
    const q = String($("#cmSearchInput").val() || "").trim();
    const postType = String($("#cmTypeFilter").val() || "all").trim() || "all";
    setLoading(true);
    return request("SMARK_cm_search_content", { q: q, post_type: postType }, "GET")
      .done((res) => {
        if (!res || !res.success) {
          showNotification(strings.error || "Error", "error");
          return;
        }
        renderResults((res.data && res.data.items) || []);
      })
      .fail(() => showNotification(strings.error || "Error", "error"))
      .always(() => setLoading(false));
  }

  function addSelected() {
    const ids = $(".cm-select:checked")
      .map(function () {
        return parseInt(String($(this).val() || "0"), 10);
      })
      .get()
      .filter((id) => id > 0);

    if (!ids.length) {
      closeModal();
      return;
    }

    return request("SMARK_cm_add_selected", { ids: ids }, "POST")
      .done((res) => {
        if (!res || !res.success) {
          showNotification(strings.error || "Error", "error");
          return;
        }
        showNotification(strings.added || "Added", "success");
        closeModal();
        loadSelected();
      })
      .fail(() => showNotification(strings.error || "Error", "error"));
  }

  function removeSelected(id) {
    return request("SMARK_cm_remove_selected", { id: id }, "POST")
      .done((res) => {
        if (!res || !res.success) {
          showNotification(strings.error || "Error", "error");
          return;
        }
        showNotification(strings.removed || "Removed", "success");
        loadSelected();
      })
      .fail(() => showNotification(strings.error || "Error", "error"));
  }

  function initPostTypeFilter() {
    const $sel = $("#cmTypeFilter");
    $sel.empty();
    (cfg.postTypes || []).forEach((pt) => {
      $sel.append($("<option/>").val(pt.name).text(pt.label));
    });
  }

  $(function () {
    ensureWpLinkDialogDetached();
    initPostTypeFilter();
    if (focusKeyword) {
      $("#cmSelectedSearch").val(focusKeyword);
    }
    loadSelected();
    loadCreateItems();
    addCreateItemFromQueryIfAny();
    fixFooterLayout();
    setTimeout(fixFooterLayout, 100);
    setTimeout(fixFooterLayout, 500);
    window.addEventListener("resize", function () {
      fixFooterLayout();
    });

    $(document).on("change", "#SMARK_language_select", function () {
      const language = String($(this).val() || "").trim();
      if (!cfg.ajaxUrl || !cfg.nonce || !language) {
        return;
      }
      $.post(cfg.ajaxUrl, {
        action: "SMARK_cm_save_language",
        nonce: cfg.nonce,
        language: language,
      })
        .done(function (resp) {
          if (resp && resp.success) {
            window.location.reload();
          }
        })
        .fail(function () {
          showNotification(strings.error || "Error", "error");
        });
    });

    $(document).on("click", ".cm-open-picker", function () {
      openModal();
      loadResults();
    });

    let selectedSearchTimeout = null;
    $(document).on("input", "#cmSelectedSearch", function () {
      clearTimeout(selectedSearchTimeout);
      selectedSearchTimeout = setTimeout(applySelectedFilter, 100);
    });

    let createSearchTimeout = null;
    $(document).on("input", "#cmCreateSearch", function () {
      clearTimeout(createSearchTimeout);
      createSearchTimeout = setTimeout(applyCreateFilter, 100);
    });

    // Picker modal close
    $(document).on("click", "#cmPickerModal .cm-modal__overlay, #cmPickerModal .cm-modal__close, #cmCancel", function () {
      closeModal();
    });

    // SERP modal close
    $(document).on("click", "#cmSerpModal .cm-modal__overlay, [data-cm-close=\"serp\"]", function () {
      closeSerpModal();
    });

    // SEO title AI (Content title field) - uses Prompt Bank key: seo_title
    $(document).on("click", "#cmSerpContentTitleAiBtn", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Only available in "create" mode (new content writing window)
      if (serpMode !== "create") return;

      const keyword = String($("#cmSerpKeywordValue").text() || "").trim();
      const currentTitle = String($(`#${serpContentTitleInputId}`).val() || "").trim();
      const draftHtml = getDraftHtml();

      request(
        "SMARK_cm_ai_write_seo_title",
        {
          post_id: currentReviewPostId || 0,
          keyword: keyword,
          current_title: currentTitle,
          draft_html: draftHtml,
        },
        "POST"
      )
        .done(async (res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }

          const manualAi = !!(res.data && parseInt(String(res.data.manual_ai || "0"), 10));
          if (!manualAi) {
            showNotification(strings.ai_manual_required || (cfg.lang === "fa" ? "این پروژه هوش مصنوعی دستی ندارد." : "Manual AI is not enabled."), "info");
            return;
          }

          const prompt = String((res.data && res.data.prompt) || "");
          if (!prompt) {
            showNotification(strings.ai_prompt_not_found || (cfg.lang === "fa" ? "پرامپت پیدا نشد." : "Prompt not found."), "error");
            return;
          }

          await openChatGptWithPrompt(prompt);
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    // SEO intro AI (SERP create modal) - uses Prompt Bank key: seo_intro
    $(document).on("click", "#cmSerpWriteIntro", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Only available in "create" mode (new content writing window)
      if (serpMode !== "create") return;

      const keyword = String($("#cmSerpKeywordValue").text() || "").trim();
      const currentTitle = String($(`#${serpContentTitleInputId}`).val() || "").trim();
      const draftHtml = getDraftHtml();

      request(
        "SMARK_cm_ai_write_seo_intro",
        {
          post_id: currentReviewPostId || 0,
          keyword: keyword,
          current_title: currentTitle,
          draft_html: draftHtml,
        },
        "POST"
      )
        .done(async (res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }

          const manualAi = !!(res.data && parseInt(String(res.data.manual_ai || "0"), 10));
          if (!manualAi) {
            showNotification(
              strings.ai_manual_required ||
                (cfg.lang === "fa" ? "این پروژه هوش مصنوعی دستی ندارد." : "Manual AI is not enabled."),
              "info"
            );
            return;
          }

          const prompt = String((res.data && res.data.prompt) || "");
          if (!prompt) {
            showNotification(strings.ai_prompt_not_found || (cfg.lang === "fa" ? "پرامپت پیدا نشد." : "Prompt not found."), "error");
            return;
          }

          await openChatGptWithPrompt(prompt);
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    // SEO conclusion AI (SERP create modal) - uses Prompt Bank key: seo_conclusion
    $(document).on("click", "#cmSerpWriteConclusion", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Only available in "create" mode (new content writing window)
      if (serpMode !== "create") return;

      const keyword = String($("#cmSerpKeywordValue").text() || "").trim();
      const currentTitle = String($(`#${serpContentTitleInputId}`).val() || "").trim();
      const draftHtml = getDraftHtml();

      request(
        "SMARK_cm_ai_write_seo_conclusion",
        {
          post_id: currentReviewPostId || 0,
          keyword: keyword,
          current_title: currentTitle,
          draft_html: draftHtml,
        },
        "POST"
      )
        .done(async (res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }

          const manualAi = !!(res.data && parseInt(String(res.data.manual_ai || "0"), 10));
          if (!manualAi) {
            showNotification(
              strings.ai_manual_required ||
                (cfg.lang === "fa" ? "این پروژه هوش مصنوعی دستی ندارد." : "Manual AI is not enabled."),
              "info"
            );
            return;
          }

          const prompt = String((res.data && res.data.prompt) || "");
          if (!prompt) {
            showNotification(strings.ai_prompt_not_found || (cfg.lang === "fa" ? "پرامپت پیدا نشد." : "Prompt not found."), "error");
            return;
          }

          await openChatGptWithPrompt(prompt);
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    $(document).on("click", "#cmAddSelected", function () {
      addSelected();
    });

    // Insert current SERP draft editor HTML into the created draft post (Insert to page)
    $(document).on("click", "#cmSerpInsertToPage", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Only available in "create" mode (new content writing window)
      if (serpMode !== "create") return;

      const postId = currentReviewPostId || 0;
      if (!postId) {
        showNotification(strings.error || "Error", "error");
        return;
      }

      // Save locally first so user doesn't lose changes even if request fails.
      try {
        saveCurrentDraftNow();
      } catch (e2) {}

      const draftHtml = getDraftHtml();

      request(
        "SMARK_cm_insert_to_page",
        {
          post_id: postId,
          draft_html: draftHtml,
        },
        "POST"
      )
        .done((res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }

          // Keep local create list reasonably fresh if it's visible later.
          try {
            const item = res.data && res.data.item ? res.data.item : null;
            if (item && item.keyword) {
              createItemsCache = (createItemsCache || []).map((it) =>
                String(it.keyword || "").trim() === String(item.keyword || "").trim() ? item : it
              );
            }
          } catch (e2) {}

          showNotification(cfg.lang === "fa" ? "محتوا به صفحه منتقل و ذخیره شد." : "Content inserted to page and saved.", "success");
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    let searchTimeout = null;
    $(document).on("input", "#cmSearchInput", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(loadResults, 250);
    });

    $(document).on("change", "#cmTypeFilter", function () {
      loadResults();
    });

    $(document).on("click", ".cm-remove", function () {
      const id = parseInt(String($(this).attr("data-id") || "0"), 10);
      if (id > 0) {
        removeSelected(id);
      }
    });

    $(document).on("change", ".cm-create-type", function () {
      const keyword = String($(this).attr("data-keyword") || "").trim();
      const postType = String($(this).val() || "post").trim();
      if (!keyword) return;
      request("SMARK_cm_update_create_item_type", { keyword: keyword, post_type: postType }, "POST").fail(() => {
        showNotification(strings.error || "Error", "error");
      });
    });

    $(document).on("click", ".cm-create-item", function () {
      const keyword = String($(this).attr("data-keyword") || "").trim();
      if (!keyword) return;

      const $select = $(`.cm-create-type[data-keyword=\"${keyword.replace(/\"/g, '\\"')}\"]`);
      const postType = String(($select.length ? $select.val() : "post") || "post").trim();

      request("SMARK_cm_create_draft_item", { keyword: keyword, post_type: postType }, "POST")
        .done((res) => {
          if (!res || !res.success) {
            showNotification(strings.error || "Error", "error");
            return;
          }
          const item = res.data && res.data.item ? res.data.item : null;
          if (item) {
            createItemsCache = (createItemsCache || []).map((it) =>
              String(it.keyword || "").trim() === String(item.keyword || "").trim() ? item : it
            );
          }
          applyCreateFilter();
        })
        .fail((xhr) => {
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings.error || "Error");
          showNotification(String(msg), "error");
        });
    });

    $(document).on("click", ".cm-review", function () {
      const $btn = $(this);
      const editUrl = String($btn.attr("data-edit-url") || "").trim();
      const viewUrl = String($btn.attr("data-view-url") || "").trim();
      const keywordsRaw = String($btn.attr("data-keywords") || "").trim();
      const postId = parseInt(String($btn.attr("data-post-id") || "0"), 10) || 0;
      const isWriteContent = $btn.hasClass("cm-write-content");
      setSerpMode(isWriteContent ? "create" : "edit");

      const proceed = () => {
        let keywords = keywordsRaw
          ? keywordsRaw
              .split("||")
              .map((k) => String(k || "").trim())
              .filter(Boolean)
          : [];

        if (!keywords.length && focusKeyword) {
          keywords = [focusKeyword];
        }

        if (!keywords.length) {
          const target = editUrl || viewUrl;
          if (target) {
            window.open(target, "_blank", "noopener,noreferrer");
          }
          return;
        }

        // Show keyword used for this preview (no dropdown)
        $("#cmSerpKeywordValue").text(keywords[0]);
        currentReviewPostId = postId || focusPostId || 0;
        loadOurHeadings(currentReviewPostId);

        try {
          restoreSerpTitleIntoInput();
        } catch (e) {}

        openSerpModal();
        ensureAiWriteButton();
        updateDraftHeadingsSet();
        bindDraftEditorWatchers();
        restoreDraftIntoEditor();
        setTimeout(updateAiWriteButton, 50);
        loadSerpForKeyword(keywords[0]);
      };

      const cost = parseInt(String($btn.attr("data-smark-cost") || "0"), 10) || 0;
      if (cost <= 0) {
        proceed();
        return;
      }

      if ($btn.data("smarkCharging")) {
        return;
      }

      $btn.data("smarkCharging", true);
      $btn.prop("disabled", true);
      $btn.addClass("is-loading").attr("aria-busy", "true");

      try {
        let $spinner = $btn.find(".cm-btn-spinner");
        if (!$spinner.length) {
          $spinner = $("<span/>", {
            class: "dashicons dashicons-update dashicons-spin cm-btn-spinner",
            "aria-hidden": "true",
          });
          $btn.append($spinner);
        }
      } catch (e) {}

      request("SMARK_cm_consume_mark", { amount: cost }, "POST")
        .done((res) => {
          if (res && res.success) {
            proceed();
            return;
          }
          const msg =
            (res && res.data && res.data.message) ||
            (strings && strings.error) ||
            (isRTL ? "خطا" : "Error");
          showNotification(String(msg), "error");
        })
        .fail((xhr) => {
          const status = xhr && xhr.status ? parseInt(String(xhr.status), 10) : 0;
          if (status === 503) {
            const msg =
              (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
              (cfg.lang === "fa"
                ? "ارتباط با سرور مرکزی برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید."
                : "Could not connect to the central server. Please try again in a moment.");
            showNotification(String(msg), "warning");
            return;
          }
          if (status === 402) {
            if (window.SMarkMarkModal && typeof window.SMarkMarkModal.open === "function") {
              window.SMarkMarkModal.open();
              return;
            }
            const msg =
              cfg.lang === "fa" ? "مارک به اندازه کافی ندارید." : "You don't have enough Mark credits.";
            showNotification(String(msg), "warning");
            return;
          }
          const msg =
            (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (strings && strings.error) ||
            (isRTL ? "خطا" : "Error");
          showNotification(String(msg), "error");
        })
        .always(() => {
          $btn.data("smarkCharging", false);
          $btn.prop("disabled", false);
          $btn.removeClass("is-loading").removeAttr("aria-busy");
        });
    });

    $(document).on("click", ".cm-find-infographic", function () {
      const msg =
        strings.find_infographic_coming_soon ||
        (isRTL ? "به‌زودی اضافه می‌شود." : "Coming soon.");
      showNotification(String(msg), "info");
    });

    $(document).on("input", `#${serpContentTitleInputId}`, function () {
      resizeSerpTitleTextarea();
      saveSerpTitleFromInput();
    });

    $(document).on("click", ".cm-serp-headings-badge", function () {
      const $result = $(this).closest(".cm-serp-result");
      toggleResultHeadings($result);
      fetchAndRenderHeadingsForResult($result, serpRenderToken);
    });

    // Insert heading into editor when user clicks H1/H2/H3 label.
    $(document).on("click", ".cm-serp-h-badge", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $li = $(this).closest("li");
      const text = String($li.find(".cm-serp-h-text").first().text() || "").trim();
      const level = String($(this).text() || "").trim().toLowerCase(); // h1/h2/h3
      if (!text) return;

      const tag = level === "h1" ? "h1" : level === "h2" ? "h2" : "h3";
      const escaped = $("<div/>").text(text).html();
      insertIntoEditor(`<${tag}>${escaped}</${tag}>\n`);
      // Optimistic update
      updateDraftHeadingsSet();
      refreshUsageBadges();
    });

    // Insert heading into editor when user clicks "Use" badge.
    $(document).on("click", ".cm-serp-use-badge.is-todo", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $li = $(this).closest("li");
      const $hBadge = $li.find(".cm-serp-h-badge").first();
      const text = String($li.find(".cm-serp-h-text").first().text() || "").trim();
      const badgeText = String($hBadge.text() || "").trim().toLowerCase(); // h1/h2/h3
      if (!text) return;

      const tag = badgeText === "h1" ? "h1" : badgeText === "h2" ? "h2" : "h3";
      const escaped = $("<div/>").text(text).html();
      insertIntoEditor(`<${tag}>${escaped}</${tag}>\n`);
      updateDraftHeadingsSet();
      refreshUsageBadges();
    });

    // Ensure draft is persisted even if user refreshes/closes quickly.
    try {
      window.addEventListener("beforeunload", function () {
        saveCurrentDraftNow();
      });
    } catch (e) {}

  });
})(jQuery);
