(function (window, document, $) {
  const cfg = window.SMarkMarkModalConfig || {};
  const lang = String(cfg.lang || "en") === "fa" ? "fa" : "en";
  const isRTL = lang === "fa";
  const ajaxUrl = cfg.ajaxUrl || "";
  const nonce = cfg.nonce || "";

  function startTopup(amount) {
    amount = parseInt(String(amount || "0"), 10) || 0;
    if (!ajaxUrl || !nonce || amount <= 0) {
      return;
    }

    const loadingText = isRTL ? "در حال انتقال به پرداخت..." : "Redirecting to payment...";

    try {
      const $root = ensureModal();
      const $btn = $root.find(".smark-mark-modal__cta");
      $btn.prop("disabled", true);
      $root.find(".smark-mark-modal__cta-text").text(loadingText);
    } catch (e) {}

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action: "SMARK_mark_topup_start",
        nonce: nonce,
        amount: amount,
      },
      success: function (resp) {
        const url = resp && resp.success && resp.data && resp.data.checkout_url ? String(resp.data.checkout_url) : "";
        if (url) {
          window.location.href = url;
          return;
        }

        const msg =
          (resp && resp.data && resp.data.message) ||
          (isRTL ? "خطا در شروع فرآیند خرید. لطفاً دوباره تلاش کنید." : "Failed to start purchase. Please try again.");
        alert(String(msg));
      },
      error: function (xhr) {
        let msg = isRTL ? "خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید." : "Server error. Please try again.";
        try {
          if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = String(xhr.responseJSON.data.message);
          }
        } catch (e) {}
        alert(String(msg));
      },
      complete: function () {
        try {
          const $root = $("#smarkMarkTopupModal");
          const $btn = $root.find(".smark-mark-modal__cta");
          $btn.prop("disabled", false);

          const cta =
            (cfg.strings && cfg.strings.cta) ||
            (isRTL ? "خرید ۵٬۰۰۰ مارک" : "Buy 5,000 Mark");
          $root.find(".smark-mark-modal__cta-text").text(cta);
        } catch (e) {}
      },
    });
  }

  function ensureModal() {
    let $root = $("#smarkMarkTopupModal");
    if ($root.length) return $root;

    const title =
      (cfg.strings && cfg.strings.title) ||
      (isRTL ? "کردیت مارک شما تمام شده است" : "Your Mark credits are finished");
    const desc =
      (cfg.strings && cfg.strings.desc) ||
      (isRTL
        ? "برای ادامه استفاده از امکانات اسمارک، همین حالا حساب‌تان را شارژ کنید."
        : "To continue using SMark features, please top up your account now.");
    const hint =
      (cfg.strings && cfg.strings.hint) ||
      (isRTL
        ? "این عملیات نیازمند مارک است و بدون شارژ حساب قابل انجام نیست."
        : "This action requires Mark credits and cannot be completed without a top up.");
    const cta =
      (cfg.strings && cfg.strings.cta) ||
      (isRTL ? "خرید ۵٬۰۰۰ مارک" : "Buy 5,000 Mark");
    const later =
      (cfg.strings && cfg.strings.later) || (isRTL ? "بعداً" : "Not now");

    $root = $(`
      <div class="smark-mark-modal${isRTL ? " rtl" : ""}" id="smarkMarkTopupModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="smark-mark-modal__backdrop" aria-hidden="true"></div>
        <div class="smark-mark-modal__dialog" role="document">
          <div class="smark-mark-modal__header">
            <div class="smark-mark-modal__title-wrap">
              <span class="smark-mark-modal__icon" aria-hidden="true">
                <span class="dashicons dashicons-tickets-alt"></span>
              </span>
              <h3 class="smark-mark-modal__title"></h3>
            </div>
            <button type="button" class="smark-mark-modal__close" aria-label="Close">
              <span class="dashicons dashicons-no-alt"></span>
            </button>
          </div>
          <div class="smark-mark-modal__body">
            <p class="smark-mark-modal__desc"></p>
            <div class="smark-mark-modal__card">
              <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
              <div class="smark-mark-modal__card-text"></div>
            </div>
          </div>
          <div class="smark-mark-modal__footer">
            <button type="button" class="smark-mark-modal__secondary" data-smark-close="1"></button>
            <button type="button" class="smark-mark-modal__cta" data-smark-mark-package="5000">
              <span class="dashicons dashicons-cart" aria-hidden="true"></span>
              <span class="smark-mark-modal__cta-text"></span>
            </button>
          </div>
        </div>
      </div>
    `);

    $root.find(".smark-mark-modal__title").text(title);
    $root.find(".smark-mark-modal__desc").text(desc);
    $root.find(".smark-mark-modal__card-text").text(hint);
    $root.find(".smark-mark-modal__cta-text").text(cta);
    $root.find(".smark-mark-modal__secondary").text(later);

    $("body").append($root);
    return $root;
  }

  function open() {
    const $root = ensureModal();
    $root.addClass("is-open").attr("aria-hidden", "false");
    $("body").addClass("smark-mark-modal-open");
    setTimeout(() => {
      $root.find(".smark-mark-modal__cta").trigger("focus");
    }, 0);
  }

  function close() {
    const $root = $("#smarkMarkTopupModal");
    if (!$root.length) return;
    $root.removeClass("is-open").attr("aria-hidden", "true");
    $("body").removeClass("smark-mark-modal-open");
  }

  $(document).on("click", "#smarkMarkTopupModal .smark-mark-modal__close", function () {
    close();
  });
  $(document).on("click", "#smarkMarkTopupModal [data-smark-close=\"1\"]", function () {
    close();
  });
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      if ($("#smarkMarkTopupModal").hasClass("is-open")) {
        close();
      }
    }
  });
  $(document).on("click", "#smarkMarkTopupModal .smark-mark-modal__cta", function () {
    const pack = parseInt(String($(this).attr("data-smark-mark-package") || "0"), 10) || 0;
    try {
      window.dispatchEvent(new CustomEvent("smark:mark_topup", { detail: { package: pack } }));
    } catch (e) {}
  });

  window.addEventListener("smark:mark_topup", function (e) {
    const amount = e && e.detail && e.detail.package ? e.detail.package : 0;
    startTopup(amount);
  });

  window.SMarkMarkModal = window.SMarkMarkModal || {};
  window.SMarkMarkModal.open = open;
  window.SMarkMarkModal.close = close;
})(window, document, window.jQuery);
