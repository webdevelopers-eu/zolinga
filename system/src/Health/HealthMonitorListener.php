<?php

declare(strict_types=1);

namespace Zolinga\System\Health;

use Zolinga\System\Events\HealthCheckEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\StatusEnum;

/**
 * Listener for monitoring system health.
 * 
 * This listener checks various system health metrics like disk space
 * and reports issues to the health check event.
 * 
 * @author GitHub Copilot <copilot@github.com>
 * @since 2025-05-23
 */
class HealthMonitorListener implements ListenerInterface
{    
    /**
     * Handle the internal healthcheck event
     * 
     * @param HealthCheckEvent $event The health check event
     */
    public function onHealthcheck(HealthCheckEvent $event): void
    {
        global $api;

        $minSpace = ini_parse_quantity($api->config['health']['minSpace'] ?? '500') * 1024 * 1024; // Convert to bytes
        $error = false;

        // Get path to check (data directories - can be mounted)
        $pathToCheck = [
                $api->fs->toPath('private://system'),
                $api->fs->toPath('public://system'),
        ];

        // Check if the path exists
        foreach ($pathToCheck as $path) {
            if (!is_dir($path)) {
                $event->report('Disk Space', StatusEnum::ERROR, "Data directory not found: $path");
                return;
            }
            if (!is_writable($path)) {
                $event->report('Disk Space', StatusEnum::ERROR, "Data directory not writable: $path");
                return;
            }

            $freeSpace = disk_free_space($path);

            if ($freeSpace < $minSpace) {
                $totalSpace = disk_total_space($path);
                $percentFree = ($freeSpace / $totalSpace) * 100;
                $info = sprintf(
                    "Free disk space[$path]: %sMB free (%.1f%% of total %s). Minimum required: %s.",
                    round($freeSpace / 1024 / 1024),
                    $percentFree,
                    round($totalSpace / 1024 / 1024),
                    round($minSpace / 1024 / 1024)
                );
                // Report error if free space is below the minimum
                $event->report('Disk Space', StatusEnum::ERROR, "Low disk space: $info");
                $error = true;
            }
        }

        if (!$error) {
            $event->report('Disk Space', StatusEnum::OK, "Sufficient disk space >" . round($minSpace / 1024 / 1024) . "MB");
        }
    }
}
