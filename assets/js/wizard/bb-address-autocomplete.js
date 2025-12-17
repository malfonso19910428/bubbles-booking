(function () {

  function byIdOrName(name) {
    return (
      document.getElementById(name) ||
      document.querySelector('[name="' + name + '"]')
    );
  }

  function getComponent(components, type) {
    if (!components || !components.length) return '';
    for (var i = 0; i < components.length; i++) {
      var c = components[i];
      if (c && c.types && c.types.indexOf(type) !== -1) {
        return c.long_name || '';
      }
    }
    return '';
  }

  function initAutocomplete() {
    // Si Google Places no está disponible, salir.
    if (!window.google || !google.maps || !google.maps.places) {
      // console.warn('Google Places not available');
      return;
    }

    var input = document.getElementById("bb_address_input");
    if (!input) return;

    // Hidden fields (por id o name)
    var placeIdField = byIdOrName("bb_place_id");
    var streetField  = byIdOrName("bb_address_street");
    var cityField    = byIdOrName("bb_address_city");
    var stateField   = byIdOrName("bb_address_state");
    var zipField     = byIdOrName("bb_address_zip");
    var latField     = byIdOrName("bb_address_lat");
    var lngField     = byIdOrName("bb_address_lng");

    // Autocomplete
    var ac = new google.maps.places.Autocomplete(input, {
      types: ['address'],
      fields: ['place_id', 'address_components', 'geometry']
    });

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place) return;

      // place_id
      if (placeIdField && place.place_id) {
        placeIdField.value = place.place_id;
      }

      // Address components
      var comps = place.address_components || [];

      var streetNumber = getComponent(comps, 'street_number');
      var route        = getComponent(comps, 'route');
      var city         = getComponent(comps, 'locality') ||
                         getComponent(comps, 'postal_town') ||
                         getComponent(comps, 'administrative_area_level_3');

      var stateShort = '';
      for (var i = 0; i < comps.length; i++) {
        var c = comps[i];
        if (c && c.types && c.types.indexOf('administrative_area_level_1') !== -1) {
          stateShort = c.short_name || c.long_name || '';
          break;
        }
      }

      var zip = getComponent(comps, 'postal_code');

      var street = (streetNumber && route) ? (streetNumber + ' ' + route) : (route || '');

      if (streetField) streetField.value = street;
      if (cityField)   cityField.value   = city || '';
      if (stateField)  stateField.value  = stateShort || '';
      if (zipField)    zipField.value    = zip || '';

      // Lat/Lng
      if (place.geometry && place.geometry.location) {
        var loc = place.geometry.location;
        if (latField) latField.value = (typeof loc.lat === 'function') ? String(loc.lat()) : '';
        if (lngField) lngField.value = (typeof loc.lng === 'function') ? String(loc.lng()) : '';
      }
    });
  }

  // Esperar DOM listo
  document.addEventListener('DOMContentLoaded', function () {
    initAutocomplete();
  });

})();
