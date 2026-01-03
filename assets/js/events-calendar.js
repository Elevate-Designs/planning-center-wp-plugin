(function () {
  "use strict";

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function safeText(el, v) { if (el) el.textContent = v || ""; }
  function safeAttr(el, k, v) { if (el) el.setAttribute(k, v || ""); }

  function positionPop(pop, x, y) {
    const pad = 12;
    const w = pop.offsetWidth || 320;
    const h = pop.offsetHeight || 380;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    let left = x + 14;
    let top = y - 10;

    if (left + w + pad > vw) left = x - w - 14;
    if (top + h + pad > vh) top = vh - h - pad;
    if (top < pad) top = pad;
    if (left < pad) left = pad;

    pop.style.left = left + "px";
    pop.style.top = top + "px";
  }

  function initCalendar(root) {
    const months = $all(".pcc-month", root);
    if (!months.length) return;

    const titleEl = $(".pcc-cal-title", root);
    const prevBtn = $(".pcc-cal-prev", root);
    const nextBtn = $(".pcc-cal-next", root);
    const todayBtn = $(".pcc-cal-today", root);
    const qInput = $(".pcc-cal-q", root);

    const pop = $("#pcc-pop", root);
    const popClose = $(".pcc-pop-close", pop);
    const popImg = $(".pcc-pop-img img", pop);
    const popDate = $(".pcc-pop-date", pop);
    const popTitle = $(".pcc-pop-title", pop);
    const popLoc = $(".pcc-pop-loc", pop);
    const popDesc = $(".pcc-pop-desc", pop);
    const popLink = $(".pcc-pop-link", pop);

    let idx = months.findIndex(m => m.style.display !== "none");
    if (idx < 0) idx = 0;

    function setTitle() {
      const key = months[idx].dataset.month; // YYYY-MM
      if (!key) return;
      const parts = key.split("-");
      const y = parseInt(parts[0], 10);
      const m = parseInt(parts[1], 10) - 1;
      const dt = new Date(y, m, 1);
      const label = dt.toLocaleString(undefined, { month: "long", year: "numeric" });
      safeText(titleEl, label);
    }

    function showMonth(i) {
      idx = Math.max(0, Math.min(months.length - 1, i));
      months.forEach((m, k) => { m.style.display = (k === idx ? "" : "none"); });
      setTitle();
      closePop();
    }

    function closePop() {
      pop.classList.remove("open");
    }

    function openPop(evEl, mouseEvent) {
      const title = evEl.dataset.title || "";
      const url = evEl.dataset.url || "#";
      const img = evEl.dataset.img || root.dataset.placeholder || "";
      const date = evEl.dataset.date || "";
      const time = evEl.dataset.time || "";
      const loc = evEl.dataset.loc || "";
      const desc = evEl.dataset.desc || "";

      safeAttr(popImg, "src", img);
      safeAttr(popImg, "alt", title);
      safeText(popDate, (date && time) ? (date + " @ " + time) : (date || time));
      safeText(popTitle, title);
      safeText(popLoc, loc);
      safeText(popDesc, desc);
      safeAttr(popLink, "href", url);

      pop.classList.add("open");
      positionPop(pop, mouseEvent.clientX, mouseEvent.clientY);
    }

    // Bind event click
    $all(".pcc-evt", root).forEach(el => {
      el.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        openPop(el, e);
      });
    });

    // Close handlers
    if (popClose) popClose.addEventListener("click", closePop);
    document.addEventListener("click", function () {
      closePop();
    });
    window.addEventListener("resize", closePop);
    window.addEventListener("scroll", closePop, { passive: true });

    // Month nav
    if (prevBtn) prevBtn.addEventListener("click", () => showMonth(idx - 1));
    if (nextBtn) nextBtn.addEventListener("click", () => showMonth(idx + 1));
    if (todayBtn) todayBtn.addEventListener("click", () => showMonth(0));

    // Simple search filter
    function applySearch() {
      const q = (qInput && qInput.value || "").trim().toLowerCase();
      $all(".pcc-evt", root).forEach(el => {
        const t = (el.dataset.title || "").toLowerCase();
        el.style.display = (!q || t.includes(q)) ? "" : "none";
      });
      closePop();
    }
    if (qInput) qInput.addEventListener("input", applySearch);

    setTitle();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pcc-cal").forEach(initCalendar);
  });
})();