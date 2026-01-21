(function () {
  "use strict";

  function byId(id) {
    return document.getElementById(id);
  }

  function byName(name) {
    return document.querySelector('[name="' + name + '"]');
  }

  function setVal(el, val) {
    if (!el) return;
    el.value = val ? String(val) : "";
  }

  function pickComponent(components, type) {
    if (!components) return "";
    const c = components.find(x => x.types && x.types.includes(type));
    return c ? (c.long_name || "") : "";
  }

  function pickShort(components, type) {
    if (!components) return "";
    const c = components.find(x => x.types && x.types.includes(type));
    return c ? (c.short_name || c.long_name || "") : "";
  }

  // ✅ City robust (US-proof)
  function pickCity(components) {
    return (
      pickComponent(components, "locality") ||
      pickComponent(components, "postal_town") ||
      pickComponent(components, "sublocality_level_1") ||
      pickComponent(components, "sublocality") ||
      pickComponent(components, "neighborhood") ||
      ""
    );
  }

  let inited = false;

  function hydrateFromHidden() {
    const input = byId("bb_tech_address_autocomplete");
    const hidden = byId("bb_tech_formatted");

    if (!input || !hidden) return false;
    if (!hidden.value) return false;

    input.value = hidden.value;
    return true;
  }

  function initAutocomplete() {
    if (inited) return true;
    if (!window.google || !google.maps || !google.maps.places) return false;

    const input = byId("bb_tech_address_autocomplete");
    if (!input) return false;

    inited = true;

    const ac = new google.maps.places.Autocomplete(input, {
      types: ["address"],
      fields: ["formatted_address", "address_components", "geometry", "place_id"]
    });

    // ✅ track last selected suggestion
    let lastSelectedFormatted = "";

    ac.addListener("place_changed", function () {
      const place = ac.getPlace();
      if (!place || !place.formatted_address) return;

      const formatted = place.formatted_address.trim();
      lastSelectedFormatted = formatted;

      const comps = place.address_components || [];

      const line1 = [
        pickComponent(comps, "street_number"),
        pickComponent(comps, "route")
      ].filter(Boolean).join(" ");

      const city  = pickCity(comps);
      const state = pickShort(comps, "administrative_area_level_1");
      const zip   = pickComponent(comps, "postal_code");

      let lat = "", lng = "";
      if (place.geometry && place.geometry.location) {
        lat = place.geometry.location.lat();
        lng = place.geometry.location.lng();
      }

      // 🔐 FUENTE ÚNICA para mostrar
      input.value = formatted;

      // ✅ Hidden IDs (lo que se guarda)
      setVal(byId("bb_tech_formatted"), formatted);
      setVal(byId("bb_tech_line1"), line1);
      setVal(byId("bb_tech_city"), city);
      setVal(byId("bb_tech_state"), state);
      setVal(byId("bb_tech_zip"), zip);

      setVal(byId("bb_tech_place_id"), place.place_id || "");
      setVal(byId("bb_tech_lat"), lat);
      setVal(byId("bb_tech_lng"), lng);

      // names (profile[])
      setVal(byName("profile[address][formatted]"), formatted);
      setVal(byName("profile[address][line1]"), line1);
      setVal(byName("profile[address][city]"), city);
      setVal(byName("profile[address][state]"), state);
      setVal(byName("profile[address][zip]"), zip);

      setVal(byName("profile[geo][place_id]"), place.place_id || "");
      setVal(byName("profile[geo][lat]"), lat);
      setVal(byName("profile[geo][lng]"), lng);
    });

    // ✅ If user edits manually, invalidate selection (prevents stale hidden)
    input.addEventListener("input", function () {
      const v = (input.value || "").trim();

      if (lastSelectedFormatted && v === lastSelectedFormatted) return;

      lastSelectedFormatted = "";

      // Clear hidden fields (so backend won’t accidentally accept stale data)
      setVal(byId("bb_tech_formatted"), "");
      setVal(byId("bb_tech_line1"), "");
      setVal(byId("bb_tech_city"), "");
      setVal(byId("bb_tech_state"), "");
      setVal(byId("bb_tech_zip"), "");

      setVal(byId("bb_tech_place_id"), "");
      setVal(byId("bb_tech_lat"), "");
      setVal(byId("bb_tech_lng"), "");

      setVal(byName("profile[address][formatted]"), "");
      setVal(byName("profile[address][line1]"), "");
      setVal(byName("profile[address][city]"), "");
      setVal(byName("profile[address][state]"), "");
      setVal(byName("profile[address][zip]"), "");

      setVal(byName("profile[geo][place_id]"), "");
      setVal(byName("profile[geo][lat]"), "");
      setVal(byName("profile[geo][lng]"), "");
    });

    return true;
  }

  function retry(fn, ms = 6000) {
    const start = Date.now();
    (function tick() {
      if (fn()) return;
      if (Date.now() - start > ms) return;
      setTimeout(tick, 200);
    })();
  }

  document.addEventListener("DOMContentLoaded", function () {
    retry(hydrateFromHidden);
    retry(initAutocomplete);

    // ✅ HARD SYNC on submit:
    // Lo que se ve en el input blanco ES lo que se guarda en formatted
    const form = document.querySelector("form");
    const input = byId("bb_tech_address_autocomplete");
    const hiddenFormatted = byId("bb_tech_formatted");
    const hiddenFormattedByName = byName("profile[address][formatted]");

    if (form && input && hiddenFormatted) {
      form.addEventListener("submit", function () {
        const v = (input.value || "").trim();
        if (!v) return;

        // 👇 Guarda exactamente lo visible (1 solo campo “verdad”)
        hiddenFormatted.value = v;
        if (hiddenFormattedByName) hiddenFormattedByName.value = v;
      });
    }
  });

})();
