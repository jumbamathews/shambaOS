<?php

namespace Drupal\Tests\farm_ui_theme\Functional;

use Drupal\Tests\farm_test\Functional\FarmBrowserTestBase;

/**
 * Tests the "Powered by shambaOS" block.
 *
 * @group farm
 */
class FarmBlockTest extends FarmBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'farm_ui_theme',
  ];

  /**
   * Test that the "Powered by shambaOS" block is visible.
   */
  public function testFarmBlock() {
    $this->assertSession()->pageTextContains('Powered by shambaOS');
  }

}
