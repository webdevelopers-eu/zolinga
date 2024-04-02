<?php

declare(strict_types=1);

namespace Zolinga\System\Installer;

use Zolinga\System\Events\{Event, InstallScriptEvent, ListenerInterface};
use Zolinga\System\{Mutex};
use Zolinga\System\Types\ModuleStatesEnum;
use const Zolinga\System\ROOT_DIR;
use ArrayObject, RuntimeException, RecursiveIteratorIterator, RecursiveDirectoryIterator;
use Composer\InstalledVersions;

/**
 * This is the installer controller that takes care of installing all modules.
 * It is triggered by the system:install event.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class InstallController implements ListenerInterface
{
    public function onInstall(Event $event): void
    {
        $this->install();
    }

    public function install(): void
    {
        global $api;

        $mutex = new Mutex('system:installer');
        if (!$mutex->lock()) { // already being installed by another process
            return;
        }

        $this->createDirectoryStructure();
        $installScriptListsAll = $this->getInstallScriptListsAll();
        $patches = $this->getSortedPatches($installScriptListsAll);

        foreach ($patches as $patchFile => $installScriptList) {
            $event = new InstallScriptEvent($patchFile);

            $api->log->info("system.install", "Dispatching $event for patch {$patchFile}.");
            $api->dispatchEvent($event);

            if ($event->status === InstallScriptEvent::STATUS_OK) {
                $installScriptList->markScriptAsApplied($patchFile);
                $installScriptList->save(); // Always save so we don't get surprised by some patch crash
                $api->log->info("system.install", "Patch {$patchFile} applied.", [
                    'status' => $event->status,
                    'message' => $event->message
                ]);
            } else {
                $api->log->error("system.install", "Patch {$patchFile} failed to apply.", [
                    'status' => $event->status,
                    'message' => $event->message
                ]);
            }
        }

        // Patches done, so make sure all modules are in "update" mode from mow on.
        foreach ($installScriptListsAll as $installScriptList) {
            $installScriptList->setUpdateMode();
        }

        $mutex->unlock();
    }

    /**
     * Create directory structure for all modules.
     *
     * @return void
     */
    private function createDirectoryStructure(): void
    {
        global $api;

        if (!is_dir(ROOT_DIR . '/data/system/installed')) {
            mkdir(ROOT_DIR . '/data/system/installed', 0777, true);
        }

        foreach ($api->manifest->manifestList as $fn) {
            $dir = dirname($fn);
            $state = $api->manifest->getState($fn);
            $name = basename($dir);

            $privateDir = ROOT_DIR . "/data/{$name}";

            if (!is_dir($privateDir)) {
                mkdir($privateDir, 0777, true)
                    or throw new RuntimeException("Failed to create directory: {$privateDir} ({$this->getSystemUserInfo()})");
            }

            if ($state === ModuleStatesEnum::NEW) {
                $this->copyRecursive(ROOT_DIR . $dir . '/install/private', $privateDir);
            }

            $publicDir = ROOT_DIR . "/public/data/{$name}";
            if (!is_dir($publicDir) && !mkdir($publicDir, 0777, true)) {
                throw new RuntimeException("Failed to create directory: {$publicDir} ({$this->getSystemUserInfo()})");
            }
            if ($state === ModuleStatesEnum::NEW) {
                $this->copyRecursive(ROOT_DIR . $dir . '/install/public', $publicDir);
            }

            $symlink = ROOT_DIR . "/public/dist/{$name}";
            if (is_link($symlink) && !unlink($symlink)) {
                throw new RuntimeException("Failed to remove symlink: {$symlink} ({$this->getSystemUserInfo()})");
            }
            if (is_dir(ROOT_DIR . $dir . '/install/dist') && !symlink(ROOT_DIR . $dir . '/install/dist', $symlink)) {
                throw new RuntimeException("Failed to create symlink from {$dir}/install/dist to {$symlink} ({$this->getSystemUserInfo()})");
            }
        }
    }

    /**
     * Copy files from source to destination recursively.
     *
     * @throws RuntimeException if source directory is not readable or destination directory cannot be created
     * @param string $source
     * @param string $dest
     * @return void
     */
    private function copyRecursive(string $source, string $dest): void
    {
        if (!is_dir($source)) {
            return;
        }

        // Ensure source exists and is readable
        if (!is_readable($source)) {
            throw new RuntimeException("Source directory $source is not readable");
        }

        // Create destination directory if it doesn't exist
        if (!is_dir($dest) && !mkdir($dest, 0755, true)) {
            throw new RuntimeException("Could not create destination directory $dest");
        }

        /**
         * @var \RecursiveDirectoryIterator $iterator
         */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } elseif (!file_exists($target)) {
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Get sorted list of patches with associated InstallScriptList objects
     *
     * @return array<string, InstallScriptList>
     */
    private function getSortedPatches(array $installScriptListsAll): array
    {
        global $api;

        $patches = [];
        foreach ($installScriptListsAll as $installScriptList) {
            foreach ($installScriptList->getNewScripts() as $patch) {
                $patches[$patch] = $installScriptList;
            }
        }

        // Sort patches by name in numerical order
        ksort($patches, SORT_NATURAL);

        return $patches;
    }

    /**
     * Get InstallScriptList objects for all modules.
     *
     * @return array<InstallScriptList>
     */
    private function getInstallScriptListsAll(): array
    {
        global $api;

        $installScriptLists = [];
        foreach ($api->manifest->manifestList as $fn) {
            $installScriptLists[] = new InstallScriptList(ROOT_DIR . $fn);
        }

        return $installScriptLists;
    }

    /**
     * This just returns debugging information used in case of an error.
     *
     * @return string
     */
    private function getSystemUserInfo(): string
    {
        $info = [
            'user name: ' . (posix_getpwuid(posix_geteuid()) ?: ["name" => "**could not be determined**"])['name'],
            'group: ' . (posix_getgrgid(posix_getegid()) ?: ["name" => "**could not be determined**"])['name']
        ];
        return implode(", ", $info);
    }
}
