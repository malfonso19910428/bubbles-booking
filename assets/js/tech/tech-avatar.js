(function () {
  "use strict";

  // evita doble init si el shortcode se imprime 2 veces
  if (window.__bbTechAvatarInited) return;
  window.__bbTechAvatarInited = true;

  function byId(id) {
    return document.getElementById(id);
  }

  function setPreviewFromUrl(url) {
    var wrap = byId("bb_profile_avatar_preview");
    if (!wrap) return;

    if (!url) {
      wrap.innerHTML = "";
      return;
    }

    wrap.innerHTML =
      '<img src="' +
      url +
      '" alt="Avatar" class="bb-profile-avatar" style="width:96px;height:96px;border-radius:9999px;object-fit:cover;">';
  }

  function show(el) {
    if (el) el.style.display = "";
  }

  function hide(el) {
    if (el) el.style.display = "none";
  }

  function init() {
    var pickBtn = byId("bb_pick_avatar");
    var removeBtn = byId("bb_remove_avatar");
    var idInput = byId("bb_tech_avatar_id");

    if (!pickBtn || !idInput) return; // no está la vista profile

    pickBtn.addEventListener("click", function () {
      // wp.media necesita wp_enqueue_media() en el render del shortcode
      if (!window.wp || !wp.media) {
        alert("Media Library no está disponible. Asegúrate de llamar wp_enqueue_media().");
        return;
      }

      var frame = wp.media({
        title: "Select avatar",
        button: { text: "Use this image" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var att = frame.state().get("selection").first();
        if (!att) return;

        var json = att.toJSON ? att.toJSON() : null;
        var id = json && json.id ? parseInt(json.id, 10) : 0;

        idInput.value = String(id || 0);

        // preview URL (prefer sizes.thumbnail)
        var url = "";
        if (json) {
          if (json.sizes && json.sizes.thumbnail && json.sizes.thumbnail.url) url = json.sizes.thumbnail.url;
          else if (json.url) url = json.url;
        }
        setPreviewFromUrl(url);

        show(removeBtn);
      });

      frame.open();
    });

    if (removeBtn) {
      removeBtn.addEventListener("click", function () {
        idInput.value = "0";
        setPreviewFromUrl("");
        hide(removeBtn);
      });
    }
  }

  // init cuando el DOM esté listo
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
