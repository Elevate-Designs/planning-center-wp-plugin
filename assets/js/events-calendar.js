(function () {
  "use strict";

  function pad2(n) {
    return (n < 10 ? "0" : "") + n;
  }

  function isoDate(d) {
    return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
  }

  function monthLabel(year, monthIndex) {
    try {
      return new Intl.DateTimeFormat(undefined, { month: "long", year: "numeric" }).format(new Date(year, monthIndex, 1));
    } catch (e) {
      var m = monthIndex + 1;
      return year + "-" + pad2(m);
    }
  }

  function startOfMonthGrid(year, monthIndex) {
    var first = new Date(year, monthIndex, 1);
    var dow = first.getDay(); // 0=Sun
    var start = new Date(first);
    start.setDate(first.getDate() - dow);
    start.setHours(0, 0, 0, 0);
    return start;
  }

  function endOfMonthGrid(year, monthIndex) {
    var last = new Date(year, monthIndex + 1, 0);
    var dow = last.getDay();
    var end = new Date(last);
    end.setDate(last.getDate() + (6 - dow));
    end.setHours(23, 59, 59, 999);
    return end;
  }

  function fetchMonth(el, year, monthIndex) {
    var cfg = window.PCCalendar || {};
    var ajaxUrl = cfg.ajaxUrl || "";

    if (!ajaxUrl) {
      return Promise.reject(new Error("Missing ajaxUrl"));
    }

    var params = new URLSearchParams();
    params.set("action", "pcc_get_events_month");
    params.set("year", String(year));
    params.set("month", String(monthIndex + 1));
    params.set("public_only", el.dataset.publicOnly || "1");
    params.set("nonce", cfg.nonce || "");

    return fetch(ajaxUrl + "?" + params.toString(), {
      credentials: "same-origin",
      headers: { "Accept": "application/json" }
    }).then(function (r) {
      return r.json();
    });
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function stripHtml(s) {
    var div = document.createElement("div");
    div.innerHTML = String(s || "");
    return div.textContent || div.innerText || "";
  }

  function Calendar(root) {
    this.root = root;
    this.year = parseInt(root.dataset.year, 10) || new Date().getFullYear();
    this.monthIndex = (parseInt(root.dataset.month, 10) || (new Date().getMonth() + 1)) - 1;
    this.publicOnly = root.dataset.publicOnly === "1";

    this.daysEl = root.querySelector(".pcc-cal-days");
    this.monthEl = root.querySelector(".pcc-cal-month");
    this.loadingEl = root.querySelector(".pcc-cal-loading");
    this.errorEl = root.querySelector(".pcc-cal-error");
    this.popoverEl = root.querySelector(".pcc-cal-popover");

    this.searchInput = root.querySelector(".pcc-cal-search-input");
    this.searchBtn = root.querySelector(".pcc-cal-search-btn");

    this.items = [];
    this.itemsByDate = {};
    this.filteredQuery = "";

    this.bind();
    this.renderNav();
    this.load();
  }

  Calendar.prototype.bind = function () {
    var self = this;
    var prev = this.root.querySelector(".pcc-cal-prev");
    var next = this.root.querySelector(".pcc-cal-next");
    var today = this.root.querySelector(".pcc-cal-today");

    if (prev) {
      prev.addEventListener("click", function () {
        self.monthIndex -= 1;
        if (self.monthIndex < 0) {
          self.monthIndex = 11;
          self.year -= 1;
        }
        self.renderNav();
        self.load();
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        self.monthIndex += 1;
        if (self.monthIndex > 11) {
          self.monthIndex = 0;
          self.year += 1;
        }
        self.renderNav();
        self.load();
      });
    }

    if (today) {
      today.addEventListener("click", function () {
        var now = new Date();
        self.year = now.getFullYear();
        self.monthIndex = now.getMonth();
        self.renderNav();
        self.load();
      });
    }

    if (this.searchBtn) {
      this.searchBtn.addEventListener("click", function () {
        self.filteredQuery = (self.searchInput ? self.searchInput.value : "") || "";
        self.renderGrid();
      });
    }

    if (this.searchInput) {
      this.searchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          self.filteredQuery = self.searchInput.value || "";
          self.renderGrid();
        }
      });
    }

    // Popover close
    if (this.popoverEl) {
      var closeBtn = this.popoverEl.querySelector(".pcc-cal-popover-close");
      if (closeBtn) {
        closeBtn.addEventListener("click", function () {
          self.hidePopover();
        });
      }
      this.popoverEl.addEventListener("click", function (e) {
        if (e.target === self.popoverEl) {
          self.hidePopover();
        }
      });
    }
  };

  Calendar.prototype.renderNav = function () {
    if (this.monthEl) {
      this.monthEl.textContent = monthLabel(this.year, this.monthIndex);
    }
    this.root.dataset.year = String(this.year);
    this.root.dataset.month = String(this.monthIndex + 1);
  };

  Calendar.prototype.setLoading = function (on) {
    if (!this.loadingEl) return;
    this.loadingEl.hidden = !on;
  };

  Calendar.prototype.setError = function (msg) {
    if (!this.errorEl) return;
    if (!msg) {
      this.errorEl.hidden = true;
      this.errorEl.textContent = "";
      return;
    }
    this.errorEl.hidden = false;
    this.errorEl.textContent = msg;
  };

  Calendar.prototype.load = function () {
    var self = this;
    this.setError("");
    this.setLoading(true);

    fetchMonth(this.root, this.year, this.monthIndex)
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          throw new Error((payload && payload.data && payload.data.message) || "Failed to load events");
        }
        self.items = (payload.data && payload.data.items) ? payload.data.items : [];
        self.indexItems();
        self.renderGrid();
      })
      .catch(function (err) {
        self.items = [];
        self.itemsByDate = {};
        self.renderGrid();
        self.setError(err && err.message ? err.message : "Failed to load events");
      })
      .finally(function () {
        self.setLoading(false);
      });
  };

  Calendar.prototype.indexItems = function () {
    var map = {};
    for (var i = 0; i < this.items.length; i++) {
      var it = this.items[i];
      var d = it.start_date || "";
      if (!d) continue;
      if (!map[d]) map[d] = [];
      map[d].push(it);
    }
    // Sort each day by start_ts
    Object.keys(map).forEach(function (k) {
      map[k].sort(function (a, b) {
        return (a.start_ts || 0) - (b.start_ts || 0);
      });
    });
    this.itemsByDate = map;
  };

  Calendar.prototype.renderGrid = function () {
    if (!this.daysEl) return;

    var query = (this.filteredQuery || "").trim().toLowerCase();
    var showAll = query === "";

    // compute grid
    var start = startOfMonthGrid(this.year, this.monthIndex);
    var end = endOfMonthGrid(this.year, this.monthIndex);

    // Build 6 weeks max
    var html = "";
    var cursor = new Date(start);
    cursor.setHours(0, 0, 0, 0);
    var today = new Date();
    today.setHours(0, 0, 0, 0);

    while (cursor <= end) {
      var dStr = isoDate(cursor);
      var isOutside = cursor.getMonth() !== this.monthIndex;
      var isToday = cursor.getTime() === today.getTime();
      var dayNum = cursor.getDate();

      var events = (this.itemsByDate[dStr] || []).slice();
      if (!showAll) {
        events = events.filter(function (it) {
          var hay = (it.title || "") + " " + stripHtml(it.description || "") + " " + (it.location || "");
          return hay.toLowerCase().indexOf(query) !== -1;
        });
      }

      html += "<div class=\"pcc-cal-day" +
        (isOutside ? " is-outside" : "") +
        (isToday ? " is-today" : "") +
        "\" data-date=\"" + dStr + "\">";
      html += "<div class=\"pcc-cal-day-num\">" + dayNum + "</div>";

      if (events.length) {
        html += "<div class=\"pcc-cal-events\">";
        for (var i = 0; i < Math.min(3, events.length); i++) {
          var ev = events[i];
          var t = (ev.time_label || "");
          var tShort = "";
          if (t) {
            // Take start time only (before dash) for compact display.
            var parts = t.split("–");
            tShort = (parts[0] || "").trim();
          }
          html += "<button type=\"button\" class=\"pcc-cal-event\" data-event-id=\"" + escapeHtml(ev.id) + "\">"
            + (tShort ? ("<span class=\"pcc-cal-event-time\">" + escapeHtml(tShort) + "</span>") : "")
            + "<span class=\"pcc-cal-event-title\">" + escapeHtml(ev.title || "") + "</span>"
            + "</button>";
        }
        if (events.length > 3) {
          html += "<div class=\"pcc-cal-more\">+" + (events.length - 3) + " more</div>";
        }
        html += "</div>";
      }
      html += "</div>";

      cursor.setDate(cursor.getDate() + 1);
    }

    this.daysEl.innerHTML = html;
    this.bindGridClicks();
  };

  Calendar.prototype.bindGridClicks = function () {
    var self = this;
    var btns = this.daysEl.querySelectorAll(".pcc-cal-event");
    btns.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        var id = btn.getAttribute("data-event-id");
        var it = self.items.find(function (x) { return String(x.id) === String(id); });
        if (it) {
          self.showPopover(it, btn);
        }
      });
    });
  };

  Calendar.prototype.hidePopover = function () {
    if (!this.popoverEl) return;
    this.popoverEl.hidden = true;
  };

  Calendar.prototype.showPopover = function (item, anchor) {
    if (!this.popoverEl) return;

    var card = this.popoverEl.querySelector(".pcc-cal-popover-card");
    var thumb = this.popoverEl.querySelector(".pcc-cal-popover-thumb");
    var dt = this.popoverEl.querySelector(".pcc-cal-popover-datetime");
    var title = this.popoverEl.querySelector(".pcc-cal-popover-title");
    var loc = this.popoverEl.querySelector(".pcc-cal-popover-location");
    var desc = this.popoverEl.querySelector(".pcc-cal-popover-desc");
    var link = this.popoverEl.querySelector(".pcc-cal-popover-detail");

    if (thumb) {
      var img = item.image_url || "";
      thumb.innerHTML = img ? ("<img src=\"" + escapeHtml(img) + "\" alt=\"\">") : "";
    }
    if (dt) {
      var when = (item.date_label || "") + (item.time_label ? (" • " + item.time_label) : "");
      dt.textContent = when;
    }
    if (title) title.textContent = item.title || "";
    if (loc) loc.textContent = item.location || "";
    if (desc) {
      var text = stripHtml(item.description || "").trim();
      if (text.length > 220) text = text.slice(0, 220) + "…";
      desc.textContent = text;
    }
    if (link) {
      link.href = item.url || "#";
      link.style.display = item.url ? "inline-flex" : "none";
    }

    this.popoverEl.hidden = false;

    // Positioning:
    // - On small screens, use a centered modal.
    // - On larger screens, try to anchor near the clicked event.
    if (card) {
      var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
      var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

      if (vw < 780 || !anchor || !anchor.getBoundingClientRect) {
        card.style.position = "fixed";
        card.style.left = "50%";
        card.style.top = "50%";
        card.style.transform = "translate(-50%, -50%)";
        return;
      }

      // Anchor mode
      card.style.position = "fixed";
      card.style.transform = "none";

      // Temporarily place for measurement
      card.style.left = "12px";
      card.style.top = "12px";

      var r = anchor.getBoundingClientRect();
      var cardRect = card.getBoundingClientRect();
      var cardW = cardRect.width || 380;
      var cardH = cardRect.height || 260;

      // Default to the right of the anchor
      var left = r.right + 12;
      var top = r.top - 20;

      // Ensure inside viewport
      if (left + cardW > vw - 12) {
        left = Math.max(12, r.left - cardW - 12);
      }
      if (top + cardH > vh - 12) {
        top = Math.max(12, vh - cardH - 12);
      }
      if (top < 12) top = 12;

      card.style.left = left + "px";
      card.style.top = top + "px";
    }
  };

  function init() {
    var els = document.querySelectorAll(".pcc-events-calendar");
    els.forEach(function (el) {
      // Avoid double init
      if (el.__pccCal) return;
      el.__pccCal = new Calendar(el);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();