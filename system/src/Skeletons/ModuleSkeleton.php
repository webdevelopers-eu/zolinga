<?php

declare(strict_types=1);

namespace Zolinga\System\Skeletons;

use Zolinga\System\Events\{RequestResponseEvent, ListenerInterface};
use const Zolinga\System\ROOT_DIR;
use RecursiveDirectoryIterator, RecursiveIteratorIterator;
use RecursiveIterator;

/**
 * This class is a skeleton for a module.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-03-25
 */
class ModuleSkeleton implements ListenerInterface
{
    /**
     * Request event listener. 
     * 
     *   bin/zolinga skeleton:module --name=MyModule
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onModule(RequestResponseEvent $event): void
    {
        $moduleName = ($event->request['name'] ?? null);
        while (!is_string($moduleName) || strlen($moduleName) < 2) {
            $moduleName = preg_replace("/[^a-z0-9_.-]/i", "-", basename(readline("Enter module name (at least 3 chars long): ")));
        }
        $moduleName = basename($moduleName);
        $dst = ROOT_DIR . "/modules/$moduleName/";

        if (file_exists($dst)) {
            $event->setStatus($event::STATUS_CONFLICT, "Module $moduleName already exists ($dst).");
            return;
        }

        if (!is_writable(dirname($dst))) {
            $event->setStatus($event::STATUS_FORBIDDEN, "Directory " . dirname($dst) . " is not writable.");
            return;
        }

        $this->copyRecursive(ROOT_DIR . "/system/skeletons/module", $dst);

        $event->setStatus($event::STATUS_OK, "Module $moduleName created successfully.");
    }

    /**
     * Copy files recursively.
     * 
     * @param string $src
     * @param string $dst
     * @return void
     */
    private function copyRecursive(string $src, string $dst): void
    {
        /**
         * @var \RecursiveDirectoryIterator $iterator
         */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $currentSrc => $current) {
            $currentDst = $dst . $iterator->getSubPathname();
            if (!is_dir(dirname($currentDst))) {
                mkdir(dirname($currentDst), 0777, true);
            }

            if (is_dir($currentSrc)) {
                mkdir($currentDst, 0777, true);
            } elseif ($iterator->getBasename() !== '.keep')   {
                copy($currentSrc, $currentDst);
            }
        }
    }
}
