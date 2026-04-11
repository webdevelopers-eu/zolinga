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

        $this->ensureDirectory(ROOT_DIR . '/data/system/installed');

        foreach ($api->manifest->manifestList as $fn) {
            $dir = dirname($fn);
            $state = $api->manifest->getState($fn);
            $name = basename($dir);

            $this->syncModuleDataDirectories($dir, $name, $state);
            $this->syncModuleDistSymlink($dir, $name);
            $this->syncModuleSkills($dir, $name);
        }
    }

    /**
     * Ensure a directory exists.
     *
     * @throws RuntimeException
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new RuntimeException("Failed to create directory: {$path} ({$this->getSystemUserInfo()})");
        }
    }

    /**
     * Ensure module data directories exist and optionally copy defaults.
     */
    private function syncModuleDataDirectories(string $dir, string $name, ModuleStatesEnum $state): void
    {
        $privateDir = ROOT_DIR . "/data/{$name}";
        $publicDir = ROOT_DIR . "/public/data/{$name}";

        $this->ensureDirectory($privateDir);
        $this->ensureDirectory($publicDir);

        if ($state === ModuleStatesEnum::NEW) {
            $this->copyRecursive(ROOT_DIR . $dir . '/install/private', $privateDir);
            $this->copyRecursive(ROOT_DIR . $dir . '/install/public', $publicDir);
        }
    }

    /**
     * Ensure install/dist symlink is refreshed.
     */
    private function syncModuleDistSymlink(string $dir, string $name): void
    {
        $target = ROOT_DIR . "/public/dist/{$name}";
        $source = ROOT_DIR . $dir . '/install/dist';

        if (is_link($target) && !unlink($target)) {
            throw new RuntimeException("Failed to remove symlink: {$target} ({$this->getSystemUserInfo()})");
        }

        if (is_dir($source) && !symlink($source, $target)) {
            throw new RuntimeException("Failed to create symlink from {$dir}/install/dist to {$target} ({$this->getSystemUserInfo()})");
        }
    }

    /**
     * Sync module skills to .agents/skills.
     */
    private function syncModuleSkills(string $dir, string $moduleName): void
    {
        $skillsSource = ROOT_DIR . $dir . '/skills';
        $agentsSkillsDir = ROOT_DIR . '/.agents/skills';

        $this->ensureDirectory($agentsSkillsDir);

        // Remove all symlinks with module prefix
        foreach (new \DirectoryIterator($agentsSkillsDir) as $agentSkillLink) {
            if (!$agentSkillLink->isDot() 
                && $agentSkillLink->isLink()
                && str_starts_with($agentSkillLink->getFilename(), $moduleName . '-')
                && !unlink($agentSkillLink->getPathname())) {
                    trigger_error("Failed to remove existing skill symlink: {$agentSkillLink->getPathname()} ({$this->getSystemUserInfo()})", E_USER_WARNING);
            }
        }

        // Create new symlinks
        if (is_dir($skillsSource)) {
            foreach (new \DirectoryIterator($skillsSource) as $skillDir) {
                if ($skillDir->isDot() || !$skillDir->isDir()) {
                    continue;
                }
                if (!is_file($skillDir->getPathname() . '/SKILL.md')) {
                    trigger_error("Skipping skill directory {$skillDir->getPathname()} because it does not contain a SKILL.md file", E_USER_WARNING);
                    continue;
                }
                if (!str_starts_with($skillDir->getFilename(), $moduleName . '-')) {
                    trigger_error(
                        "Skipping skill directory {$skillDir->getPathname()} because its folder name does not start with the module name prefix '{$moduleName}-' " .
                        "The expected name is 'modules/<module>/skills/{$moduleName}-<skill-name>'", E_USER_WARNING);
                    continue;
                }
                $skillSymlink = $agentsSkillsDir . '/' . $skillDir->getFilename();
                if (!symlink($skillDir->getPathname(), $skillSymlink)) {
                    trigger_error("Failed to create skill symlink: {$skillSymlink} ({$this->getSystemUserInfo()})", E_USER_WARNING);
                }
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
