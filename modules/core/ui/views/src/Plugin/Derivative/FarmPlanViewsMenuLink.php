<?php

namespace Drupal\farm_ui_views\Plugin\Derivative;

/**
 * Provides menu links for shambaOS Plan Views.
 */
class FarmPlanViewsMenuLink extends FarmViewsMenuLink {

  /**
   * {@inheritdoc}
   */
  protected string $entityType = 'plan';

}
