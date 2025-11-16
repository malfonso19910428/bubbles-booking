(function () {
  // --- Namespacing seguro ---
  window.BBWizard = window.BBWizard || {};
  var WZ = window.BBWizard;

  // Clave de almacenamiento
  var LS_KEY = 'bb_wizard_draft_v1';

  // Lee/guarda estado (no bloqueante si storage falla)
  function loadDraft() {
    try {
      var raw = localStorage.getItem(LS_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }
  function saveDraft(d) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(d || {})); } catch (e) {}
  }
  function clearDraft() {
    try { localStorage.removeItem(LS_KEY); } catch (e) {}
  }

  // Extrae datos visibles del paso actual (solo lo básico)
  function collectStepData(panel) {
    var data = {};
    if (!panel) return data;

    // Campos del picker (si están presentes)
    var year  = panel.querySelector('#bb-year');
    var make  = panel.querySelector('#bb-make');
    var model = panel.querySelector('#bb-model');
    var color = panel.querySelector('#bb-color');
    if (year || make || model || color) {
      data.vehicle = {
        year:  year  ? year.value.trim()  : '',
        make:  make  ? make.value.trim()  : '',
        model: model ? model.value.trim() : '',
        color: color ? color.value.trim() : ''
      };
    }

    // Paquete
    var pkg = panel.querySelector('input[name="bb_package"]:checked');
    if (pkg) { data.package = { id: pkg.value }; }

    // TODO: date/contact cuando añadas los inputs
    return data;
  }

  // Vuelca datos guardados si el formulario está vacío (no pisa lo que ya hay)
  function hydrateFromDraft(panel, draft) {
    if (!panel || !draft) return;

    if (draft.vehicle) {
      var v = draft.vehicle;
      var year  = panel.querySelector('#bb-year');
      var make  = panel.querySelector('#bb-make');
      var model = panel.querySelector('#bb-model');
      var color = panel.querySelector('#bb-color');
      if (year  && !year.value)  year.value  = v.year  || '';
      if (make  && !make.value)  make.value  = v.make  || '';
      if (model && !model.value) model.value = v.model || '';
      if (color && !color.value) color.value = v.color || '';
    }

    if (draft.package && draft.package.id) {
      var el = panel.querySelector('input[name="bb_package"][value="' + draft.package.id + '"]');
      if (el && !el.checked) el.checked = true;
    }
  }

  // Dispara re-init del picker dentro del panel actual
  function ensurePickerInit(panel) {
    // Evento público por si otros scripts escuchan
    document.dispatchEvent(new CustomEvent('bb-picker-mounted', { detail: { panel: panel || document } }));

    // Hook directo si expusiste una función global desde bb-picker.js
    if (window.BB_PICKER_INIT) {
      try { window.BB_PICKER_INIT(panel || document); } catch (e) {}
    }
  }

  // Marca el paso (para UI opcional) y guarda
  function setStepInDraft(step) {
    var d = loadDraft();
    d.step = step || d.step || 'vehicle';
    saveDraft(d);
  }

  // Encuentra el contenedor principal del wizard
  function getPanelRoot() {
    // Tu markup actual: .bb-panel contiene el step activo
    return document.querySelector('.bb-panel') || document;
  }

  // Wire events por cada render (se usa en DOMContentLoaded y también tras “navegar”)
  function wireStep(panel) {
    if (!panel) panel = getPanelRoot();

    // 1) Hidratar con lo que tengamos en draft (sin pisar inputs ya llenos)
    hydrateFromDraft(panel, loadDraft());

    // 2) Forzar init del picker en este contexto
    ensurePickerInit(panel);

    // 3) Guardar en localStorage cuando se pulse Continue/Back (sin impedir el POST)
    var form = panel.querySelector('form.bb-step-form');
    if (form && !form.__bb_wired) {
      form.__bb_wired = true;

      form.addEventListener('submit', function (ev) {
        // Guarda un snapshot rápido del paso
        var d = loadDraft();
        var extra = collectStepData(panel);
        for (var k in extra) { d[k] = extra[k]; }

        // Descubre paso “siguiente” de forma aproximada (solo para recordar)
        var stepInput = form.querySelector('input[name="bb_step"]');
        var current = stepInput ? stepInput.value : 'vehicle';

        // Si el usuario tocó Continue, asumimos que pasó al siguiente visualmente
        if (form.querySelector('button[name="bb_continue"][value]')) {
          var contBtn = ev.submitter && ev.submitter.name === 'bb_continue';
          if (contBtn) {
            d.step = guessNextStep(current);
          }
        }
        // Si tocó Back, retrocedemos en la pista local
        if (form.querySelector('button[name="bb_back"][value]')) {
          var backBtn = ev.submitter && ev.submitter.name === 'bb_back';
          if (backBtn) {
            d.step = guessPrevStep(current);
          }
        }

        saveDraft(d);
        // No prevenimos el submit: el servidor sigue controlando el flujo
      });
    }
  }

  // Orden de pasos (debe coincidir con PHP)
  var ORDER = ['vehicle','package','date','contact','review'];
  function guessNextStep(cur) {
    var i = ORDER.indexOf(cur);
    return (i >= 0 && i < ORDER.length - 1) ? ORDER[i + 1] : cur;
  }
  function guessPrevStep(cur) {
    var i = ORDER.indexOf(cur);
    return (i > 0) ? ORDER[i - 1] : cur;
  }

  // Exponer helpers (útil si luego llamas desde PHP inline)
  WZ.loadDraft = loadDraft;
  WZ.saveDraft = saveDraft;
  WZ.clearDraft = clearDraft;
  WZ.wireStep = wireStep;
  WZ.ensurePickerInit = ensurePickerInit;
  WZ.setStepInDraft = setStepInDraft;

  // Boot
  document.addEventListener('DOMContentLoaded', function () {
    var panel = getPanelRoot();

    // Marcar el paso actual (si PHP incluye un input hidden bb_step)
    var stepInput = panel.querySelector('input[name="bb_step"]');
    setStepInDraft(stepInput ? stepInput.value : 'vehicle');

    wireStep(panel);
  });
})();
