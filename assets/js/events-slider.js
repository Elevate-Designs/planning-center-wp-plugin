(function () {
  "use strict";

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function getGapPx(track) {
    const cs = window.getComputedStyle(track);
    const gap = cs.columnGap || cs.gap || "0px";
    const n = parseFloat(gap);
    return Number.isFinite(n) ? n : 0;
  }

  function computePerView(slider) {
    const wanted = parseInt(slider.dataset.perView || "3", 10) || 3;

    // ✅ pakai container width (Divi kadang beda dari window width)
    const rect = slider.getBoundingClientRect();
    const w = rect.width || window.innerWidth || 1024;

    if (w <= 640) return 1;
    if (w <= 980) return Math.min(2, wanted);
    return clamp(wanted, 1, 6);
  }

  function applyPerView(slider) {
    const perView = computePerView(slider);
    slider.style.setProperty("--pcc-per-view", String(perView));
  }

  function getStep(viewport, track) {
    const first = track.querySelector(".pcc-slide");
    if (!first) return viewport.clientWidth;

    const gap = getGapPx(track);
    const rect = first.getBoundingClientRect();
    return rect.width + gap;
  }

  function updateButtons(slider) {
    const viewport = slider.querySelector(".pcc-slider-viewport");
    const prev = slider.querySelector(".pcc-prev");
    const next = slider.querySelector(".pcc-next");

    if (!viewport || !prev || !next) return;

    const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth;
    const x = viewport.scrollLeft;

    prev.disabled = x <= 2;
    next.disabled = x >= (maxScrollLeft - 2);
  }

  function initSlider(slider) {
    const viewport = slider.querySelector(".pcc-slider-viewport");
    const track = slider.querySelector(".pcc-slider-track");
    const prev = slider.querySelector(".pcc-prev");
    const next = slider.querySelector(".pcc-next");

    if (!viewport || !track) return;

    applyPerView(slider);
    updateButtons(slider);

    if (prev) {
      prev.addEventListener("click", function () {
        const step = getStep(viewport, track);
        viewport.scrollBy({ left: -step, behavior: "smooth" });
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        const step = getStep(viewport, track);
        viewport.scrollBy({ left: step, behavior: "smooth" });
      });
    }

    viewport.addEventListener(
      "scroll",
      function () {
        updateButtons(slider);
      },
      { passive: true }
    );

    // Recalc on resize + after fonts/layout settle
    window.addEventListener("resize", function () {
      applyPerView(slider);
      updateButtons(slider);
    });

    setTimeout(function () {
      applyPerView(slider);
      updateButtons(slider);
    }, 250);
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pcc-events-slider").forEach(initSlider);
  });
})();