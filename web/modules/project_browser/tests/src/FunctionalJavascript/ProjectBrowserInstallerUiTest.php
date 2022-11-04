<?php

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\package_manager_bypass\Beginner;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;

/**
 * Provides tests for the Project Browser Installer UI.
 *
 * @group project_browser
 */
class ProjectBrowserInstallerUiTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait, FixtureUtilityTrait;

  /**
   * The shared tempstore object.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $sharedTempStore;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager',
    'package_manager_bypass',
    'project_browser',
    'project_browser_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sharedTempStore = $this->container->get('tempstore.shared');
    $pm_path = $this->container->get('extension.list.module')->getPath('package_manager');
    $this->useFixtureDirectoryAsActive($pm_path . '/tests/fixtures/fake_site');

    $this->config('project_browser.admin_settings')->set('enabled_sources', ['drupalorg_mockapi'])->save(TRUE);
    $this->config('project_browser.admin_settings')->set('allow_ui_install', TRUE)->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
    ]));
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

  /**
   * Tests module download functionality.
   */
  public function testModuleDownload(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    $cream_cheese_module_selector = '#project-browser .box-2 ul > li:nth-child(1)';
    $splitbutton_open = $page->find('css', "$cream_cheese_module_selector .splitbutton-main button:nth-child(2)");
    $splitbutton_open->click();
    $download_button = $assert_session->waitForElementVisible('css', "$cream_cheese_module_selector .splitbutton-additional button:nth-child(1)");
    $this->assertSame('Download (experimental)', $download_button->getText());
    $download_button->click();
    $popup = $assert_session->waitForElementVisible('css', '.project-browser-popup');
    $this->assertStringContainsString('Download of cream_cheese complete.', $popup->getText());
    $popup->find('css', 'button[title="Close"]')->click();

    $splitbutton_open->click();
    $splitbutton_items = $assert_session->waitForElementVisible('css', "$cream_cheese_module_selector .splitbutton-additional");
    $this->assertSame('Install (experimental)', $splitbutton_items->getText());
  }

  /**
   * Tests module download and enable functionality.
   */
  public function testModuleDownloadAndEnable(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    $cream_cheese_module_selector = '#project-browser .box-2 ul > li:nth-child(1)';
    $splitbutton_open = $page->find('css', "$cream_cheese_module_selector .splitbutton-main button:nth-child(2)");
    $splitbutton_open->click();
    $download_and_enable = $assert_session->waitForElementVisible('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(2)');
    $this->assertSame('Download and Install (experimental)', $download_and_enable->getText());
    $download_and_enable->click();
    $popup = $assert_session->waitForElementVisible('css', '.project-browser-popup');
    $this->assertStringContainsString('Project cream_cheese was installed successfully', $popup->getText());
    $popup->find('css', 'button[title="Close"]')->click();

    // The splitbutton should be replaced with an "installed" indicator.
    $cream_cheese_action_button = $assert_session->waitForElementVisible('css', "$cream_cheese_module_selector .action .button--secondary");
    $this->assertSame('Installed', $cream_cheese_action_button->getText());
    $assert_session->elementNotExists('css', "$cream_cheese_module_selector .splitbutton-main");
  }

  /**
   * Tests install UI not available if not enabled.
   */
  public function testAllowUiInstall(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    $assert_session->elementExists('css', '.splitbutton-main button:nth-child(2)');

    $this->drupalGet('/admin/config/development/project_browser');
    $assert_session->waitForText('Allow installing via UI (experimental)');
    $page->find('css', '#edit-allow-ui-install')->click();
    $assert_session->checkboxNotChecked('edit-allow-ui-install');
    $this->submitForm([], 'Save');
    $assert_session->waitForText('The configuration options have been saved.');

    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    $assert_session->buttonNotExists('▼');

    $this->drupalGet('/admin/config/development/project_browser');
    $assert_session->waitForText('Allow installing via UI (experimental)');
    $page->find('css', '#edit-allow-ui-install')->click();
    $assert_session->checkboxChecked('edit-allow-ui-install');
    $this->submitForm([], 'Save');
    $assert_session->waitForText('The configuration options have been saved.');

    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    $assert_session->buttonExists('▼');
  }

  /**
   * Confirms stage can be unlocked despite a missing Project Browser lock.
   *
   * @covers::unlock
   */
  public function testCanBreakStageWithMissingProjectBrowserLock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->getSession()->resizeWindow(1250, 1000);
    // Start install begin.
    $this->drupalGet('admin/modules/project_browser/install-begin/drupal/metatag');
    $this->sharedTempStore->get('project_browser')->delete('requiring');
    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    // Try beginning another install while one is in progress, but not yet in
    // the applying stage.
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-main button:nth-child(2)')->click();
    $assert_session->waitForElementVisible('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)');
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)')->click();

    $this->assertTrue($assert_session->waitForText('An install staging area claimed by Project Browser exists but has expired. You may unlock the stage and try the install again.'));
    // Click Unlock Install Stage link
    $this->clickWithWait('#ui-id-1 > p > a');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    // Try beginning another install after breaking lock.
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-main button:nth-child(2)')->click();
    $assert_session->waitForElementVisible('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)');
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)')->click();
    $this->assertTrue($assert_session->waitForText('Download of cream_cheese complete.'));
  }

  /**
   * Confirms the break lock link is available and works.
   *
   * The break lock link is not available once the stage is applying.
   *
   * @covers::unlock
   */
  public function testCanBreakLock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->getSession()->resizeWindow(1250, 1000);
    // Start install begin.
    $this->drupalGet('admin/modules/project_browser/install-begin/drupal/metatag');
    $this->drupalGet('admin/modules/browse');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    // Try beginning another install while one is in progress, but not yet in
    // the applying stage.
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-main button:nth-child(2)')->click();
    $assert_session->waitForElementVisible('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)');
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)')->click();
    $this->assertTrue($assert_session->waitForText('The install staging area was locked less than 1 minutes ago. This is recent enough that a legitimate installation may be in progress. Consider waiting before unlocking the installation staging area.'));
    // Click Unlock Install Stage link
    $this->clickWithWait('#ui-id-1 > p > a');
    $this->svelteInitHelper('text', 'Cream cheese on a bagel');
    // Try beginning another install after breaking lock.
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-main button:nth-child(2)')->click();
    $assert_session->waitForElementVisible('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)');
    $page->find('css', '#project-browser .box-2 ul > li:nth-child(1) .splitbutton-additional button:nth-child(1)')->click();
    $this->assertTrue($assert_session->waitForText('Download of cream_cheese complete.'));
  }

}
