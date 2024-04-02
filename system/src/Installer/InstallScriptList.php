<?php

declare(strict_types=1);

namespace Zolinga\System\Installer;

use JsonSerializable;
use const Zolinga\System\ROOT_DIR;

/**
 * Class that provides list of data for the module.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class InstallScriptList implements JsonSerializable
{
    private readonly string $recordFile;
    private readonly string $dir;
    public readonly string $name;
    private readonly string $scriptFolder;

    /**
     * Applied data or installation data.
     *
     * @var array<string, mixed>
     */
    private array $data = [
        "mode" => "install", // or "update"
        "lastUpdate" => 0,
        "since" => 0,
        "scripts" => [],
    ];

    public function __construct(string $manifestFile)
    {
        $this->dir = dirname($manifestFile);
        $this->name = basename($this->dir);
        $this->recordFile = ROOT_DIR . "/data/system/installed/{$this->name}.json";

        if (file_exists($this->recordFile)) {
            $this->load();
        }

        if ($this->data['mode'] === "install") {
            $this->data['since'] = $this->data['since'] ?: time();
            $this->skipUpdatesAll();
        }

        $this->scriptFolder = "{$this->dir}/install/{$this->data['mode']}";
    }

    private function load(): void
    {
        $raw = file_get_contents($this->recordFile);
        if ($raw === false) {
            throw new \RuntimeException("Failed to load module record file: {$this->recordFile}");
        }
        $data = json_decode($raw, true);
        if ($data === null) {
            throw new \RuntimeException("Failed to load module record file: {$this->recordFile}");
        }
        $this->data = $data;
    }

    public function setUpdateMode(): void
    {
        if ($this->data['mode'] !== "update") {
            $this->data['mode'] = "update";
            $this->save();
        }
    }

    public function save(): void
    {
        $this->data['lastUpdate'] = time();
        $data = json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($this->recordFile, $data) === false) {
            throw new \RuntimeException("Failed to save module record file: {$this->recordFile}");
        }
    }

    /**
     * Return the list of data that are not yet applied.
     *
     * @return array<string> List of script file names with full paths
     */
    public function getNewScripts(): array
    {
        $return = [];

        if (!is_dir($this->scriptFolder)) {
            return $return;
        }

        // List all files that are not already applied
        foreach (glob("{$this->scriptFolder}/*") ?: [] as $script) {
            if (preg_match('/\.(tmp|bak|old|.*~)$/', $script)) {
                continue;
            }
            $scriptId = $this->data['mode'] . '/' . basename($script);
            if (!isset($this->data['scripts'][$scriptId]) || !in_array($this->data['scripts'][$scriptId]['status'], ['skipped', 'installed'])) {
                $return[] = $script;
            }
        }

        return $return;
    }

    public function markScriptAsApplied(string $script): void
    {
        $scriptId = $this->data['mode'] . '/' . basename($script);
        $this->data['scripts'][$scriptId] = $this->data['scripts'][$scriptId] ?? [];
        $this->data['scripts'][$scriptId]['status'] = "installed";
        $this->data['scripts'][$scriptId]['stamp'] = time();
        $this->save(); // save always to avoid crashes when other data get applied
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    private function skipUpdatesAll(): void
    {
        foreach (glob("{$this->dir}/install/update/*") ?: [] as $script) {
            $scriptId = 'update/' . basename($script);
            $this->data['scripts'][$scriptId] = $this->data['scripts'][$scriptId] ?? [];
            $this->data['scripts'][$scriptId]['status'] = "skipped";
            $this->data['scripts'][$scriptId]['stamp'] = time();
            $this->save(); // save always to avoid crashes when other data get applied
        }
    }
}
