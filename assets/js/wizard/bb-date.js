(function () {
  "use strict";

  function isDateStep() {
    var step = document.querySelector("input[name='bb_step']");
    return step && String(step.value || "") === "date";
  }

  function getContinueButton() {
    // ✅ mismo patrón que Address: agarra el botón correcto
    return document.querySelector(".bb-btn-continue,[name='bb_continue']");
  }

  function setContinueDisabled(disabled) {
    // ✅ solo afecta el step date
    if (!isDateStep()) return;

    var btn = getContinueButton();
    if (!btn) return;

    btn.disabled = !!disabled;
    btn.classList.toggle("is-disabled", !!disabled);
  }

  function showErrorBox(el, msg) {
    if (!el) return;
    el.textContent = msg || "";
    el.style.display = msg ? "block" : "none";
  }

  function getHourFromTime(t) {
    if (!t) return null;
    var parts = String(t).split(":"); // "HH:MM" or "HH:MM:SS"
    var h = parseInt(parts[0], 10);
    return isFinite(h) ? h : null;
  }

  function bucketLabel(hour) {
    if (hour === null) return "Morning";
    if (hour < 12) return "Morning";
    if (hour < 17) return "Afternoon";
    return "Evening";
  }

  function initDateUX() {
    // ✅ solo corre en el step date
    if (!isDateStep()) return;

    var dateInput = document.getElementById("bb_date");
    var timeInput = document.getElementById("bb_time"); // hidden real
    var timeLabelInput = document.getElementById("bb_time_label");
    var panel = document.getElementById("bb-slots-panel");
    var hint = document.getElementById("bb-slot-hint");
    var errorBox = document.getElementById("bb-slot-error");

    if (!dateInput || !timeInput || !panel) return;

    var tokenEl =
      document.getElementById("bb_draft_token") ||
      document.querySelector('[name="bb_draft_token"]');
    var draftToken = tokenEl ? String(tokenEl.value || "").trim() : "";

    var ajaxUrl = (window.BBDateData && window.BBDateData.ajaxUrl) || window.ajaxurl || "";
    var action = (window.BBDateData && window.BBDateData.action) || "bb_get_slots";

    function markSelectedButton(value) {
      var btns = panel.querySelectorAll(".bb-slot-btn");
      for (var i = 0; i < btns.length; i++) {
        var b = btns[i];
        b.classList.toggle("is-selected", b.getAttribute("data-value") === value);
      }
    }

    function clearGroupsToPlaceholder() {
      var names = ["Morning", "Afternoon", "Evening"];
      for (var i = 0; i < names.length; i++) {
        var name = names[i];
        var body = panel.querySelector('.bb-slot-group[data-bucket="' + name + '"] .bb-slot-group-body');
        if (!body) continue;
        body.setAttribute("data-empty", "1");
        body.innerHTML =
          '<div class="bb-slot-placeholder">Select a date to see ' +
          name.toLowerCase() +
          " times</div>";
      }
      if (hint) hint.textContent = "Pick a date on the left to load available times.";
    }

    function setLoading() {
      var names = ["Morning", "Afternoon", "Evening"];
      for (var i = 0; i < names.length; i++) {
        var name = names[i];
        var body = panel.querySelector('.bb-slot-group[data-bucket="' + name + '"] .bb-slot-group-body');
        if (!body) continue;
        body.setAttribute("data-empty", "1");
        body.innerHTML = '<div class="bb-slot-placeholder">Loading…</div>';
      }
      if (hint) hint.textContent = "Loading available times…";
    }

    function resetTimeSelection() {
      timeInput.value = "";
      if (timeLabelInput) timeLabelInput.value = "";
      markSelectedButton("__none__");
      showErrorBox(errorBox, "");
      setContinueDisabled(true);
    }

    function setTimeSelection(value, label) {
      timeInput.value = value || "";
      if (timeLabelInput) timeLabelInput.value = label || "";
      markSelectedButton(value);
      showErrorBox(errorBox, "");
      setContinueDisabled(!value);
      if (hint && label) hint.textContent = "Selected: " + label;
    }

    function renderSlots(slots) {
      var groups = { Morning: [], Afternoon: [], Evening: [] };

      for (var i = 0; i < (slots || []).length; i++) {
        var s = slots[i];
        var h = getHourFromTime(s.start);
        var key = bucketLabel(h);
        if (!groups[key]) groups[key] = [];
        groups[key].push(s);
      }

      for (var bucket in groups) {
        if (!Object.prototype.hasOwnProperty.call(groups, bucket)) continue;

        var body = panel.querySelector('.bb-slot-group[data-bucket="' + bucket + '"] .bb-slot-group-body');
        if (!body) continue;

        var list = groups[bucket];

        if (!list.length) {
          body.setAttribute("data-empty", "1");
          body.innerHTML = '<div class="bb-slot-placeholder">No ' + bucket.toLowerCase() + " times</div>";
          continue;
        }

        body.removeAttribute("data-empty");
        body.innerHTML = "";

        for (var j = 0; j < list.length; j++) {
          var slot = list[j];
          var btn = document.createElement("button");
          btn.type = "button";
          btn.className = "bb-slot-btn";
          btn.setAttribute("data-value", slot.start);
          btn.setAttribute("data-label", slot.start + " - " + slot.end);
          btn.textContent = slot.start + " - " + slot.end;

          btn.addEventListener("click", function () {
            var val = this.getAttribute("data-value") || "";
            var lbl = this.getAttribute("data-label") || val;
            setTimeSelection(val, lbl);
          });

          body.appendChild(btn);
        }
      }

      if (hint) hint.textContent = "Pick a time on the right.";
    }

    function loadSlotsForDate(dateStr) {
      if (!dateStr) return;

      if (!draftToken || !ajaxUrl) {
        clearGroupsToPlaceholder();
        if (hint) hint.textContent = "Missing token / ajaxUrl.";
        return;
      }

      resetTimeSelection();
      setLoading();

      var url =
        ajaxUrl +
        "?action=" + encodeURIComponent(action) +
        "&date=" + encodeURIComponent(dateStr) +
        "&token=" + encodeURIComponent(draftToken);

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (payload) { renderSlots((payload && payload.data && payload.data.slots) ? payload.data.slots : []); })
        .catch(function () {
          clearGroupsToPlaceholder();
          if (hint) hint.textContent = "Could not load times. Try another date.";
        });
    }

    // Guard: si intenta continuar sin hora (en este step), bloquea y muestra error
    var btn = getContinueButton();
    if (btn) {
      btn.addEventListener("click", function (e) {
        if (!isDateStep()) return;
        if (!timeInput.value) {
          e.preventDefault();
          showErrorBox(errorBox, "Please select a time slot to continue.");
          panel.scrollIntoView({ behavior: "smooth", block: "center" });
          setContinueDisabled(true);
        }
      });
    }

    // Estado inicial
    setContinueDisabled(!timeInput.value);

    // Flatpickr inline
    if (typeof flatpickr !== "undefined") {
      var minDate = dateInput.dataset.min || "today";
      var maxDate = dateInput.dataset.max || null;

      flatpickr(dateInput, {
        inline: true,
        dateFormat: "Y-m-d",
        minDate: minDate,
        maxDate: maxDate,
        disable: [function (date) { return date.getDay() !== 1; }], // solo lunes
        locale: { firstDayOfWeek: 1 },
        onChange: function (selectedDates, dateStr) {
          loadSlotsForDate(dateStr);
        }
      });

      if (dateInput.value) {
        loadSlotsForDate(dateInput.value);
        // si ya hay hora guardada (volver atrás), permitir continuar
        setContinueDisabled(!timeInput.value);
      } else {
        clearGroupsToPlaceholder();
        resetTimeSelection();
      }
    } else {
      clearGroupsToPlaceholder();
      resetTimeSelection();
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    initDateUX();
  });
})();
