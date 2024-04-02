<?php

declare(strict_types=1);

namespace Zolinga\System\Installer;

use Zolinga\System\Events\InstallScriptEvent;
use Zolinga\System\Events\ListenerInterface;

/**
 * Responsible for executing PHP installation or update scripts.
 * 
 * Listens on installation system:install:script:php event from the Installer.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-03-06
 */
class InstallPhpScript implements ListenerInterface
{

    public function onInstall(InstallScriptEvent $event): void
    {
        global $api;

        if ($event->ext !== 'php') {
            return;
        }

        try {
            $this->executeScript($event->patchFile);
            $event->setStatus(InstallScriptEvent::STATUS_OK, 'Script executed successfully.');
            $event->stopPropagation();
        } catch (\Throwable $e) {
            $api->log->error("system.install", $e, ['file' => $event->patchFile]);
            $event->setStatus(InstallScriptEvent::STATUS_ERROR, $e->getMessage());
            $event->stopPropagation();
        }
    }

    private function executeScript(string $script): void
    {
        global $api;
        require($script);
    }
}
