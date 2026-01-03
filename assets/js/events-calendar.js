(function () {
  "use strict";

  function qs(root, sel) { return root.querySelector(sel); }
  function qsa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

  function openModal(root, payload) {
    var modal = qs(root, "[data-pcc-modal]");
    if (!modal) return;

    var img = qs(modal, "[data-pcc-modal-img]");
    var time = qs(modal, "[data-pcc-modal-time]");
    var title = qs(modal, "[data-pcc-modal-title]");
    var loc = qs(modal, "[data-pcc-modal-loc]");
    var desc = qs(modal, "[data-pcc-modal-desc]");
    var link = qs(modal, "[data-pcc-modal-link]");

    var imageUrl = payload.image_url || (window.PCC_CAL && PCC_CAL.placeholder) || "";
    img.src = imageUrl;
    img.alt = payload.title || "";

    time.textContent = payload.time || "";
    title.textContent = payload.title || "";
    loc.textContent = [payload.location, payload.address].filter(Boolean).join(" · ");
    desc.textContent = (payload.description || "").replace(/<\/?[^>]+(>|$)/g, "").trim();

    if (payload.url) {
      link.href = payload.url;
      link.style.display = "";
    } else {
      link.style.display = "none";
    }

    modal.hidden = false;
    document.body.classList.add("pcc-modal-open");
  }

  function closeModal(root) {
    var modal = qs(root, "[data-pcc-modal]");
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove("pcc-modal-open");
  }

  function initCalendar(root) {
    if (root.dataset.pccInited === "1") return;
    root.dataset.pccInited = "1";

    var monthsWrap = qs(root, "[data-pcc-cal-months]");
    var months = qsa(root, "[data-pcc-cal-month]");
    var title = qs(root, "[data-pcc-cal-title]");

    var idx = 0;

    function show(i) {
      idx = Math.max(0, Math.min(months.length - 1, i));
      months.forEach(function (m, mi) {
        m.hidden = mi !== idx;
      });

      var lab = qs(months[idx], "[data-month-label]");
      if (lab && title) title.textContent = lab.getAttribute("data-month-label") || "";
    }

    var prev = qs(root, "[data-pcc-cal-prev]");
    var next = qs(root, "[data-pcc-cal-next]");
    var today = qs(root, "[data-pcc-cal-today]");

    if (prev) prev.addEventListener("click", function(){ show(idx - 1); });
    if (next) next.addEventListener("click", function(){ show(idx + 1); });
    if (today) today.addEventListener("click", function(){ show(0); });

    // Event click => modal
    root.addEventListener("click", function (e) {
      var btn = e.target.closest(".pcc-cal-event");
      if (btn && btn.dataset.pccEvent) {
        try {
          var payload = JSON.parse(btn.dataset.pccEvent);
          openModal(root, payload);
        } catch (err) {}
      }

      if (e.target.matches("[data-pcc-modal-close]")) {
        closeModal(root);
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeModal(root);
    });

    show(0);
  }

  function scan() {
    document.querySelectorAll("[data-pcc-calendar]").forEach(initCalendar);
  }

  document.addEventListener("DOMContentLoaded", scan);

  // Divi/dynamic DOM
  var mo = new MutationObserver(scan);
  mo.observe(document.documentElement, { childList: true, subtree: true });
})();