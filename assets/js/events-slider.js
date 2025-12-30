function pccInitEventSliders() {
  document.querySelectorAll(".pcc-events-slider").forEach(slider => {
    if (slider.dataset.initialized) return;
    slider.dataset.initialized = "true";

    const track = slider.querySelector(".pcc-slider-track");
    const cards = slider.querySelectorAll(".pcc-event-card");
    const perView = parseInt(slider.dataset.perView || "3", 10);
    let index = 0;

    function update() {
      if (!cards.length) return;
      const cardWidth = cards[0].offsetWidth;
      const gap = parseInt(getComputedStyle(track).gap || 16, 10);
      const step = (cardWidth + gap) * perView;
      track.style.transform = `translateX(-${index * step}px)`;
    }

    slider.querySelector(".pcc-prev")?.addEventListener("click", () => {
      index = Math.max(index - 1, 0);
      update();
    });

    slider.querySelector(".pcc-next")?.addEventListener("click", () => {
      const maxIndex = Math.max(Math.ceil(cards.length / perView) - 1, 0);
      index = Math.min(index + 1, maxIndex);
      update();
    });

    window.addEventListener("resize", update);
    update();
    if (cards.length <= perView) {
    slider.querySelector(".pcc-prev")?.remove();
    slider.querySelector(".pcc-next")?.remove();
  }
  });


}

// Normal page load
document.addEventListener("DOMContentLoaded", pccInitEventSliders);

// Elementor / dynamic content
window.addEventListener("elementor/frontend/init", () => {
  elementorFrontend.hooks.addAction(
    "frontend/element_ready/global",
    pccInitEventSliders
  );
});

console.log('PCC slider loaded');