(function () {
  function initOne(slider) {
    if (!slider || slider.dataset.pccInit === "1") return;

    var perView = parseInt(slider.getAttribute("data-per-view") || "3", 10);
    if (!perView || perView < 1) perView = 3;

    var track = slider.querySelector(".pcc-slider-track");
    var prev = slider.querySelector(".pcc-prev");
    var next = slider.querySelector(".pcc-next");
    if (!track || !prev || !next) return;

    var cards = Array.prototype.slice.call(track.querySelectorAll(".pcc-event-card"));
    if (!cards.length) return;

    var index = 0;

    function measureStep() {
      if (!cards[0]) return 0;
      var cardW = cards[0].getBoundingClientRect().width || 0;

      var style = window.getComputedStyle(track);
      var gap = 0;

      // gap bisa column-gap atau gap
      var cg = parseFloat(style.columnGap || "0");
      var g = parseFloat(style.gap || "0");
      gap = isNaN(cg) ? (isNaN(g) ? 0 : g) : cg;

      return cardW + gap;
    }

    function maxIndex() {
      // total pages = ceil(cards/perView)
      var pages = Math.ceil(cards.length / perView);
      return Math.max(0, pages - 1);
    }

    function update() {
      var step = measureStep();
      var pageW = step * perView;
      var maxI = maxIndex();

      if (index < 0) index = 0;
      if (index > maxI) index = maxI;

      track.style.transform = "translateX(" + (-index * pageW) + "px)";

      prev.disabled = index === 0;
      next.disabled = index === maxI;

      // hide arrows if not needed
      if (cards.length <= perView) {
        prev.style.display = "none";
        next.style.display = "none";
      }
    }

    prev.addEventListener("click", function () {
      index -= 1;
      update();
    });

    next.addEventListener("click", function () {
      index += 1;
      update();
    });

    window.addEventListener("resize", function () {
      update();
    });

    slider.dataset.pccInit = "1";
    update();
  }

  function initAll() {
    var sliders = document.querySelectorAll(".pcc-events-slider");
    for (var i = 0; i < sliders.length; i++) {
      initOne(sliders[i]);
    }
  }

  document.addEventListener("DOMContentLoaded", initAll);
  window.addEventListener("load", initAll);

  // Untuk builder yang inject DOM belakangan
  var obs = new MutationObserver(function () {
    initAll();
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();