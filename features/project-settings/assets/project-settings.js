(function ($) {
  function showNotification(message, type, options) {
    if (!message) {
      return;
    }

    const t = type || "info";
    let $notice = $(".smark-notification");
    if (!$notice.length) {
      $notice = $('<div class="smark-notification" role="status" aria-live="polite" />').appendTo("body");
    }

    const isRTL =
      $(".wrap.smark-project-settings-page").hasClass("rtl") ||
      $(".wrap.smark-project-settings-page").attr("data-lang") === "fa";

    const titlesFa = {
      success: "موفقیت‌آمیز",
      error: "خطا",
      warning: "هشدار",
      info: "اطلاع‌رسانی",
    };
    const titlesEn = {
      success: "Congratulations!",
      error: "Something went wrong!",
      warning: "Warning!",
      info: "Did you know?",
    };
    const titles = isRTL ? titlesFa : titlesEn;
    const icons = { success: "✓", error: "×", warning: "!", info: "i" };

    $notice.removeClass("success error warning info visible rtl").addClass(t).empty();

    const $icon = $('<div class="smark-notification__icon" aria-hidden="true" />').text(icons[t] || icons.info);
    const $body = $('<div class="smark-notification__body" />');
    $('<strong class="smark-notification__title" />').text(titles[t] || titles.info).appendTo($body);
    $('<span class="smark-notification__message" />').text(message).appendTo($body);
    const $close = $(
      '<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>'
    );

    $close.on("click", function () {
      clearTimeout($notice.data("timeout"));
      $notice.removeClass("visible");
      setTimeout(() => {
        if (!$notice.hasClass("visible")) {
          $notice.remove();
        }
      }, 80);
    });

    $notice.append($icon, $body, $close);

    if (isRTL) {
      $notice.addClass("rtl");
    }

    window.setTimeout(function () {
      $notice.addClass("visible");
    }, 20);

    clearTimeout($notice.data("timeout"));
    $notice.data("timeout", null);
  }

  function maybeShowOAuthNotices() {
    try {
      const cfg = window.SMarkProjectSettings || {};
      const strings = cfg.strings || {};
      const url = new URL(window.location.href);
      const scSuccess = url.searchParams.get("sc_success");
      const scError = url.searchParams.get("sc_error");
      const scErrorDesc = url.searchParams.get("sc_error_desc");

      const hashRaw = String(window.location.hash || "").replace(/^#/, "");
      const hashParams = new URLSearchParams(hashRaw);
      const scHashError = hashParams.get("smark_sc_error");
      const scHashErrorDesc = hashParams.get("smark_sc_error_desc");

      if (scSuccess === "1") {
        showNotification(strings.scSuccess || "Connected", "success");
      } else if (scError || scHashError) {
        const err = scError || scHashError;
        const desc = scErrorDesc || scHashErrorDesc;
        const msg =
          (strings.scError || "Connection failed") +
          (desc ? "\n" + desc : "\n" + err);
        showNotification(msg, "error");
      }

      if (scSuccess || scError || scErrorDesc) {
        url.searchParams.delete("sc_success");
        url.searchParams.delete("sc_error");
        url.searchParams.delete("sc_error_desc");
        window.history.replaceState({}, document.title, url.toString());
      }

      if (scHashError || scHashErrorDesc) {
        hashParams.delete("smark_sc_error");
        hashParams.delete("smark_sc_error_desc");
        hashParams.delete("smark_sc_state");
        const newHash = hashParams.toString();
        const cleaned = new URL(window.location.href);
        cleaned.hash = newHash ? "#" + newHash : "";
        window.history.replaceState({}, document.title, cleaned.toString());
      }
    } catch (e) {
      // ignore
    }
  }

  function maybeHandleBrokerClaim() {
    const cfg = window.SMarkProjectSettings || {};
    const strings = cfg.strings || {};

    if (!cfg.ajaxUrl || !cfg.pmNonce) {
      return;
    }

    const hashRaw = String(window.location.hash || "").replace(/^#/, "");
    if (!hashRaw) return;

    const hashParams = new URLSearchParams(hashRaw);
    const claimCode = String(hashParams.get("smark_sc_claim") || "").trim();
    const stateId = String(hashParams.get("smark_sc_state") || "").trim();

    if (!claimCode || !stateId) {
      return;
    }

    // Prevent referrer leakage: keep claim codes in hash only, then exchange via AJAX.
    showNotification(strings.checking ? strings.checking : "Checking...", "info");

    function storeTokensOnProject(tokens) {
      return $.post(cfg.ajaxUrl, {
        action: "SMARK_project_settings_store_search_console_tokens",
        nonce: cfg.pmNonce,
        state_id: stateId,
        tokens: JSON.stringify(tokens || {}),
      });
    }

    async function tryBrowserClaimThenStore() {
      const brokerBase = String(cfg.scBrokerBase || "").trim().replace(/\/+$/, "");
      if (!brokerBase) {
        throw new Error("Broker base URL is not configured");
      }

      const claimUrl = brokerBase + "/claim";
      const resp = await fetch(claimUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ claim_code: claimCode }),
        credentials: "omit",
      });

      if (!resp.ok) {
        const text = await resp.text().catch(() => "");
        throw new Error(
          (text && text.trim()) ? text.trim() : ("HTTP " + resp.status)
        );
      }

      const data = await resp.json();
      if (!data || !data.tokens) {
        throw new Error("Invalid broker claim response");
      }

      const jqResp = await storeTokensOnProject(data.tokens);
      if (!jqResp || !jqResp.success) {
        const msg =
          jqResp && jqResp.data && jqResp.data.message
            ? String(jqResp.data.message)
            : "Unable to store tokens on project";
        throw new Error(msg);
      }
    }

    $.post(cfg.ajaxUrl, {
      action: "SMARK_project_settings_claim_search_console",
      nonce: cfg.pmNonce,
      claim_code: claimCode,
      state_id: stateId,
    })
      .done(function (resp) {
        if (resp && resp.success) {
          const next = new URL(window.location.href);
          next.hash = "";
          next.searchParams.set("sc_success", "1");
          window.location.href = next.toString();
          return;
        }
        const msg =
          resp && resp.data && resp.data.message
            ? String(resp.data.message)
            : strings.scError || "Connection failed";
        showNotification(msg, "error", { autoDismiss: false });

        const cleaned = new URL(window.location.href);
        cleaned.hash = "";
        window.history.replaceState({}, document.title, cleaned.toString());
      })
      .fail(function () {
        let msg = strings.scError || "Connection failed";
        const xhr = arguments && arguments.length ? arguments[0] : null;
        if (xhr) {
          const status = xhr.status ? String(xhr.status) : "";
          const jsonMsg =
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
              ? String(xhr.responseJSON.data.message)
              : "";
          const text =
            xhr.responseText && String(xhr.responseText).trim()
              ? String(xhr.responseText).trim()
              : "";
          if (jsonMsg) {
            msg = jsonMsg + (status ? " (" + status + ")" : "");
          } else if (text && text !== "-1") {
            msg = (status ? "HTTP " + status + ": " : "") + text;
          } else if (status) {
            msg = (strings.scError || "Connection failed") + " (" + status + ")";
          }
        }
        // If the server cannot reach the broker (common on locked-down hosts),
        // fall back to browser-side claim + store on project.
        const shouldFallback =
          /Resolving timed out/i.test(msg) ||
          /Could not resolve host/i.test(msg) ||
          /cURL error 28/i.test(msg);

        if (shouldFallback) {
          tryBrowserClaimThenStore()
            .then(() => {
              const next = new URL(window.location.href);
              next.hash = "";
              next.searchParams.set("sc_success", "1");
              window.location.href = next.toString();
            })
            .catch((e) => {
              const em = e && e.message ? String(e.message) : msg;
              showNotification(em, "error", { autoDismiss: false });
              const cleaned = new URL(window.location.href);
              cleaned.hash = "";
              window.history.replaceState({}, document.title, cleaned.toString());
            });
          return;
        }

        showNotification(msg, "error", { autoDismiss: false });
        const cleaned = new URL(window.location.href);
        cleaned.hash = "";
        window.history.replaceState({}, document.title, cleaned.toString());
      });
  }

  function setBusy($btn, busy, text) {
    $btn.prop("disabled", busy);
    if (text) $btn.text(text);
  }

  async function fetchMarkBalanceViaBrowser() {
    const cfg = window.SMarkProjectSettings || {};
    const endpoints = Array.isArray(cfg.centralMarkBalanceGetEndpoints)
      ? cfg.centralMarkBalanceGetEndpoints
      : [];

    const website = String(cfg.projectWebsite || "").trim().replace(/\/+$/, "");
    const projectId = String(cfg.projectPublicId || "").trim();
    const centralDbId = parseInt(String(cfg.centralProjectDbId || "0"), 10);
    if (!endpoints.length || !website) return null;

    const headers = {};
    const token = String(cfg.centralToken || "").trim();
    if (token) {
      headers["x-smark-sync-token"] = token;
    }

    for (const base of endpoints) {
      const baseUrl = String(base || "").trim();
      if (!baseUrl) continue;
      try {
        const url = new URL(baseUrl);
        url.searchParams.set("website", website);
        if (!Number.isNaN(centralDbId) && centralDbId > 0) {
          url.searchParams.set("id", String(centralDbId));
        }
        if (projectId) url.searchParams.set("project_id", projectId);
        url.searchParams.set("_ts", String(Date.now()));

        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), 8000);

        const resp = await fetch(url.toString(), {
          method: "GET",
          headers,
          credentials: "omit",
          signal: ctrl.signal,
        }).finally(() => clearTimeout(t));

        if (!resp.ok) continue;
        const data = await resp.json().catch(() => null);
        if (!data || !data.ok) continue;
        const mark = parseInt(String(data.mark || "0"), 10);
        if (Number.isNaN(mark) || mark < 0) continue;
        return mark;
      } catch (e) {
        // ignore and try next endpoint
      }
    }

    return null;
  }

  function maybeFixMarkBalanceDisplay() {
    const $field = $("#smark_mark_credit");
    if (!$field.length) return;

    function normalizeDigits(str) {
      return String(str || "")
        .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06f0))
        .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660));
    }

    function parseDisplayNumber(str) {
      const s = normalizeDigits(str).replace(/[^\d-]/g, "");
      if (!s) return null;
      const n = parseInt(s, 10);
      return Number.isNaN(n) ? null : n;
    }

    const currentRaw = String($field.val() || "").trim();
    const emptyLike =
      currentRaw === "" ||
      currentRaw === "-" ||
      currentRaw === "—" ||
      currentRaw === "\u2014" ||
      currentRaw === "â€”";
    const currentNumber = emptyLike ? null : parseDisplayNumber(currentRaw);

    fetchMarkBalanceViaBrowser().then((mark) => {
      if (mark === null || mark === undefined) return;

      const cfg = window.SMarkProjectSettings || {};
      const effective = Math.max(0, parseInt(String(mark || "0"), 10));

      if (currentNumber !== null && currentNumber === effective) return;
      try {
        const lang = String(cfg.currentLang || "en") === "fa" ? "fa-IR" : "en-US";
        const formatted = new Intl.NumberFormat(lang).format(effective);
        $field.val(formatted);
        document.dispatchEvent(
          new window.CustomEvent("smark:dashboard-mark-balance-updated", {
            detail: { markBalance: { value: effective, label: formatted } },
          })
        );
      } catch (e) {
        $field.val(String(effective));
        document.dispatchEvent(
          new window.CustomEvent("smark:dashboard-mark-balance-updated", {
            detail: { markBalance: { value: effective, label: String(effective) } },
          })
        );
      }
    });
  }

  function fixFooterLayout() {
    const wpBody = document.querySelector("#wpbody");
    const wpBodyContent = document.querySelector("#wpbody-content");
    const wrap = document.querySelector(".wrap.smark-project-settings-page");
    const mainContent = document.querySelector(".smark-project-settings-content");
    const footer = document.querySelector(".smark-version-footer");

    if (!wrap) {
      return;
    }

    if (wrap.closest(".smark-dashboard-embedded-view")) {
      return;
    }

    if (wpBody && wpBodyContent) {
      wpBodyContent.style.height = getComputedStyle(wpBody).height;
      wpBodyContent.style.minHeight = wpBodyContent.style.height;
      wpBodyContent.style.float = "none";
      wpBodyContent.style.paddingBottom = "0";
    }

    if (wpBody && wrap) {
      wrap.style.height = getComputedStyle(wpBody).height;
      wrap.style.minHeight = wrap.style.height;
      wrap.style.float = "none";
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
  }

  function connectSearchConsole() {
    const cfg = window.SMarkProjectSettings || {};
    const $btn = $(".smark-connect-sc");
    const $status = $(".smark-sc-status");

    if (!cfg.ajaxUrl || !cfg.pmNonce || !cfg.projectId) return;

    setBusy($btn, true, cfg.strings && cfg.strings.checking ? cfg.strings.checking : "Checking...");

    $.ajax({
      url: cfg.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action: "SMARK_project_settings_connect_search_console",
        nonce: cfg.pmNonce,
        project_id: cfg.projectId,
      },
    })
      .done(function (resp) {
        if (resp && resp.success && resp.data && resp.data.auth_url) {
          $status.text(cfg.strings && cfg.strings.connected ? cfg.strings.connected : "Connected");
          window.location.href = resp.data.auth_url;
          return;
        }
        if (resp && resp.data && resp.data.message) {
          window.alert(resp.data.message);
        } else {
          window.alert("Unable to initiate Search Console connection");
        }
      })
      .fail(function () {
        let msg = "Request failed";
        const xhr = arguments && arguments.length ? arguments[0] : null;
        if (xhr) {
          const status = xhr.status ? String(xhr.status) : "";
          const jsonMsg =
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
              ? String(xhr.responseJSON.data.message)
              : "";
          const text =
            xhr.responseText && String(xhr.responseText).trim()
              ? String(xhr.responseText).trim()
              : "";
          if (jsonMsg) {
            msg = jsonMsg + (status ? " (" + status + ")" : "");
          } else if (text) {
            msg = (status ? "HTTP " + status + ": " : "") + text;
          } else if (status) {
            msg = "Request failed (" + status + ")";
          }
        }
        showNotification(msg, "error");
      })
      .always(function () {
        setBusy($btn, false, cfg.strings && cfg.strings.connect ? cfg.strings.connect : "Connect");
      });
  }

  let mainSettingsSaveTimer = null;
  let mainSettingsSaveXhr = null;

  function setMainSettingsStatus(message, type) {
    $("[data-smark-settings-save-state]")
      .removeClass("is-error")
      .toggleClass("is-error", type === "error")
      .text(message || "");
  }

  function saveEmbeddedProjectSettings($form) {
    const cfg = window.SMarkProjectSettings || {};
    if (!cfg.ajaxUrl) {
      return false;
    }

    const strings = cfg.strings || {};
    const formData = new FormData($form.get(0));
    formData.set("action", "smark_dashboard_project_settings_save");

    if (mainSettingsSaveXhr && mainSettingsSaveXhr.abort) {
      mainSettingsSaveXhr.abort();
    }

    setMainSettingsStatus(strings.saving || "Saving...");

    mainSettingsSaveXhr = $.ajax({
      url: cfg.ajaxUrl,
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
    })
      .done(function (resp) {
        if (resp && resp.success && resp.data) {
          setMainSettingsStatus(resp.data.message || strings.saved || "Saved");
          document.dispatchEvent(
            new window.CustomEvent("smark:dashboard-module-visibility-updated", {
              detail: {
                moduleVisibility: resp.data.moduleVisibility || {},
              },
            })
          );
          return;
        }

        setMainSettingsStatus((resp && resp.data && resp.data.message) || "Unable to save.", "error");
      })
      .fail(function (xhr) {
        if (xhr && xhr.statusText === "abort") {
          return;
        }
        const message =
          xhr &&
          xhr.responseJSON &&
          xhr.responseJSON.data &&
          xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : "Unable to save.";
        setMainSettingsStatus(message, "error");
      });

    return true;
  }

  function scheduleMainSettingsSave($form) {
    window.clearTimeout(mainSettingsSaveTimer);
    mainSettingsSaveTimer = window.setTimeout(function () {
      saveEmbeddedProjectSettings($form);
    }, 600);
  }

  let offerAgentSaveTimer = null;

  function setOfferAgentStatus(message, type) {
    const $status = $("[data-offer-agent-save-state]").first();
    if (!$status.length) {
      return;
    }

    $status
      .removeClass("is-error")
      .toggleClass("is-error", type === "error")
      .text(message || "");
  }

  function collectOfferAgentSettings() {
    const settings = {
      product_id: "random",
      audience_type_id: "random",
      strategy_id: "random",
    };

    $("[data-offer-agent-setting]").each(function () {
      const name = String($(this).attr("name") || "").trim();
      if (!name) {
        return;
      }
      settings[name] = String($(this).val() || "random").trim() || "random";
    });

    return settings;
  }

  function saveOfferAgentSettings() {
    const cfg = window.SMarkProjectSettings || {};
    if (!cfg.ajaxUrl || !cfg.offerAgentNonce) {
      return;
    }

    const settings = collectOfferAgentSettings();
    const strings = cfg.strings || {};

    setOfferAgentStatus(strings.offerAgentSaving || "Saving...");

    $.post(cfg.ajaxUrl, {
      action: "smark_project_settings_save_offer_agent",
      nonce: cfg.offerAgentNonce,
      product_id: settings.product_id,
      audience_type_id: settings.audience_type_id,
      strategy_id: settings.strategy_id,
    })
      .done(function (resp) {
        if (resp && resp.success) {
          setOfferAgentStatus((resp.data && resp.data.message) || strings.offerAgentSaved || "Saved");
          return;
        }

        setOfferAgentStatus((resp && resp.data && resp.data.message) || strings.offerAgentSaveError || "Unable to save.", "error");
      })
      .fail(function (xhr) {
        const message =
          xhr &&
          xhr.responseJSON &&
          xhr.responseJSON.data &&
          xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : strings.offerAgentSaveError || "Unable to save.";
        setOfferAgentStatus(message, "error");
      });
  }

  function scheduleOfferAgentSave() {
    window.clearTimeout(offerAgentSaveTimer);
    offerAgentSaveTimer = window.setTimeout(saveOfferAgentSettings, 450);
  }

  let emailCampaignAgentSaveTimer = null;

  function setEmailCampaignAgentStatus(message, type) {
    const $status = $("[data-email-campaign-agent-save-state]").first();
    if (!$status.length) {
      return;
    }

    $status
      .removeClass("is-error")
      .toggleClass("is-error", type === "error")
      .text(message || "");
  }

  function collectEmailCampaignAgentSettings() {
    const settings = {
      offer_id: "random",
    };

    $("[data-email-campaign-agent-setting]").each(function () {
      const name = String($(this).attr("name") || "").trim();
      if (!name) {
        return;
      }
      settings[name] = String($(this).val() || "random").trim() || "random";
    });

    return settings;
  }

  function saveEmailCampaignAgentSettings() {
    const cfg = window.SMarkProjectSettings || {};
    if (!cfg.ajaxUrl || !cfg.emailCampaignAgentNonce) {
      return;
    }

    const settings = collectEmailCampaignAgentSettings();
    const strings = cfg.strings || {};

    setEmailCampaignAgentStatus(strings.offerAgentSaving || "Saving...");

    $.post(cfg.ajaxUrl, {
      action: "smark_project_settings_save_email_campaign_agent",
      nonce: cfg.emailCampaignAgentNonce,
      offer_id: settings.offer_id,
    })
      .done(function (resp) {
        if (resp && resp.success) {
          setEmailCampaignAgentStatus((resp.data && resp.data.message) || strings.emailCampaignAgentSaved || "Saved");
          return;
        }

        setEmailCampaignAgentStatus((resp && resp.data && resp.data.message) || strings.emailCampaignAgentSaveError || "Unable to save.", "error");
      })
      .fail(function (xhr) {
        const message =
          xhr &&
          xhr.responseJSON &&
          xhr.responseJSON.data &&
          xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : strings.emailCampaignAgentSaveError || "Unable to save.";
        setEmailCampaignAgentStatus(message, "error");
      });
  }

  function scheduleEmailCampaignAgentSave() {
    window.clearTimeout(emailCampaignAgentSaveTimer);
    emailCampaignAgentSaveTimer = window.setTimeout(saveEmailCampaignAgentSettings, 450);
  }

  function toggleAgentPanel($trigger) {
    const agent = String($trigger.attr("data-smark-agent-panel-trigger") || "").trim();
    const panelId = agent === "email_campaign" ? "smark_email_campaign_agent_settings_panel" : "smark_offer_agent_settings_panel";
    const $panel = $("#" + panelId);
    if (!$panel.length) {
      return;
    }

    const isHidden = $panel.prop("hidden");
    $(".smark-agent-config-window").not($panel).prop("hidden", true);
    $('[data-smark-agent-panel-trigger]').not($trigger).attr("aria-expanded", "false");
    $panel.prop("hidden", !isHidden);
    $trigger.attr("aria-expanded", isHidden ? "true" : "false");

    if (isHidden) {
      if (agent === "email_campaign") {
        setEmailCampaignAgentStatus("");
      } else {
        setOfferAgentStatus("");
      }
      const firstSelect = $panel.find("select").first().get(0);
      if (firstSelect) {
        window.setTimeout(function () {
          firstSelect.focus();
        }, 80);
      }
    }
  }

  function toggleSettingsSection($trigger) {
    const panelId = String($trigger.attr("aria-controls") || "").trim();
    const $panel = panelId ? $("#" + panelId) : $();
    if (!$panel.length) {
      return;
    }

    const isOpen = !$panel.prop("hidden");
    $panel.prop("hidden", isOpen);
    $trigger.toggleClass("is-open", !isOpen).attr("aria-expanded", isOpen ? "false" : "true");

    const collapseLabel = $trigger.attr("data-collapse-label");
    const expandLabel = $trigger.attr("data-expand-label");
    if (collapseLabel && expandLabel) {
      $trigger.attr("aria-label", isOpen ? expandLabel : collapseLabel);
    }
  }

  $(function () {
    $(document).on("change", "#SMARK_language_select", function () {
      const cfg = window.SMarkProjectSettings || {};
      const language = String($(this).val() || "").trim();
      if (!cfg.ajaxUrl || !cfg.languageNonce) {
        return;
      }
      if (!language) {
        return;
      }

      $.post(cfg.ajaxUrl, {
        action: "SMARK_project_settings_save_language",
        nonce: cfg.languageNonce,
        language: language,
      })
        .done(function (resp) {
          if (resp && resp.success) {
            window.location.reload();
          }
        })
        .fail(function () {
          window.alert("Unable to save language preference");
        });
    });

    $(document).on("click", ".smark-connect-sc", function (e) {
      e.preventDefault();
      connectSearchConsole();
    });

    $(document).on("submit", ".smark-project-settings-content form", function (e) {
      e.preventDefault();
      window.clearTimeout(mainSettingsSaveTimer);
      saveEmbeddedProjectSettings($(this));
    });

    $(document).on(
      "input",
      '.smark-project-settings-content form input[type="text"], .smark-project-settings-content form input[type="url"], .smark-project-settings-content form textarea',
      function () {
        scheduleMainSettingsSave($(this).closest("form"));
      }
    );

    $(document).on(
      "change",
      '.smark-project-settings-content form select, .smark-project-settings-content form input[type="checkbox"]',
      function () {
        scheduleMainSettingsSave($(this).closest("form"));
      }
    );

    $(document).on("click", "[data-smark-agent-panel-trigger]", function (e) {
      e.preventDefault();
      toggleAgentPanel($(this));
    });

    $(document).on("click", "[data-smark-section-toggle]", function () {
      toggleSettingsSection($(this));
    });

    $(document).on("change", "[data-offer-agent-setting]", function () {
      scheduleOfferAgentSave();
    });

    $(document).on("change", "[data-email-campaign-agent-setting]", function () {
      scheduleEmailCampaignAgentSave();
    });

    maybeHandleBrokerClaim();
    maybeShowOAuthNotices();
    maybeFixMarkBalanceDisplay();
    fixFooterLayout();

    $(document).on("smark:project-settings-view-loaded", function () {
      maybeShowOAuthNotices();
      maybeFixMarkBalanceDisplay();
      fixFooterLayout();
    });
  });
})(jQuery);
