<?php

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Core\Site\Settings;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;
use Drupal\package_manager_bypass\Beginner;
use Drupal\Tests\BrowserTestBase;

abstract class ProjectBrowserInstallerFunctionalTestBase extends BrowserTestBase {

  use FixtureUtilityTrait;

  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser_test_disable_validators',
    'package_manager_bypass',
    'package_manager',
    'package_manager_test_validation',
  ];

  /**
   * The service IDs of any validators to disable.
   *
   * @var string[]
   */
  protected $disableValidators = [
    // Symlinks are part of the DrupalCI filesystem.
    'package_manager.validator.symlink',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->disableValidators($this->disableValidators);
    $pm_path = $this->container->get('extension.list.module')->getPath('package_manager');
    $this->useFixtureDirectoryAsActive($pm_path . '/tests/fixtures/fake_site');
  }

  /**
   * Disables validators in the test site's settings.
   *
   * This modifies the service container such that the disabled validators are
   * not defined at all. This method will have no effect unless the
   * project_browser_test_disable_validators module is installed.
   *
   * @param string[] $validators
   *   The service IDs of the validators to disable.
   *
   * @see \Drupal\project_browser_test_disable_validators\ProjectBrowserTestDisableValidatorsServiceProvider::alter()
   */
  protected function disableValidators(array $validators): void {
    $key = 'project_browser_test_disable_validators';
    $disabled_validators = Settings::get($key, []);

    foreach ($validators as $service_id) {
      $disabled_validators[] = $service_id;
    }
    $this->writeSettings([
      'settings' => [
        $key => (object) [
          'value' => $disabled_validators,
          'required' => TRUE,
        ],
      ],
    ]);
    $this->rebuildContainer();
  }

  /**
   * Sets a fixture directory to use as the active directory.
   *
   * @param string $fixture_directory
   *   The fixture directory.
   */
  protected function useFixtureDirectoryAsActive(string $fixture_directory): void {
    // Create a temporary directory from our fixture directory that will be
    // unique for each test run. This will enable changing files in the
    // directory and not affect other tests.
    $active_dir = $this->copyFixtureToTempDirectory($fixture_directory);
    Beginner::setFixturePath($active_dir);
    $this->container->get('package_manager.path_locator')
      ->setPaths($active_dir, $active_dir . '/vendor', '', NULL);
  }

  /**
   * Copies a fixture directory to a temporary directory.
   *
   * @param string $fixture_directory
   *   The fixture directory.
   *
   * @return string
   *   The temporary directory.
   */
  protected function copyFixtureToTempDirectory(string $fixture_directory): string {
    $temp_directory = $this->root . DIRECTORY_SEPARATOR . $this->siteDirectory . DIRECTORY_SEPARATOR . $this->randomMachineName(20);
    static::copyFixtureFilesTo($fixture_directory, $temp_directory);
    return $temp_directory;
  }

}
