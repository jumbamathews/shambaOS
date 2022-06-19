(function () {
  // Make the built-in shambaOS-map 'sidePanel' behavior attach on map instantiation
  shambaOS.map.behaviors.sidePanel = shambaOS.map.namedBehaviors.sidePanel;

  // Make the built-in shambaOS-map 'layerSwitcherInSidePanel' behavior attach on map instantiation
  shambaOS.map.behaviors.layerSwitcherInSidePanel = shambaOS.map.namedBehaviors.layerSwitcherInSidePanel;
}());
