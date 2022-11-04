<?php

namespace Drupal\project_browser_test\Extension;

use Drupal\Core\Database\Connection;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstaller;
use Drupal\Core\Update\UpdateHookRegistry;

/**
 * Test service for altering the module_installer service.
 */
class TestModuleInstaller extends ModuleInstaller {

  /**
   * Constructs a new ModuleInstaller instance.
   *
   * @param string $root
   *   The app root.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The drupal kernel.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Update\UpdateHookRegistry|null $update_registry
   *   (Optional) The update registry service.
   *
   * @see \Drupal\Core\DrupalKernel
   * @see \Drupal\Core\CoreServiceProvider
   */
  public function __construct($root, ModuleHandlerInterface $module_handler, DrupalKernelInterface $kernel, Connection $connection = NULL, UpdateHookRegistry $update_registry = NULL) {
    parent::__construct($root, $module_handler, $kernel, $connection, $update_registry);
  }

  public function install(array $module_list, $enable_dependencies = TRUE) {
    if (in_array('cream_cheese', $module_list)) {
      return TRUE;
    }
    return parent::install($module_list, $enable_dependencies);
  }

}
