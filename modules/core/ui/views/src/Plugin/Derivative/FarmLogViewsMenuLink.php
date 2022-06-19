<?php

namespace Drupal\farm_ui_views\Plugin\Derivative;

/**
 * Provides menu links for shambaOS Log Views.
 */
class FarmLogViewsMenuLink extends FarmViewsMenuLink {

  /**
   * {@inheritdoc}
   */
  protected string $entityType = 'log';

}
