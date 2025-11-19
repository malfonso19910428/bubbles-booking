(function () {
    function initAutocomplete() {
        // Si Google Maps / Places no está disponible, no hacemos nada
        if (!window.google || !google.maps || !google.maps.places) {
            return;
        }

        const input = document.getElementById("bb_address_input");
        if (!input) return;

        // Helper para buscar campos ocultos por id o por name
        function byIdOrName(name) {
            return (
                document.getElementById(name) ||
                document.querySelector('[name="' + name + '"]')
            );
        }

        const placeIdField = byIdOrName("bb_place_id");
        const streetField  = byIdOrName("bb_address_street");
        const cityField    = byIdOrName("bb_address_city");
        const stateField   = byIdOrName("bb_address_state");
        const zipField     = byIdOrName("bb_address_zip");
        const latField     = byIdOrName("bb_address_lat");
        const lngField     = byIdOrName("bb_address_lng");
        const preview      = document.getElementById("bb_address_preview");
        const errorBox     = document.getElementById("bb_address_error");

        // Autocomplete de Google
        const autocomplete = new google.maps.places.Autocomplete(input, {
            types: ["geocode"],
            componentRestrictions: { country: "us" }
        });

        let isProgrammatic = false; // para distinguir cambios de Google vs usuario

        // Limpia todos los campos estructurados
        function clearHidden() {
            if (placeIdField) placeIdField.value = "";
            if (streetField)  streetField.value  = "";
            if (cityField)    cityField.value    = "";
            if (stateField)   stateField.value   = "";
            if (zipField)     zipField.value     = "";
            if (latField)     latField.value     = "";
            if (lngField)     lngField.value     = "";
            if (preview)      preview.textContent = "";
        }

        // Si el usuario escribe manualmente, invalidamos lo anterior
        function invalidate() {
            if (isProgrammatic) return; // si viene de autocomplete, no tocamos
            clearHidden();
            if (errorBox) errorBox.textContent = "";
        }

        ["input", "keyup", "paste", "change"].forEach((evt) => {
            input.addEventListener(evt, invalidate);
        });

        // Cuando el usuario selecciona una sugerencia de Google
        autocomplete.addListener("place_changed", function () {
            isProgrammatic = true;

            const place = autocomplete.getPlace();
            clearHidden();

            if (!place || !place.address_components) {
                if (errorBox) {
                    errorBox.textContent = "Please select a valid address from suggestions.";
                }
                isProgrammatic = false;
                return;
            }

            let street = "";
            let city   = "";
            let state  = "";
            let zip    = "";

            (place.address_components || []).forEach((c) => {
                const t = c.types || [];

                if (t.includes("street_number")) {
                    street = c.long_name + " " + street;
                }
                if (t.includes("route")) {
                    street = (street || "") + c.long_name;
                }
                if (t.includes("locality") || t.includes("postal_town")) {
                    city = c.long_name;
                }
                if (t.includes("administrative_area_level_1")) {
                    state = c.short_name;
                }
                if (t.includes("postal_code")) {
                    zip = c.long_name;
                }
            });

            // Necesitamos al menos un número para evitar solo ciudades / regiones
            const base = street || input.value;
            if (!/\d/.test(base)) {
                clearHidden();
                if (errorBox) {
                    errorBox.textContent =
                        "Please select a street address with a house or business number.";
                }
                input.value = "";
                isProgrammatic = false;
                return;
            }

            // Guardamos en los hidden
            if (placeIdField) placeIdField.value = place.place_id || "";
            if (streetField)  streetField.value  = street;
            if (cityField)    cityField.value    = city;
            if (stateField)   stateField.value   = state;
            if (zipField)     zipField.value     = zip;

            if (place.geometry && place.geometry.location) {
                const loc = place.geometry.location;
                if (latField) latField.value = loc.lat();
                if (lngField) lngField.value = loc.lng();
            }

            // Texto de preview
            if (preview) {
                const parts = [];
                if (city)  parts.push(city);
                if (state) parts.push(state);
                if (zip)   parts.push(zip);
                preview.textContent = parts.length
                    ? "Detected: " + parts.join(", ")
                    : "";
            }

            // Reescribimos el input con una versión limpia
            let full = street;
            if (city)  full += ", " + city;
            if (state) full += ", " + state;
            if (zip)   full += " " + zip;
            if (full.trim() !== "") {
                input.value = full;
            }

            isProgrammatic = false;
        });

        // Submit del formulario (solo para reforzar la parte de la dirección)
        const form = input.closest("form");
        if (!form) return;

        // Hidden interno para decidir si validamos o no
        let validateField = form.querySelector('input[name="bb_validate_address"]');
        if (!validateField) {
            validateField = document.createElement('input');
            validateField.type = 'hidden';
            validateField.name = 'bb_validate_address';
            validateField.value = '';
            form.appendChild(validateField);
        }

        // Botones Continue / Back
        const continueBtn = form.querySelector('button[name="bb_continue"]');
        const backBtn     = form.querySelector('button[name="bb_back"]');

        if (continueBtn) {
            continueBtn.addEventListener('click', function () {
                validateField.value = '1';  // activar validación
            });
        }

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                validateField.value = '';   // desactivar validación
            });
        }

        form.addEventListener("submit", function (ev) {
            if (errorBox) errorBox.textContent = "";

            // 👉 Si NO se debe validar (ej: Back), salir sin hacer nada
            if (!validateField || validateField.value !== '1') {
                return;
            }

            let street  = streetField ? streetField.value.trim() : "";
            let city    = cityField   ? cityField.value.trim()   : "";
            let state   = stateField  ? stateField.value.trim()  : "";
            let zip     = zipField    ? zipField.value.trim()    : "";
            const visible = input.value.trim();

            // Si los hidden se perdieron al ir atrás/adelante, intentamos reconstruirlos
            if ((!street || !city || !state || !zip) && visible !== "") {
                const parts       = visible.split(",");
                const streetGuess = (parts[0] || "").trim();
                const cityGuess   = (parts[1] || "").trim();
                const stateZip    = (parts[2] || "").trim();

                const sz          = stateZip.split(/\s+/);
                const stateGuess  = sz[0] || "";
                const zipGuess    = sz[1] || "";

                if (streetField) streetField.value = streetGuess;
                if (cityField)   cityField.value   = cityGuess;
                if (stateField)  stateField.value  = stateGuess;
                if (zipField)    zipField.value    = zipGuess;

                street = streetGuess;
                city   = cityGuess;
                state  = stateGuess;
                zip    = zipGuess;
            }

            // Debe tener número en la dirección
            if (!/\d/.test(street || visible)) {
                ev.preventDefault();
                if (errorBox) {
                    errorBox.textContent =
                        "Please select a complete street address with house or business number.";
                }
                input.focus();
                return false;
            }

            // Debe tener ciudad, estado y ZIP
            if (!city || !state || !zip) {
                ev.preventDefault();
                if (errorBox) {
                    errorBox.textContent =
                        "Please select a full address including city, state, and ZIP code.";
                }
                input.focus();
                return false;
            }

            // ✅ Si todo bien, dejamos que el form se envíe (Continue avanza)
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAutocomplete);
    } else {
        initAutocomplete();
    }
})();
