<?php

namespace Drupal\farm_update;

/**
 * Farm update service interface.
 */
interface FarmUpdateInterface {

  /**
   * Rebuild shambaOS configuration.
   */
  public function rebuild(): void;

}
