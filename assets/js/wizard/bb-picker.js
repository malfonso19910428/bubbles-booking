(function () {

  function bbNormalizeLabel(str) {
    if (!str) return '';
    str = String(str).trim();
    if (!str) return '';
    str = str.toLowerCase();
    return str.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function debounce(fn, ms) {
    var t;
    return function () {
      var a = arguments, ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, a); }, ms || 220);
    };
  }

  function filterContains(arr, q) {
    q = (q || "").toLowerCase().trim();
    if (!q) return arr.slice(0, 30);
    var starts = arr.filter(function (x) { return x.toLowerCase().indexOf(q) === 0; });
    var contains = arr.filter(function (x) { return x.toLowerCase().indexOf(q) > 0; });
    return starts.concat(contains).slice(0, 30);
  }

  // ✅ Helper: submit REAL para que viaje bb_vehicle_submit y el wizard avance
  function submitVehicleForm(form) {
    if (!form) return;

    // ✅ Forzar POST + action limpio (evita /?s=...&bb_vehicle_submit=1)
    try {
      form.method = "post";
      // Nota: si tu wizard vive en una URL con query, esto lo limpia igual
      form.action = window.location.origin + window.location.pathname;
    } catch (e) {}

    // Asegurar bb_step=vehicle
    var step = form.querySelector("input[name='bb_step']");
    if (step) step.value = "vehicle";

    // 1) Si existe el botón real submit del step vehicle -> click (mejor)
    var submitBtn = form.querySelector("button[name='bb_vehicle_submit']");
    if (submitBtn) {
      submitBtn.click();
      return;
    }

    // 2) requestSubmit con botón temporal (incluye submitter)
    if (typeof form.requestSubmit === "function") {
      var tmpBtn = document.createElement("button");
      tmpBtn.type = "submit";
      tmpBtn.name = "bb_vehicle_submit";
      tmpBtn.value = "1";
      tmpBtn.style.display = "none";
      form.appendChild(tmpBtn);
      form.requestSubmit(tmpBtn);
      tmpBtn.remove();
      return;
    }

    // 3) Fallback viejo: inyectar hidden bb_vehicle_submit y submit()
    var h = form.querySelector("input[name='bb_vehicle_submit']");
    if (!h) {
      h = document.createElement("input");
      h.type = "hidden";
      h.name = "bb_vehicle_submit";
      form.appendChild(h);
    }
    h.value = "1";
    form.submit();
  }

  // ========= AJAX helper =========
  function ajaxGet(action, params) {
    params = params || {};
    params.action = action;
    params.nonce = (window.BBPickerData && BBPickerData.nonce) ? BBPickerData.nonce : '';

    var base = (window.BBPickerData && BBPickerData.ajaxUrl) ? BBPickerData.ajaxUrl : '';
    if (!base) return Promise.resolve([]);

    var qs = Object.keys(params).map(function (k) {
      return encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
    }).join("&");

    return fetch(base + "?" + qs, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) { return (j && j.success) ? (j.data || []) : []; })
      .catch(function () { return []; });
  }

  // ========= Suggest core =========
  function mountSuggest(root, input, box, itemsFetcher, nextInput, opts) {
    if (!input || !box) return;
    opts = opts || {};

    function hideAll() {
      root.querySelectorAll(".bb-suggest").forEach(function (el) {
        el.style.display = "none";
      });
    }

    function pick(val) {
      input.value = bbNormalizeLabel(val);
      box.style.display = "none";
      input.blur();

      if (typeof opts.onPick === "function") {
        try { opts.onPick(input.value); } catch (e) {}
      }

      if (nextInput && nextInput.focus) {
        setTimeout(function () {
          nextInput.focus();
          if (nextInput.select) nextInput.select();
        }, 0);
      }
    }

    var fire = debounce(function () {
      var q = input.value || "";
      itemsFetcher(q, function (list) {
        if (!Array.isArray(list)) list = [];
        if (!list.length) {
          box.style.display = "none";
          box.innerHTML = "";
          return;
        }

        box.innerHTML = list.map(function (x) {
          var raw = String(x);
          var label = bbNormalizeLabel(raw);
          var safe = raw.replace(/"/g, '&quot;');
          return '<div class="bb-opt" data-value="' + safe + '">' + label + '</div>';
        }).join("");

        box.style.display = "block";

        box.querySelectorAll(".bb-opt").forEach(function (el) {
          el.addEventListener("click", function () {
            hideAll();
            var raw = el.getAttribute("data-value") || el.textContent;
            pick(raw);
          });
        });
      });
    }, 250);

    input.addEventListener("input", fire);
    input.addEventListener("focus", function () { fire(); });

    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        var first = box.querySelector(".bb-opt");
        if (first) {
          hideAll();
          var raw = first.getAttribute("data-value") || first.textContent;
          pick(raw);
        } else if (nextInput) {
          hideAll();
          input.blur();
          setTimeout(function () {
            nextInput.focus();
            if (nextInput.select) nextInput.select();
          }, 0);
        }
      } else if (e.key === "Escape") {
        box.style.display = "none";
      }
    });

    document.addEventListener("click", function (e) {
      if (!box.contains(e.target) && e.target !== input) {
        box.style.display = "none";
      }
    });
  }

  // ========= Data =========
  var years = [], maxY = (new Date()).getFullYear() + 1;
  for (var y = maxY; y >= 1980; --y) years.push(String(y));

  var popularMakes = [
    "Toyota", "Tesla", "Ford", "Chevrolet", "Honda", "Nissan", "Hyundai", "Kia", "BMW", "Mercedes-Benz",
    "Volkswagen", "Mazda", "Subaru", "Audi", "Lexus", "Jeep", "Dodge", "GMC", "Ram", "Cadillac",
    "Acura", "Infiniti", "Lincoln", "Volvo", "Porsche", "Land Rover", "Mini", "Mitsubishi",
    "Buick", "Chrysler", "Jaguar"
  ];

  var popularColors = [
    "Black", "White", "Silver", "Gray", "Grey", "Blue", "Dark Blue", "Navy", "Red", "Maroon",
    "Burgundy", "Green", "Dark Green", "Olive", "Beige", "Tan", "Brown", "Gold", "Yellow",
    "Orange", "Purple", "Pink", "Pearl", "Ivory", "Charcoal", "Teal", "Turquoise", "Bronze",
    "Copper", "Champagne", "Gunmetal", "Matte Black", "Gloss Black", "Metallic Blue"
  ];

  // ========= Cache =========
  var vpAllMakes = null;
  var vpModelsCache = {};
  var vpModelsPending = {};

  function fetchAllMakes() {
    if (vpAllMakes) return Promise.resolve(vpAllMakes);
    return ajaxGet("bb_fetch_makes", {}).then(function (list) {
      if (!Array.isArray(list) || !list.length) {
        vpAllMakes = popularMakes.slice(0);
        return vpAllMakes;
      }
      vpAllMakes = list;
      return vpAllMakes;
    });
  }

  function rankMakes(list, q) {
    q = (q || "").toLowerCase().trim();
    if (!q) return popularMakes.slice(0, 40);
    var exact = [], starts = [], contains = [];
    list.forEach(function (m) {
      var ml = m.toLowerCase();
      if (ml.indexOf(q) === -1) return;
      if (ml === q) exact.push(m);
      else if (ml.indexOf(q) === 0) starts.push(m);
      else contains.push(m);
    });
    return exact.concat(starts, contains).slice(0, 50);
  }

  function modelKey(make, year) {
    return String(make || "").trim().toLowerCase() + "|" + String(year || "").trim();
  }

  function fetchModels(make, year) {
    if (!make || !year) return Promise.resolve([]);
    var key = modelKey(make, year);

    if (vpModelsCache[key]) return Promise.resolve(vpModelsCache[key]);
    if (vpModelsPending[key]) return vpModelsPending[key];

    vpModelsPending[key] = ajaxGet("bb_fetch_models", { make: make, year: year })
      .then(function (list) {
        if (!Array.isArray(list)) list = [];
        vpModelsCache[key] = list;
        return list;
      })
      .catch(function () { return []; })
      .finally(function () { delete vpModelsPending[key]; });

    return vpModelsPending[key];
  }

  function prefetchModels(iMake, iYear) {
    var mk = (iMake && iMake.value || "").trim();
    var yr = (iYear && iYear.value || "").trim();
    if (!mk || !yr) return;
    fetchModels(mk, yr);
  }

  function initOne(root) {
    if (!root) root = document;

    var iYear = root.querySelector("#bb-year"),
      sYear = root.querySelector("#bb-year-suggest");
    var iMake = root.querySelector("#bb-make"),
      sMake = root.querySelector("#bb-make-suggest");
    var iModel = root.querySelector("#bb-model"),
      sModel = root.querySelector("#bb-model-suggest");
    var iColor = root.querySelector("#bb-color"),
      sColor = root.querySelector("#bb-color-suggest");

    if (!iYear || !iMake || !iModel || !iColor) return;

    // ✅ Save ONLY when the real submit happens (manual vehicle submit)
    var form = iYear.closest("form");
    if (form && !form.__bbVehBound) {
      form.__bbVehBound = true;

      form.addEventListener("submit", function (e) {
        // ✅ Solo en step vehicle
        var step = form.querySelector("input[name='bb_step']");
        if (!step || String(step.value || "") !== "vehicle") return;

        var submitter = e.submitter || document.activeElement;

        // Guardar si:
        // - submitter es el botón real bb_vehicle_submit
        // - o si existe hidden bb_vehicle_submit=1 (fallback)
        var shouldMark = false;

        if (submitter && submitter.name === "bb_vehicle_submit") {
          shouldMark = true;
        } else {
          var h = form.querySelector("input[name='bb_vehicle_submit']");
          if (h && String(h.value || "") === "1") shouldMark = true;
        }

        if (!shouldMark) return;

        // ✅ Evita que el hidden se quede pegado para submits futuros
        var h2 = form.querySelector("input[name='bb_vehicle_submit']");
        if (h2) h2.value = "";
      });
    }

    // Year
    mountSuggest(root, iYear, sYear, function (q, done) {
      done(filterContains(years, q));
    }, iMake, {
      onPick: function () { prefetchModels(iMake, iYear); }
    });

    // Make
    mountSuggest(root, iMake, sMake, function (q, done) {
      q = (q || "").trim();
      if (!q) { done(popularMakes); return; }

      if (vpAllMakes) { done(rankMakes(vpAllMakes, q)); return; }

      fetchAllMakes().then(function (all) {
        done(rankMakes(all, q));
      }).catch(function () {
        done(popularMakes);
      });
    }, iModel, {
      onPick: function () { prefetchModels(iMake, iYear); }
    });

    iYear.addEventListener("blur", function () { prefetchModels(iMake, iYear); });
    iMake.addEventListener("blur", function () { prefetchModels(iMake, iYear); });

    // Model
    mountSuggest(root, iModel, sModel, function (q, done) {
      var mk = (iMake.value || "").trim();
      var yr = (iYear.value || "").trim();
      if (!mk || !yr) { done([]); return; }

      var key = modelKey(mk, yr);

      function rankModels(list, q) {
        list = list || [];
        q = (q || "").toLowerCase().trim();
        if (!q) return list.slice(0, 50);
        var exact = [], starts = [], contains = [];
        list.forEach(function (m) {
          var ml = m.toLowerCase();
          if (ml === q) exact.push(m);
          else if (ml.indexOf(q) === 0) starts.push(m);
          else if (ml.indexOf(q) > 0) contains.push(m);
        });
        return exact.concat(starts, contains).slice(0, 50);
      }

      if (vpModelsCache[key]) {
        done(rankModels(vpModelsCache[key], q));
        return;
      }

      fetchModels(mk, yr).then(function (list) {
        done(rankModels(list, q));
      }).catch(function () {
        done([]);
      });
    }, iColor);

    // Color
    mountSuggest(root, iColor, sColor, function (q, done) {
      done(filterContains(popularColors, q));
    }, null);

    // Help
    var btnHelp = root.querySelector("#bb-help");
    var helpMsg = root.querySelector("#bb-help-msg");
    if (btnHelp && helpMsg) {
      btnHelp.addEventListener("click", function () {
        helpMsg.style.display = (helpMsg.style.display === "none" || !helpMsg.style.display) ? "block" : "none";
      });
    }

    // Reset model when year/make change
    iYear.addEventListener("input", function () {
      iModel.value = "";
      if (sModel) sModel.style.display = "none";
    });
    iMake.addEventListener("input", function () {
      iModel.value = "";
      if (sModel) sModel.style.display = "none";
    });
  }

  window.BB_PICKER_INIT = function (scope) { initOne(scope || document); };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () { initOne(document); });
  } else {
    initOne(document);
  }

})();
