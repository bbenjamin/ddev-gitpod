<script>
  import { openPopup, getCommandsPopupMessage } from '../popup';
  // eslint-disable-next-line import/prefer-default-export
  import { uiCapabilities } from '../stores';

  export let project;
  export let buttons = [];
  export let loading;
  export let projectInstalled;
  export let projectDownloaded;
  export let showStatus;
  const secondaryButtons = buttons.splice(1);
  let open = false;
  let timeoutId;
  const { drupalSettings, Drupal } = window;

  /**
   * Installs an already downloaded module.
   */
  function installModule() {
    loading = true;
    const url = `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/activate-module/${project.project_machine_name}`;
    fetch(url)
      .then((res) => res.json())
      .then((json) => {
        if (json.status === 0) {
          drupalSettings.project_browser.modules[
            project.project_machine_name
          ] = 1;
          projectInstalled = true;
          loading = false;
        }
        const div = document.createElement('div');
        div.textContent = json.message;
        openPopup(div, project);
      });
  }

  /**
   * Uses package manager to download a module using Composer.
   *
   * @param {boolean} install
   *   If true, the module will be installed after it is downlaoded.
   */
  function downloadModule(install = false) {
    showStatus(true);
    const handleError = async (errorResponse) => {
      // If an error occurred, set loading to false so the UI no longer reports
      // the download/install as in progress.
      loading = false;

      // The error can take on many shapes, so it should be normalized.
      let err = '';
      if (typeof errorResponse === 'string') {
        err = errorResponse;
      } else {
        err = await errorResponse.text();
      }
      try {
        // See if the error string can be parsed as JSON. If not, the block
        // is exited before the `err` string is overwritten.
        const parsed = JSON.parse(err);
        err = parsed;
      } catch (error) {
        // The catch behavior is established before the try block.
      }
      const errorMessage = err.message || err;

      // The popup function expects an element, so a div containing the error
      // message is created here for it to display in a modal.
      const div = document.createElement('div');
      if (err.unlock_url && err.unlock_url !== '') {
        div.innerHTML += `<p>${errorMessage} <a href="${
          err.unlock_url
        }&destination=admin/modules/browse">${Drupal.t(
          'Unlock Install Stage',
        )}</a></p>`;
      } else {
        div.innerHTML += `<p>${errorMessage}</p>`;
      }
      openPopup(div, {
        ...project,
        title: `Error while installing ${project.title}`,
      });
    };

    /**
     * Performs the requests necessary to download a module via Package Manager.
     *
     * @return {Promise<void>}
     *   No return, but is technically a Promise because this function is async.
     */
    async function doRequests() {
      loading = true;
      const beginInstallUrl = `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/install-begin/${project.composer_namespace}`;
      const beginInstallResponse = await fetch(beginInstallUrl);
      if (!beginInstallResponse.ok) {
        await handleError(beginInstallResponse);
      } else {
        const beginInstallJson = await beginInstallResponse.json();
        const stageId = beginInstallJson.stage_id;

        // The process of adding a module is separated into four stages, each
        // with their own endpoint. When one stage completes, the next one is
        // requested.
        const installSteps = [
          `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/install-require/${project.composer_namespace}/${stageId}`,
          `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/install-apply/${project.composer_namespace}/${stageId}`,
          `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/install-post_apply/${project.composer_namespace}/${stageId}`,
          `${drupalSettings.project_browser.origin_url}/admin/modules/project_browser/install-destroy/${project.composer_namespace}/${stageId}`,
        ];
        let message = '';
        // eslint-disable-next-line no-restricted-syntax,guard-for-in
        for (const step in installSteps) {
          // eslint-disable-next-line no-await-in-loop
          const stepResponse = await fetch(installSteps[step]);
          message = stepResponse.message || '';
          if (!stepResponse.ok) {
            // eslint-disable-next-line no-await-in-loop
            const errorMessage = await stepResponse.text();
            // eslint-disable-next-line no-console
            console.warn(
              `failed request to ${installSteps[step]}: ${errorMessage}`,
              stepResponse,
            );
            // eslint-disable-next-line no-await-in-loop
            await handleError(errorMessage);
            return;
          }
        }

        // If this line is reached, then every stage of the download process
        // was completed without error and we can consider the module
        // downloaded and the process complete.
        drupalSettings.project_browser.modules[
          project.project_machine_name
        ] = 0;
        projectDownloaded = true;
        loading = false;

        // If install is true, install the module before conveying the process
        // is complete to the UI.
        if (install === true) {
          installModule();
        } else {
          // This block means the request was only to download the module, not
          // install it. Create a popup that reports the module as successfully
          // downloaded.
          const div = document.createElement('div');
          div.textContent =
            message ||
            Drupal.t('Download of @project complete.', {
              '@project': project.project_machine_name,
            });
          openPopup(div, project);
        }
      }
    }
    // Begin the install process, which is contained in the doRequests()
    // function so it can be async without its parent function having to be.
    doRequests();
  }

  // Each key of this object corresponds to a button type that can be added
  // by including it in the array assigned to the "buttons" property of this
  // component.
  const buttonMap = {
    commands: {
      onClick: () => openPopup(getCommandsPopupMessage(project), project),
      text: Drupal.t('View Commands'),
      disabled: false,
    },
    download: {
      onClick: () => downloadModule(),
      text: Drupal.t('Download (experimental)'),
      disabled: $uiCapabilities.pm_validation_error,
    },
    downloadAndInstall: {
      onClick: () => downloadModule(true),
      text: Drupal.t('Download and Install (experimental)'),
      disabled: $uiCapabilities.pm_validation_error,
    },
    install: {
      onClick: () => installModule(),
      text: Drupal.t('Install (experimental)'),
      disabled: false,
    },
  };
</script>

<div
  class="splitbutton-wrapper"
  on:mouseenter={() => clearTimeout(timeoutId)}
  on:mouseleave={() => {
    timeoutId = setTimeout(() => {
      open = false;
    }, 500);
  }}
>
  <div class="splitbutton-main">
    <button
      on:click={buttonMap[buttons[0]].onClick}
      class="button button--primary"
      disabled={buttonMap[buttons[0]].disabled}
      >{buttonMap[buttons[0]].text}<span class="visually-hidden"
        >{Drupal.t(' for ')} {project.title}</span
      >
    </button>
    <button
      on:click={() => {
        open = !open;
      }}
      aria-expanded={!open}
      aria-owns={`${project.project_machine_name}-actions`}
      aria-label="{Drupal.t('Available actions for ')}{project.title}"
      >{open ? '▲' : '▼'}
    </button>
  </div>
  <div
    id={`${project.project_machine_name}-actions`}
    class="splitbutton-additional"
    hidden={!open}
  >
    {#each secondaryButtons as button}
      <button
        class="button button--secondary"
        on:click={buttonMap[button].onClick}
        disabled={buttonMap[button].disabled}>{buttonMap[button].text}</button
      >
    {/each}
  </div>
</div>

<style>
  .button--primary,
  .button--secondary {
    color: #ffffff;
    height: 24px;
    font-size: 12.65px;
    line-height: 19px;
    display: flex;
    align-items: center;
    text-align: center;
    margin: 0;
    justify-content: center;
  }
  .button--secondary {
    background-color: #575757;
    padding-left: 12px;
  }
  .splitbutton-additional button {
    height: auto;
    border: 2px solid white !important;
    border-left-width: 0 !important;
    border-right-width: 0 !important;
  }
  .splitbutton-wrapper {
    position: relative;
  }
  .splitbutton-additional {
    position: absolute;
    z-index: 1;
  }
  .splitbutton-main {
    display: flex;
  }
</style>
