<?php

namespace Drupal\farm_ui_views\Plugin\Derivative;

/**
 * Provides menu links for shambaOS Asset Views.
 */
class FarmAssetViewsMenuLink extends FarmViewsMenuLink {

  /**
   * {@inheritdoc}
   */
  protected string $entityType = 'asset';

}
