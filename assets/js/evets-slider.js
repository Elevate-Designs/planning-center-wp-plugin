document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".pcc-events-slider").forEach(slider => {
    const track = slider.querySelector(".pcc-slider-track");
    const cards = slider.querySelectorAll(".pcc-event-card");
    const perView = parseInt(slider.dataset.perView || "3", 10);

    let index = 0;

    function update() {
      const cardWidth = cards[0].offsetWidth;
      const gap = parseInt(getComputedStyle(track).gap || 0, 10);
      const step = (cardWidth + gap) * perView;
      track.style.transform = `translateX(-${index * step}px)`;
    }

    slider.querySelector(".pcc-prev").onclick = () => {
      index = Math.max(index - 1, 0);
      update();
    };

    slider.querySelector(".pcc-next").onclick = () => {
      const maxIndex = Math.max(Math.ceil(cards.length / perView) - 1, 0);
      index = Math.min(index + 1, maxIndex);
      update();
    };

    window.addEventListener("resize", update);
    update();
  });
});
