(function() {
    function initAutocomplete() {
        if (!window.google || !google.maps || !google.maps.places) {
            return;
        }

        var input = document.getElementById('bb_address_input');
        if (!input) return;

        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['geocode'],
            componentRestrictions: { country: 'us' }
        });

        var placeIdField = document.getElementById('bb_place_id');
        var streetField  = document.getElementById('bb_address_street');
        var cityField    = document.getElementById('bb_address_city');
        var stateField   = document.getElementById('bb_address_state');
        var zipField     = document.getElementById('bb_address_zip');
        var latField     = document.getElementById('bb_address_lat');
        var lngField     = document.getElementById('bb_address_lng');
        var preview      = document.getElementById('bb_address_preview');
        var errorBox     = document.getElementById('bb_address_error');

        function clearStructuredFields() {
            if (placeIdField) placeIdField.value = '';
            if (streetField)  streetField.value  = '';
            if (cityField)    cityField.value    = '';
            if (stateField)   stateField.value   = '';
            if (zipField)     zipField.value     = '';
            if (latField)     latField.value     = '';
            if (lngField)     lngField.value     = '';
            if (preview)      preview.textContent = '';
        }

        // 👇 Cada vez que el usuario escribe algo, reseteamos los datos estructurados
        input.addEventListener('input', function() {
            clearStructuredFields();
            if (errorBox) errorBox.textContent = '';
        });

        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();

            // Primero limpiamos
            clearStructuredFields();
            if (!place || !place.address_components) {
                if (errorBox) errorBox.textContent = 'Please select a valid address from the suggestions.';
                return;
            }

            if (errorBox) errorBox.textContent = '';

            // place_id (aunque no lo usemos en PHP, es útil tenerlo)
            if (placeIdField && place.place_id) {
                placeIdField.value = place.place_id;
            }

            var street = '';
            var city   = '';
            var state  = '';
            var zip    = '';

            (place.address_components || []).forEach(function(comp) {
                var types = comp.types || [];
                if (types.indexOf('street_number') !== -1) {
                    street = comp.long_name + ' ' + street;
                }
                if (types.indexOf('route') !== -1) {
                    street = (street || '') + comp.long_name;
                }
                if (types.indexOf('locality') !== -1 || types.indexOf('postal_town') !== -1) {
                    city = comp.long_name;
                }
                if (types.indexOf('administrative_area_level_1') !== -1) {
                    state = comp.short_name;
                }
                if (types.indexOf('postal_code') !== -1) {
                    zip = comp.long_name;
                }
            });

            if (streetField) streetField.value = street;
            if (cityField)   cityField.value   = city;
            if (stateField)  stateField.value  = state;
            if (zipField)    zipField.value    = zip;

            if (place.geometry && place.geometry.location) {
                var loc = place.geometry.location;
                if (latField) latField.value = typeof loc.lat === 'function' ? loc.lat() : loc.lat;
                if (lngField) lngField.value = typeof loc.lng === 'function' ? loc.lng() : loc.lng;
            }

            if (preview) {
                var parts = [];
                if (city)  parts.push(city);
                if (state) parts.push(state);
                if (zip)   parts.push(zip);
                preview.textContent = parts.length ? 'Detected location: ' + parts.join(', ') : '';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    } else {
        initAutocomplete();
    }
})();
