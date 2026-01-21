(function () {
  "use strict";

  function byIdOrName(name) {
    return (
      document.getElementById(name) ||
      document.querySelector('[name="' + name + '"]')
    );
  }

  function isAddressStep() {
    // Estamos en el wizard y el step actual es address
    var step = document.querySelector("input[name='bb_step']");
    return step && String(step.value || "") === "address";
  }

  function getDraftToken() {
    var el =
      document.getElementById("bb_draft_token") ||
      document.querySelector('[name="bb_draft_token"]');
    return el ? String(el.value || "").trim() : "";
  }

  function getComponent(components, type) {
    if (!components || !components.length) return "";
    for (var i = 0; i < components.length; i++) {
      var c = components[i];
      if (c && c.types && c.types.indexOf(type) !== -1) {
        return c.long_name || "";
      }
    }
    return "";
  }

  function getStateShort(components) {
    if (!components || !components.length) return "";
    for (var i = 0; i < components.length; i++) {
      var c = components[i];
      if (
        c &&
        c.types &&
        c.types.indexOf("administrative_area_level_1") !== -1
      ) {
        return c.short_name || c.long_name || "";
      }
    }
    return "";
  }

  function ensureStatusEl(input) {
    var id = "bb_address_coverage_status";
    var el = document.getElementById(id);
    if (el) return el;

    el = document.createElement("div");
    el.id = id;
    el.style.marginTop = "8px";
    el.style.fontSize = "13px";
    el.style.fontWeight = "600";
    input.parentNode.insertBefore(el, input.nextSibling);
    return el;
  }

  function setStatus(el, text) {
    if (!el) return;
    el.textContent = text || "";
  }

  function getContinueButton() {
    return document.querySelector(".bb-btn-continue,[name='bb_continue']");
  }

  function setContinueDisabled(disabled) {
    // ✅ IMPORTANT: solo afecta el step address
    if (!isAddressStep()) return;

    var btn = getContinueButton();
    if (!btn) return;

    btn.disabled = !!disabled;
    btn.classList.toggle("is-disabled", !!disabled);
  }

  function normalizeCoverageYes(data) {
    // Expected: { coverage: { yes: bool, reason } }
    if (data && data.coverage && typeof data.coverage.yes !== "undefined") {
      return { yes: !!data.coverage.yes, reason: data.coverage.reason || "" };
    }
    // fallback legacy
    if (data && typeof data.has_tech !== "undefined") {
      return { yes: !!data.has_tech, reason: data.reason || "" };
    }
    if (data && typeof data.ok !== "undefined") {
      return { yes: !!data.ok, reason: data.reason || "" };
    }
    if (data && typeof data.yes !== "undefined") {
      return { yes: !!data.yes, reason: data.reason || "" };
    }
    return { yes: false, reason: "unknown" };
  }

  function postCoverage(payload) {
    if (!window.BBAddressData || !window.BBAddressData.ajaxUrl) {
      return Promise.reject(new Error("BBAddressData missing"));
    }

    var body = new URLSearchParams();
    body.set("action", window.BBAddressData.action || "bb_wizard_address_coverage");
    body.set("nonce", window.BBAddressData.nonce || "");
    body.set("token", payload.token || "");
    body.set("lat", String(payload.lat || ""));
    body.set("lng", String(payload.lng || ""));
    body.set("place_id", payload.place_id || "");
    body.set("formatted", payload.formatted || "");

    return fetch(window.BBAddressData.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    }).then(function (res) {
      return res.json();
    });
  }

  function applyCoverageResult(statusEl, cov) {
    if (cov.yes === true) {
      setStatus(statusEl, "✅ We serve your area");
      setContinueDisabled(false);
      return;
    }

    var r = cov.reason || "out_of_service_area";
    if (r === "invalid_location") {
      setStatus(statusEl, "❌ Please select an address from suggestions");
    } else {
      setStatus(statusEl, "❌ Out of service area");
    }
    setContinueDisabled(true);
  }

  function initAutocomplete() {
    // Solo corre en Address step
    if (!isAddressStep()) return;

    if (!window.google || !google.maps || !google.maps.places) return;

    var input = document.getElementById("bb_address_input");
    if (!input) return;

    var statusEl = ensureStatusEl(input);

    // ✅ default safe state in Address
    setContinueDisabled(true);

    // Hidden fields
    var placeIdField = byIdOrName("bb_place_id");
    var streetField = byIdOrName("bb_address_street");
    var cityField = byIdOrName("bb_address_city");
    var stateField = byIdOrName("bb_address_state");
    var zipField = byIdOrName("bb_address_zip");
    var latField = byIdOrName("bb_address_lat");
    var lngField = byIdOrName("bb_address_lng");

    // typing invalidates
    input.addEventListener("input", function () {
      setContinueDisabled(true);
      setStatus(statusEl, "");
      if (placeIdField) placeIdField.value = "";
      if (latField) latField.value = "";
      if (lngField) lngField.value = "";
    });

    // re-check on back/refresh if lat/lng exist
    var token0 = getDraftToken();
    var lat0 = latField ? String(latField.value || "").trim() : "";
    var lng0 = lngField ? String(lngField.value || "").trim() : "";
    if (token0 && lat0 && lng0) {
      setStatus(statusEl, "Checking coverage…");
      postCoverage({
        token: token0,
        lat: lat0,
        lng: lng0,
        place_id: placeIdField ? (placeIdField.value || "") : "",
        formatted: (input.value || "").trim(),
      })
        .then(function (json) {
          if (json && json.success === true) {
            var cov0 = normalizeCoverageYes(json.data || {});
            applyCoverageResult(statusEl, cov0);
            return;
          }
          setContinueDisabled(true);
        })
        .catch(function () {
          setContinueDisabled(true);
        });
    }

    var ac = new google.maps.places.Autocomplete(input, {
      types: ["address"],
      fields: ["place_id", "address_components", "geometry", "formatted_address"],
    });

    ac.addListener("place_changed", function () {
      var place = ac.getPlace();
      if (!place) return;

      var comps = place.address_components || [];

      var streetNumber = getComponent(comps, "street_number");
      var route = getComponent(comps, "route");
      var city =
        getComponent(comps, "locality") ||
        getComponent(comps, "postal_town") ||
        getComponent(comps, "administrative_area_level_3");

      var stateShort = getStateShort(comps);
      var zip = getComponent(comps, "postal_code");
      var street =
        streetNumber && route ? streetNumber + " " + route : route || "";

      if (placeIdField) placeIdField.value = place.place_id || "";
      if (streetField) streetField.value = street || "";
      if (cityField) cityField.value = city || "";
      if (stateField) stateField.value = stateShort || "";
      if (zipField) zipField.value = zip || "";

      var lat = "";
      var lng = "";
      if (place.geometry && place.geometry.location) {
        var loc = place.geometry.location;
        lat = typeof loc.lat === "function" ? String(loc.lat()) : "";
        lng = typeof loc.lng === "function" ? String(loc.lng()) : "";
      }

      if (latField) latField.value = lat;
      if (lngField) lngField.value = lng;

      var token = getDraftToken();
      if (!token) {
        setStatus(statusEl, "⚠️ Missing draft token (cannot check coverage).");
        setContinueDisabled(true);
        return;
      }
      if (!lat || !lng) {
        setStatus(statusEl, "⚠️ Please select an address from suggestions.");
        setContinueDisabled(true);
        return;
      }

      var formatted = (place.formatted_address || input.value || "").trim();

      setStatus(statusEl, "Checking coverage…");
      setContinueDisabled(true);

      postCoverage({
        token: token,
        lat: lat,
        lng: lng,
        place_id: place.place_id || "",
        formatted: formatted,
      })
        .then(function (json) {
          if (!json || json.success !== true) {
            var reason =
              json && json.data && json.data.reason ? json.data.reason : "unknown";
            setStatus(statusEl, "❌ Coverage check failed (" + reason + ")");
            setContinueDisabled(true);
            return;
          }

          var cov = normalizeCoverageYes(json.data || {});
          applyCoverageResult(statusEl, cov);
        })
        .catch(function () {
          setStatus(statusEl, "❌ Coverage check error");
          setContinueDisabled(true);
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initAutocomplete();
  });
})();
