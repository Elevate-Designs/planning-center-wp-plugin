(function () {
  "use strict";

  function pad2(n) {
    return String(n).padStart(2, "0");
  }

  function formatISODateLocal(d) {
    const y = d.getFullYear();
    const m = pad2(d.getMonth() + 1);
    const day = pad2(d.getDate());
    return `${y}-${m}-${day}`;
  }

  function monthLabel(year, month) {
    // month: 1..12
    try {
      const dt = new Date(year, month - 1, 1);
      return dt.toLocaleDateString(undefined, { month: "long", year: "numeric" });
    } catch (e) {
      return `${year}-${pad2(month)}`;
    }
  }

  function timeLabel(iso) {
    if (!iso) return "";
    const d = new Date(iso);
    if (isNaN(d.getTime())) return "";
    return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  }

  function clampMonthYear(year, month) {
    // month: 1..12
    let y = year, m = month;
    while (m < 1) { m += 12; y -= 1; }
    while (m > 12) { m -= 12; y += 1; }
    return { year: y, month: m };
  }

  function encodeForm(data) {
    const params = new URLSearchParams();
    Object.keys(data).forEach((k) => params.append(k, data[k]));
    return params.toString();
  }

  function initCalendar(root) {
    const cfg = window.pccCalendar || {};
    const ajaxUrl = cfg.ajaxUrl || (window.ajaxurl || "/wp-admin/admin-ajax.php");
    const nonce = cfg.nonce || "";
    const startOfWeek = typeof cfg.startOfWeek === "number" ? cfg.startOfWeek : 0; // 0 Sunday

    let year = parseInt(root.getAttribute("data-initial-year") || "0", 10);
    let month = parseInt(root.getAttribute("data-initial-month") || "0", 10);
    const publicOnly = (root.getAttribute("data-public-only") || "1") === "1";

    if (!year || !month) {
      const now = new Date();
      year = now.getFullYear();
      month = now.getMonth() + 1;
    }

    const gridEl = root.querySelector(".pcc-calendar-grid");
    const popoverEl = root.querySelector(".pcc-cal-popover");
    const prevBtn = root.querySelector(".pcc-cal-prev");
    const nextBtn = root.querySelector(".pcc-cal-next");
    const todayBtn = root.querySelector(".pcc-cal-today");
    const monthLabelEl = root.querySelector(".pcc-cal-monthlabel");
    const monthSelect = root.querySelector(".pcc-cal-monthselect");
    const searchInput = root.querySelector(".pcc-cal-search");
    const searchBtn = root.querySelector(".pcc-cal-search-btn");

    let items = [];
    let searchTerm = "";

    function setMonthSelectOptions() {
      if (!monthSelect) return;
      monthSelect.innerHTML = "";

      for (let i = 1; i <= 12; i++) {
        const opt = document.createElement("option");
        opt.value = String(i);
        opt.textContent = monthLabel(year, i).replace(String(year), "").trim() || new Date(year, i - 1, 1).toLocaleDateString(undefined, { month: "long" });
        if (i === month) opt.selected = true;
        monthSelect.appendChild(opt);
      }
    }

    function setHeaderLabel() {
      if (monthLabelEl) monthLabelEl.textContent = monthLabel(year, month);
      setMonthSelectOptions();
    }

    function closePopover() {
      if (!popoverEl) return;
      popoverEl.hidden = true;
      popoverEl.innerHTML = "";
    }

    function openPopover(anchorEl, item) {
      if (!popoverEl) return;

      const title = item.title || "";
      const img = item.image_url || "";
      const loc = item.location || "";
      const start = item.starts_at || "";
      const end = item.ends_at || "";
      const url = item.url || "";

      const dateTime = start
        ? `${new Date(start).toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" })} • ${timeLabel(start)}${end ? " - " + timeLabel(end) : ""}`
        : "";

      const descText = (item.description || "").replace(/<[^>]*>/g, "").trim();
      const desc = descText.length > 180 ? descText.slice(0, 180) + "…" : descText;

      popoverEl.innerHTML = `
        <div class="pcc-pop-card" role="dialog" aria-label="Event details">
          ${img ? `<div class="pcc-pop-img"><img alt="" src="${img}" loading="lazy"></div>` : ""}
          <div class="pcc-pop-body">
            <div class="pcc-pop-title">${escapeHtml(title)}</div>
            ${dateTime ? `<div class="pcc-pop-meta">${escapeHtml(dateTime)}</div>` : ""}
            ${loc ? `<div class="pcc-pop-loc">${escapeHtml(loc)}</div>` : ""}
            ${desc ? `<div class="pcc-pop-desc">${escapeHtml(desc)}</div>` : ""}
            ${url ? `<a class="pcc-pop-btn" href="${escapeAttr(url)}">${escapeHtml("Detail")}</a>` : ""}
          </div>
        </div>
      `;

      // Position near anchor
      const r = anchorEl.getBoundingClientRect();
      const pr = root.getBoundingClientRect();

      const top = r.top - pr.top + r.height + 8;
      const left = r.left - pr.left;

      popoverEl.style.top = `${Math.max(0, top)}px`;
      popoverEl.style.left = `${Math.max(0, left)}px`;
      popoverEl.hidden = false;
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      }[c]));
    }

    function escapeAttr(s) {
      return String(s).replace(/"/g, "&quot;");
    }

    function groupItemsByDate(list) {
      const map = new Map();
      list.forEach((it) => {
        const d = it.date || "";
        if (!d) return;
        if (!map.has(d)) map.set(d, []);
        map.get(d).push(it);
      });
      // Sort items per day by starts_at
      for (const [k, arr] of map.entries()) {
        arr.sort((a, b) => String(a.starts_at || "").localeCompare(String(b.starts_at || "")));
        map.set(k, arr);
      }
      return map;
    }

    function render() {
      if (!gridEl) return;

      closePopover();
      setHeaderLabel();

      // Filter by search
      const filtered = searchTerm
        ? items.filter((it) => (it.title || "").toLowerCase().includes(searchTerm.toLowerCase()))
        : items;

      const byDate = groupItemsByDate(filtered);

      const first = new Date(year, month - 1, 1);
      const daysInMonth = new Date(year, month, 0).getDate();
      const firstDow = first.getDay(); // 0..6
      const offset = (firstDow - startOfWeek + 7) % 7;

      const totalCells = 42; // 6 weeks
      const startDayNumber = 1 - offset;

      const dowNames = [];
      for (let i = 0; i < 7; i++) {
        const idx = (startOfWeek + i) % 7;
        const tmp = new Date(2023, 0, 1 + idx);
        dowNames.push(tmp.toLocaleDateString(undefined, { weekday: "short" }));
      }

      let html = `<div class="pcc-cal-dow">` + dowNames.map((n) => `<div class="pcc-cal-dowcell">${escapeHtml(n)}</div>`).join("") + `</div>`;
      html += `<div class="pcc-cal-cells">`;

      for (let i = 0; i < totalCells; i++) {
        const dayNum = startDayNumber + i;
        const cellDate = new Date(year, month - 1, dayNum);
        const inMonth = dayNum >= 1 && dayNum <= daysInMonth;
        const iso = formatISODateLocal(cellDate);

        const dayItems = byDate.get(iso) || [];
        const maxShow = 3;
        const shown = dayItems.slice(0, maxShow);
        const more = dayItems.length - shown.length;

        html += `<div class="pcc-cal-cell ${inMonth ? "" : "is-out"}" data-date="${iso}">
          <div class="pcc-cal-daynum">${cellDate.getDate()}</div>
          <div class="pcc-cal-list">`;

        shown.forEach((it, idx) => {
          const t = timeLabel(it.starts_at);
          const title = it.title || "";
          const key = it.instance_id || `${iso}-${idx}`;
          html += `<button type="button" class="pcc-cal-ev" data-key="${escapeAttr(key)}" data-date="${iso}">
              ${t ? `<span class="pcc-cal-time">${escapeHtml(t)}</span>` : ""}
              <span class="pcc-cal-ttl">${escapeHtml(title)}</span>
            </button>`;
        });

        if (more > 0) {
          html += `<button type="button" class="pcc-cal-more" data-date="${iso}">+${more} more</button>`;
        }

        html += `</div></div>`;
      }

      html += `</div>`;

      gridEl.innerHTML = html;

      // Bind event clicks (popover)
      gridEl.querySelectorAll(".pcc-cal-ev").forEach((btn) => {
        btn.addEventListener("click", () => {
          const key = btn.getAttribute("data-key");
          const item = items.find((x) => String(x.instance_id || "") === String(key)) ||
                       items.find((x) => String(x.event_id || "") === String(key)) ||
                       null;
          if (!item) return;
          openPopover(btn, item);
        });
      });

      // “more” shows simple alert list (can be upgraded to modal later)
      gridEl.querySelectorAll(".pcc-cal-more").forEach((btn) => {
        btn.addEventListener("click", () => {
          const d = btn.getAttribute("data-date") || "";
          const list = byDate.get(d) || [];
          const lines = list.map((it) => {
            const t = timeLabel(it.starts_at);
            return `${t ? t + " - " : ""}${it.title || ""}`;
          });
          alert(lines.join("\n"));
        });
      });
    }

    function setLoading(isLoading) {
      if (!gridEl) return;
      root.classList.toggle("is-loading", !!isLoading);
    }

    function fetchMonth() {
      setLoading(true);

      const body = encodeForm({
        action: "pcc_get_events_month",
        nonce: nonce,
        year: String(year),
        month: String(month),
        public_only: publicOnly ? "1" : "0",
      });

      return fetch(ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body,
        credentials: "same-origin",
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || !json.success) {
            throw new Error((json && json.data && json.data.message) ? json.data.message : "Request failed");
          }
          items = (json.data && Array.isArray(json.data.items)) ? json.data.items : [];
          render();
        })
        .catch((err) => {
          console.error(err);
          if (gridEl) {
            gridEl.innerHTML = `<div class="pcc-cal-error">Failed to load events.</div>`;
          }
        })
        .finally(() => setLoading(false));
    }

    function goPrev() {
      const r = clampMonthYear(year, month - 1);
      year = r.year; month = r.month;
      fetchMonth();
    }

    function goNext() {
      const r = clampMonthYear(year, month + 1);
      year = r.year; month = r.month;
      fetchMonth();
    }

    function goToday() {
      const now = new Date();
      year = now.getFullYear();
      month = now.getMonth() + 1;
      fetchMonth();
    }

    // Header controls
    if (prevBtn) prevBtn.addEventListener("click", goPrev);
    if (nextBtn) nextBtn.addEventListener("click", goNext);
    if (todayBtn) todayBtn.addEventListener("click", goToday);

    if (monthSelect) {
      monthSelect.addEventListener("change", () => {
        const m = parseInt(monthSelect.value || "0", 10);
        if (m >= 1 && m <= 12) {
          month = m;
          fetchMonth();
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        searchTerm = (searchInput.value || "").trim();
        render();
      });
    }
    if (searchBtn && searchInput) {
      searchBtn.addEventListener("click", () => {
        searchInput.focus();
      });
    }

    // Close popover on outside click
    document.addEventListener("click", (e) => {
      if (!popoverEl || popoverEl.hidden) return;
      if (root.contains(e.target) && (popoverEl.contains(e.target) || e.target.classList.contains("pcc-cal-ev"))) return;
      closePopover();
    });

    // Initial load
    fetchMonth();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pcc-calendar").forEach(initCalendar);
  });
})();