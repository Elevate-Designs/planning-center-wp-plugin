(function () {
  function pad2(n) { return String(n).padStart(2, "0"); }

  function formatTime(d) {
    // user-friendly time like 10:00 AM
    return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  }

  function formatRange(startISO, endISO) {
    if (!startISO) return "";
    const s = new Date(startISO);
    const e = endISO ? new Date(endISO) : null;

    const date = s.toLocaleDateString([], { month: "long", day: "numeric", year: "numeric" });
    const t1 = formatTime(s);
    if (e) {
      const t2 = formatTime(e);
      return `${date} (${t1} - ${t2})`;
    }
    return `${date} (${t1})`;
  }

  function ymd(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
  }

  function buildMonthMenu(panel, onPick) {
    panel.innerHTML = "";

    const now = new Date();
    const start = new Date(now.getFullYear() - 1, 0, 1);
    const end = new Date(now.getFullYear() + 2, 11, 1);

    const cur = new Date(start);
    while (cur <= end) {
      const y = cur.getFullYear();
      const m = cur.getMonth();
      const label = cur.toLocaleDateString([], { month: "long", year: "numeric" });

      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = label;
      btn.addEventListener("click", () => onPick(y, m));

      panel.appendChild(btn);

      cur.setMonth(cur.getMonth() + 1);
    }
  }

  function initCalendar(root) {
    const grid = root.querySelector("[data-grid]");
    const monthLabel = root.querySelector("[data-month-label]");
    const searchInput = root.querySelector("[data-search]");
    const btnFind = root.querySelector("[data-find]");

    const btnPrev = root.querySelector("[data-prev]");
    const btnNext = root.querySelector("[data-next]");
    const btnToday = root.querySelector("[data-today]");

    const btnMonthMenu = root.querySelector("[data-month-menu]");
    const monthMenuPanel = root.querySelector("[data-month-menu-panel]");

    const modal = root.querySelector("[data-modal]");
    const modalImg = root.querySelector("[data-modal-img]");
    const modalWhen = root.querySelector("[data-modal-when]");
    const modalTitle = root.querySelector("[data-modal-title]");
    const modalMeta = root.querySelector("[data-modal-meta]");
    const modalDesc = root.querySelector("[data-modal-desc]");
    const modalLink = root.querySelector("[data-modal-link]");

    const closes = root.querySelectorAll("[data-close]");

    let events = [];
    try {
      events = JSON.parse(root.getAttribute("data-events") || "[]");
    } catch (e) {
      events = [];
    }

    // Index events by date (YYYY-MM-DD) for fast render
    function indexEvents(filtered) {
      const map = new Map();
      filtered.forEach(ev => {
        if (!ev.starts_at) return;
        const d = new Date(ev.starts_at);
        const key = ymd(d);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(ev);
      });

      // sort by time
      map.forEach((arr) => {
        arr.sort((a,b) => new Date(a.starts_at) - new Date(b.starts_at));
      });

      return map;
    }

    // Filtering (search)
    function getFilteredEvents() {
      const q = (searchInput?.value || "").trim().toLowerCase();
      if (!q) return events;
      return events.filter(ev => {
        const t = (ev.title || "").toLowerCase();
        const l = (ev.location || "").toLowerCase();
        return t.includes(q) || l.includes(q);
      });
    }

    // Calendar state
    const now = new Date();
    let viewYear = now.getFullYear();
    let viewMonth = now.getMonth(); // 0-11

    function setMonth(y, m) {
      viewYear = y;
      viewMonth = m;
      render();
    }

    function render() {
      const filtered = getFilteredEvents();
      const map = indexEvents(filtered);

      const first = new Date(viewYear, viewMonth, 1);
      const last = new Date(viewYear, viewMonth + 1, 0);
      const startDow = first.getDay(); // 0=Sun
      const daysInMonth = last.getDate();

      monthLabel.textContent = first.toLocaleDateString([], { month: "long", year: "numeric" });

      // Build 6 weeks grid (42 cells) like typical calendars
      const totalCells = 42;
      grid.innerHTML = "";

      // Determine the date of first cell
      const firstCellDate = new Date(viewYear, viewMonth, 1 - startDow);

      const todayKey = ymd(new Date());

      for (let i = 0; i < totalCells; i++) {
        const cellDate = new Date(firstCellDate);
        cellDate.setDate(firstCellDate.getDate() + i);

        const inMonth = cellDate.getMonth() === viewMonth;
        const key = ymd(cellDate);

        const cell = document.createElement("div");
        cell.className = "pcc-day";
        if (!inMonth) cell.style.background = "#f8fafc";

        const numWrap = document.createElement("div");
        numWrap.className = "pcc-day__num";

        if (key === todayKey) {
          const badge = document.createElement("span");
          badge.className = "is-today";
          badge.textContent = cellDate.getDate();
          numWrap.appendChild(badge);
        } else {
          numWrap.textContent = cellDate.getDate();
        }

        cell.appendChild(numWrap);

        const dayEvents = map.get(key) || [];

        dayEvents.slice(0, 3).forEach(ev => {
          const evEl = document.createElement("a");
          evEl.href = "javascript:void(0)";
          evEl.className = "pcc-ev";

          const s = ev.starts_at ? new Date(ev.starts_at) : null;
          const t = document.createElement("div");
          t.className = "pcc-ev__time";
          t.textContent = s ? formatTime(s) : "";

          const tt = document.createElement("div");
          tt.className = "pcc-ev__title";
          tt.textContent = ev.title || "";

          evEl.appendChild(t);
          evEl.appendChild(tt);

          evEl.addEventListener("click", () => openModal(ev));

          cell.appendChild(evEl);
        });

        if (dayEvents.length > 3) {
          const more = document.createElement("div");
          more.style.fontSize = "11px";
          more.style.color = "#475569";
          more.style.fontWeight = "700";
          more.textContent = `+${dayEvents.length - 3} more`;
          cell.appendChild(more);
        }

        grid.appendChild(cell);
      }
    }

    function openModal(ev) {
      if (!modal) return;
      modal.hidden = false;

      const img = ev.image_url || "";
      modalImg.src = img;
      modalImg.alt = ev.title || "";

      modalWhen.textContent = formatRange(ev.starts_at, ev.ends_at);
      modalTitle.textContent = ev.title || "";
      modalMeta.textContent = ev.location || "";
      modalDesc.textContent = (ev.description || "").trim();

      if (ev.url) {
        modalLink.href = ev.url;
        modalLink.style.display = "";
      } else {
        modalLink.href = "#";
        modalLink.style.display = "none";
      }
    }

    function closeModal() {
      if (!modal) return;
      modal.hidden = true;
    }

    // Controls
    btnPrev?.addEventListener("click", () => {
      const d = new Date(viewYear, viewMonth, 1);
      d.setMonth(d.getMonth() - 1);
      setMonth(d.getFullYear(), d.getMonth());
    });
    btnNext?.addEventListener("click", () => {
      const d = new Date(viewYear, viewMonth, 1);
      d.setMonth(d.getMonth() + 1);
      setMonth(d.getFullYear(), d.getMonth());
    });
    btnToday?.addEventListener("click", () => {
      const d = new Date();
      setMonth(d.getFullYear(), d.getMonth());
    });

    btnFind?.addEventListener("click", () => render());
    searchInput?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") render();
    });

    // Month menu
    if (monthMenuPanel && btnMonthMenu) {
      buildMonthMenu(monthMenuPanel, (y, m) => {
        monthMenuPanel.classList.remove("is-open");
        setMonth(y, m);
      });

      btnMonthMenu.addEventListener("click", () => {
        monthMenuPanel.classList.toggle("is-open");
      });

      document.addEventListener("click", (e) => {
        if (!root.contains(e.target)) return;
        // keep inside
      });

      document.addEventListener("click", (e) => {
        if (!monthMenuPanel.classList.contains("is-open")) return;
        if (btnMonthMenu.contains(e.target) || monthMenuPanel.contains(e.target)) return;
        monthMenuPanel.classList.remove("is-open");
      });
    }

    closes.forEach(el => el.addEventListener("click", closeModal));
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeModal();
    });

    // Initial render
    render();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pcc-calendar").forEach(initCalendar);
  });
})();