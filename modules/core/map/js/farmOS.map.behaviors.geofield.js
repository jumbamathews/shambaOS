(function () {
  shambaOS.map.behaviors.geofield = {
    attach: function (instance) {
      instance.editAttached.then(() => {
        instance.edit.wktOn('featurechange', function(wkt) {
          instance.map.getTargetElement().parentElement.querySelector('[data-map-geometry-field]').value = wkt;
        });
      });
    },

    // Make sure this runs after shambaOS.map.behaviors.wkt.
    weight: 101,
  };
}());
