(function () {
  'use strict';

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function escapeHtml(s) {
    s = (s == null) ? "" : String(s);
    return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
  }

  function trim(s){ return (s || '').toString().trim(); }
  function yearOk(y) { return /^\d{4}$/.test(trim(y)); }

  function titleCase(s) {
    s = trim(s).toLowerCase();
    if (!s) return "";
    return s.split(/\s+/).map(function (w) {
      return w ? (w.charAt(0).toUpperCase() + w.slice(1)) : "";
    }).join(" ");
  }

  function startsWithFilter(list, q, limit) {
    q = trim(q).toLowerCase();
    limit = limit || 20;
    if (!Array.isArray(list)) return [];
    if (!q) return list.slice(0, limit);

    var out = [];
    for (var i = 0; i < list.length; i++) {
      var v = String(list[i] || '');
      if (v.toLowerCase().indexOf(q) === 0) {
        out.push(v);
        if (out.length >= limit) break;
      }
    }
    return out;
  }

  function showSuggest(box, items, onPick) {
    if (!box) return;
    if (!items || !items.length) { box.style.display="none"; box.innerHTML=""; return; }
    box.style.display = "block";
    box.innerHTML = items.map(function (it) {
      return '<div class="bb-suggest-item" data-val="' + escapeHtml(it) + '">' + escapeHtml(it) + '</div>';
    }).join("");
    qsa(".bb-suggest-item", box).forEach(function (el) {
      el.addEventListener("mousedown", function (e) {
        e.preventDefault();
        onPick(el.getAttribute("data-val") || "");
      });
    });
  }
  function hideSuggest(box) { if (!box) return; box.style.display="none"; box.innerHTML=""; }

  function ajaxGet(action, params) {
    params = params || {};
    params.action = action;
    params.nonce  = BBPickerData.nonce;

    var url = BBPickerData.ajaxUrl + "?" + Object.keys(params).map(function (k) {
      return encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
    }).join("&");

    return fetch(url, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.success) throw (json || new Error("ajax error"));
        return json.data;
      });
  }

  function initVehiclePicker() {
    if (typeof window.BBPickerData === "undefined" || !BBPickerData.ajaxUrl || !BBPickerData.nonce) return false;

    var iYear  = qs("#bb-year");
    var iMake  = qs("#bb-make");
    var iModel = qs("#bb-model");
    var iColor = qs("#bb-color");
    var iType  = qs("#bb-vehicle-type");

    var sYear  = qs("#bb-year-suggest");
    var sMake  = qs("#bb-make-suggest");
    var sModel = qs("#bb-model-suggest");
    var sColor = qs("#bb-color-suggest");

    var iJobTargetId = qs("#bb-job-target-id");
    var jobTargetLbl = qs("#bb-job-target-label");

    var form = iYear ? iYear.closest("form") : null;

    if (!iYear || !iMake || !iModel || !iColor || !sYear || !sMake || !sModel || !sColor) return false;
    if (iMake.dataset.bbBound === "1") return true;

    var typingTimer = null;
    var TYPING_DELAY = 160;

    var makesAll = null;
    var modelsByMakeYear = {};

    var COLORS = [
      "Black","White","Silver","Gray","Red","Blue","Green","Yellow","Orange","Brown","Beige","Gold",
      "Purple","Pink","Burgundy","Maroon","Teal","Navy","Bronze","Champagne","Charcoal","Cream","Tan"
    ];

    var years = (function () {
      var out = [];
      var maxY = (new Date()).getFullYear() + 1;
      for (var y = maxY; y >= 1980; y--) out.push(String(y));
      return out;
    })();

    // ---- JobTarget/type helpers ----
    function setJobTargetLabel(text) { if (jobTargetLbl) jobTargetLbl.textContent = text || ""; }
    function clearJobTarget() { if (iJobTargetId) iJobTargetId.value = "0"; setJobTargetLabel(""); }

    function fetchJobTargetBySlug(slug) {
      slug = trim(slug);
      if (!slug) return Promise.resolve(null);
      return ajaxGet("bb_fetch_job_target", { industry: "auto", slug: slug })
        .then(function (data) { return (data && data.item) ? data.item : null; })
        .catch(function () { return null; });
    }

    function setJobTarget(item, mode) {
      if (!item || !item.id) return;
      if (iJobTargetId) iJobTargetId.value = String(item.id);
      setJobTargetLabel((mode || "Detected") + " vehicle type: " + (item.label || item.slug || ""));
    }

    function fetchVehicleType(make, model, year) {
      return ajaxGet("bb_fetch_vehicle_type", { make: trim(make), model: trim(model), year: trim(year) })
        .then(function (data) { return (data && data.suggested) ? data.suggested : "other"; })
        .catch(function () { return "other"; });
    }

    function applyTypeSuggested(suggested) {
      if (!iType || !suggested) return;

      // Solo bloquea autoselección si el usuario cambió el dropdown manualmente
      if (iType.__bbTouched) return;

      var opt = iType.querySelector('option[value="' + suggested + '"]');
      if (opt) iType.value = suggested;

      fetchJobTargetBySlug(suggested).then(function (jt) {
        if (jt && jt.id) setJobTarget(jt, "Detected");
        else clearJobTarget();
      });
    }

    function ensureJobTargetNow(modeLabel) {
      var make  = trim(iMake.value);
      var year  = trim(iYear.value);
      var model = trim(iModel.value);
      if (!make || !yearOk(year) || !model) return Promise.resolve(false);

      var typeVal = iType ? trim(iType.value) : "";
      if (typeVal) {
        return fetchJobTargetBySlug(typeVal).then(function (jt) {
          if (jt && jt.id) { setJobTarget(jt, modeLabel || "Selected"); return true; }
          clearJobTarget(); return false;
        });
      }

      return fetchVehicleType(make, model, year).then(function (suggested) {
        applyTypeSuggested(suggested);
        return true;
      });
    }

    // ---- Data fetch ----
    function ensureMakes() {
      if (makesAll) return Promise.resolve(makesAll);
      return ajaxGet("bb_fetch_makes", {})
        .then(function (data) { makesAll = Array.isArray(data) ? data : []; return makesAll; })
        .catch(function () { makesAll = []; return makesAll; });
    }

    function fetchModels(make, year) {
      make = trim(make);
      year = trim(year);
      if (!make || !yearOk(year)) return Promise.resolve([]);

      var key = make.toLowerCase() + "|" + year;
      if (modelsByMakeYear[key]) return Promise.resolve(modelsByMakeYear[key]);

      return ajaxGet("bb_fetch_models", { make: make, year: year })
        .then(function (data) {
          var models = Array.isArray(data) ? data : [];
          modelsByMakeYear[key] = models;
          return models;
        })
        .catch(function () { modelsByMakeYear[key] = []; return []; });
    }

    // ---- Year autocomplete ----
    function renderYearSuggest() {
      var q = trim(iYear.value).replace(/[^\d]/g,'').slice(0,4);
      if (iYear.value !== q) iYear.value = q;

      var list = startsWithFilter(years, q, 20);
      showSuggest(sYear, list, function (v) {
        iYear.value = v;
        hideSuggest(sYear);
        clearJobTarget();
        iMake.focus();
      });

      if (yearOk(iYear.value)) hideSuggest(sYear);
    }

    iYear.addEventListener("input", function () {
      clearJobTarget();
      hideSuggest(sModel);
      clearTimeout(typingTimer);
      typingTimer = setTimeout(renderYearSuggest, TYPING_DELAY);

      if (yearOk(iYear.value)) {
        hideSuggest(sYear);
        iMake.focus();
      }
    });

    iYear.addEventListener("focus", renderYearSuggest);
    iYear.addEventListener("blur", function () { setTimeout(function(){ hideSuggest(sYear); },150); });

    // ---- Make autocomplete ----
    iMake.addEventListener("input", function () {
      clearJobTarget();
      clearTimeout(typingTimer);

      typingTimer = setTimeout(function () {
        ensureMakes().then(function (all) {
          var filtered = startsWithFilter(all, iMake.value, 20);
          showSuggest(sMake, filtered, function (v) {
            iMake.value = titleCase(v);
            hideSuggest(sMake);

            // reset model/type/jobtarget
            iModel.value = "";
            hideSuggest(sModel);
            if (iType) { iType.value = ""; iType.__bbTouched = false; }
            clearJobTarget();

            iModel.focus();
          });
        });
      }, TYPING_DELAY);
    });

    iMake.addEventListener("blur", function () {
      setTimeout(function(){ hideSuggest(sMake); },150);
      if (trim(iMake.value)) iMake.value = titleCase(iMake.value);
    });

    // ---- Model autocomplete (FIX TYPE AUTOSELECT HERE) ----
    function showModelsDropdown(list, makeRaw, yearRaw) {
      showSuggest(sModel, list, function (pickedModelRaw) {
        // ✅ Guardamos "bonito" para UI
        iModel.value = titleCase(pickedModelRaw);
        hideSuggest(sModel);

        // ✅ VEHICLE TYPE: usar valores RAW (no Title Case) para llamar al endpoint
        // make: usamos el input real (puede ser TitleCase) pero también puede funcionar.
        // si quieres 100% robusto, manda makeRaw que viene del input (sin tocar).
        var makeForApi  = makeRaw;        // RAW
        var modelForApi = pickedModelRaw; // RAW
        var yearForApi  = yearRaw;

        // Resetea touched para permitir autoselect si el usuario no lo tocó
        if (iType && !iType.__bbTouched) {
          // keep false
        }

        fetchVehicleType(makeForApi, modelForApi, yearForApi).then(function (suggested) {
          applyTypeSuggested(suggested);
        });

        // ✅ salto a color
        iColor.focus();
      });
    }

    iModel.addEventListener("focus", function () {
      var make = trim(iMake.value);
      var year = trim(iYear.value);

      if (!yearOk(year) || !make) { hideSuggest(sModel); return; }

      fetchModels(make, year).then(function (models) {
        var list = (models || []).slice(0, 20);
        showModelsDropdown(list, make, year);
      });
    });

    iModel.addEventListener("input", function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(function () {
        var make = trim(iMake.value);
        var year = trim(iYear.value);
        if (!make || !yearOk(year)) { hideSuggest(sModel); return; }

        fetchModels(make, year).then(function (models) {
          var filtered = startsWithFilter(models, iModel.value, 20);
          showModelsDropdown(filtered, make, year);
        });
      }, TYPING_DELAY);
    });

    iModel.addEventListener("blur", function () {
      setTimeout(function(){ hideSuggest(sModel); },150);
      if (trim(iModel.value)) iModel.value = titleCase(iModel.value);
      setTimeout(function(){ ensureJobTargetNow("Detected"); }, 50);
    });

    // ---- Color autocomplete ----
    function renderColorSuggest() {
      var filtered = startsWithFilter(COLORS, iColor.value, 20);
      showSuggest(sColor, filtered, function (v) {
        iColor.value = titleCase(v);
        hideSuggest(sColor);
      });
    }

    iColor.addEventListener("input", function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(renderColorSuggest, TYPING_DELAY);
    });
    iColor.addEventListener("focus", renderColorSuggest);
    iColor.addEventListener("blur", function () {
      setTimeout(function(){ hideSuggest(sColor); },150);
      if (trim(iColor.value)) iColor.value = titleCase(iColor.value);
    });

    // ---- Type manual change ----
    if (iType) {
      iType.addEventListener("change", function () {
        iType.__bbTouched = true;
        var val = trim(iType.value);
        if (!val) { clearJobTarget(); return; }
        fetchJobTargetBySlug(val).then(function (jt) {
          if (jt && jt.id) setJobTarget(jt, "Selected");
          else clearJobTarget();
        });
      });
    }

    // ---- Submit safeguard ----
    if (form) {
      form.addEventListener("submit", function (e) {
        var cur = iJobTargetId ? parseInt(iJobTargetId.value || "0", 10) : 0;
        if (cur > 0) return;
        e.preventDefault();
        ensureJobTargetNow("Detected").then(function(){ form.submit(); });
      });
    }

    // ---- Normalize existing values ----
    if (trim(iMake.value)) iMake.value = titleCase(iMake.value);
    if (trim(iModel.value)) iModel.value = titleCase(iModel.value);
    if (trim(iColor.value)) iColor.value = titleCase(iColor.value);

    // ---- Bound marker ----
    iMake.dataset.bbBound = "1";
    log("[BB VEHICLE JS] bound OK", { ajaxUrl: BBPickerData.ajaxUrl });

    return true;
  }

  function start() {
    if (initVehiclePicker()) return;
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      if (initVehiclePicker() || tries >= 40) clearInterval(t);
    }, 250);
  }

  document.addEventListener("DOMContentLoaded", start);
  window.addEventListener("load", start);

})();
