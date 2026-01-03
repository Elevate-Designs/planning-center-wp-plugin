(function () {
  function initSlider(root) {
    const track = root.querySelector("[data-track]");
    if (!track) return;

    const prevBtn = root.querySelector('[data-action="prev"]');
    const nextBtn = root.querySelector('[data-action="next"]');

    function getCardWidth() {
      const card = track.querySelector("[data-card]");
      if (!card) return 320;
      const rect = card.getBoundingClientRect();
      return rect.width + 16; // gap
    }

    function scrollByCards(dir) {
      const w = getCardWidth();
      track.scrollBy({ left: dir * w, behavior: "smooth" });
    }

    if (prevBtn) prevBtn.addEventListener("click", () => scrollByCards(-1));
    if (nextBtn) nextBtn.addEventListener("click", () => scrollByCards(1));
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pcc-events-slider").forEach(initSlider);
  });
})();