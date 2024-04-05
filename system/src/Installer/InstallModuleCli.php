<?php

declare(strict_types=1);

namespace Zolinga\System\Installer;

use Zolinga\System\Events\{ListenerInterface, RequestResponseEvent};
use const Zolinga\System\ROOT_DIR;

/**
 * Install the module from the GIT repository.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-02
 */
class InstallModuleCli implements ListenerInterface
{
    const REPO_URL = 'https://raw.githubusercontent.com/webdevelopers-eu/zolinga-repo/main/repositories/master.json';
    const VERSION = '1.0';

    /**
     * Repository data.
     * @var array<mixed> $data
     */
    private array $data;

    public function __construct()
    {
        $this->data = $this->readDatabase();
        $version = isset($this->data['meta'], $this->data['meta']['version']) ? $this->data['meta']['version'] : "0";

        if (version_compare(self::VERSION, $version, '<')) {
            $this->message("🟠 Warning: The repository version is newer (v$version) then the version supported by this installer (v" . self::VERSION . "). Some features may not work correctly.");
        }
    }

    /**
     * Install the module. Syntax:
     * 
     * ./bin/zolinga install [--list] [--refresh] [--module=module-name[,module-name2,...]] 
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onInstall(RequestResponseEvent $event): void
    {
        if (isset($event->request['help']) || !$event->request) {
            $this->printHelp();
            return;
        }
        if (isset($event->request['refresh'])) {
            $this->downloadDatabase();
            $this->data = $this->readDatabase();
        }
        if (isset($event->request['list'])) {
            $this->listModules($event);
        }
        if (!empty($event->request['module'])) {
            $event->response['log'] = [];
            $moduleList = explode(',', $event->request['module']);
            for ($i = 0; $i < count($moduleList); $i++) {
                $info = $this->parseModuleString($moduleList[$i]);
                if ($this->checkModule($event, $info['id'], $info['branch'])) {
                    $event->response['log'][] = "Installing module {$info['id']} from {$info['source']}, branch {$info['branch']}";
                    $this->installModule($event, $info['source'], $info['id'], $info['branch']);

                    // Add dependencies
                    $config = json_decode(file_get_contents(ROOT_DIR . "/modules/{$info['id']}/zolinga.json") ?: 'null', true);
                    if (isset($config['dependencies'])) {
                        foreach ($config['dependencies'] as $dep) {
                            if (!in_array($dep, $moduleList)) {
                                $moduleList[] = $dep;
                            }
                        }
                    }
                } else {
                    $event->response['log'][] = "Module {$info['id']} is already installed.";
                }
            }
        }
    }

    /**
     * Return info about the module.
     *
     * @param string $moduleString
     * @return array<mixed>
     */
    private function parseModuleString(string $moduleString): array
    {
        // If module is a URL it may contain "@" character in the URL so we need to parse it differently
        if (!preg_match('/^(?<module>.+)(?:@(?<branch>[a-z0-9_-]+))?$/', $moduleString, $matches)) {
            throw new \Exception("Invalid module string: $moduleString");
        }

        $module = $matches['module'];
        $branch = $matches['branch'] ?? null;

        // Dynamic info created from the URL
        if (filter_var($module, FILTER_VALIDATE_URL)) {
            $id = basename(parse_url($module, PHP_URL_PATH), '.git')
                or throw new \Exception("Invalid module URL: $module");
            return [
                "id" => $id,
                "name" => $id,
                "description" => null,
                "source" => $module,
                "branch" => $branch ?: null
            ];
        }

        // Static repository-based
        list($info) = [...array_filter(
            $this->data['list'],
            fn ($item) => $item['id'] === $module
        ), null];

        if (!$info) {
            throw new \Exception('Module not found: ' . $module . '. Check the repository database at ' . self::REPO_URL . ' or try to refresh local copy by running `bin/zolinga install --refresh`');
        }
        $info['branch'] = $branch;
        return $info;
    }

    /**
     * Install the module.
     *
     * @param RequestResponseEvent $event
     * @param string $module
     * @return bool true all is ok, false if the module is already installed - $event->setStatus() is called automatically
     */
    private function checkModule(RequestResponseEvent $event, string $module, ?string $branch): bool
    {
        $targetDir = ROOT_DIR . "/modules/{$module}";

        // Check if the module is already installed and if so if the correct branch is checked out
        if (is_dir($targetDir)) {
            $currentBranch = null;
            if (is_dir("$targetDir/.git")) {
                $cmd = "command -p git --git-dir=" . escapeshellarg("$targetDir/.git") . " branch --show-current";
                $currentBranch = trim(shell_exec($cmd) ?: 'unknown');
            }
            if ($currentBranch === $branch || !$branch) {
                $event->setStatus($event::STATUS_OK, "Module is installed.");
                return false;
            } else {
                $event->setStatus($event::STATUS_PRECONDITION_FAILED, "Module $module (branch " . ($branch ?: 'not specified') . ") cannot be installed. There is already installed $module (branch $currentBranch)");
                return false;
            }
        }

        return true;
    }

    private function installModule(RequestResponseEvent $event, string $source, string $id, ?string $branch): void
    {
        $this->message("💿 Installing module $id from $source (branch " . ($branch ?: 'not specified') . ")");
        $params = [
            $source,
            ROOT_DIR . "/modules/{$id}"
        ];
        if ($branch) $params = ["-b", $branch, ...$params];

        $paramsEsc = implode(" ", array_map('escapeshellarg', $params));
        // We use the command -p to use the system PATH variable.
        // We redirect the output to stderr to avoid messing up default JSON output.
        $cmd = "command -p git clone $paramsEsc > /dev/stderr";

        if (passthru($cmd, $return) !== null || $return !== 0) {
            throw new \Exception("Could not install module: $id ($cmd)");
        }
        $event->setStatus($event::STATUS_OK, "Module installed: $id");
    }


    private function listModules(RequestResponseEvent $event): void
    {
        $event->response['modules'] = $this->data['list'];
    }

    /**
     * Return the repository data.
     *
     * @return array<mixed>
     */
    private function readDatabase(): array
    {
        if (!file_exists('private://system/repo/master.json')) {
            $this->downloadDatabase();
        }
        $data = json_decode(file_get_contents('private://system/repo/master.json') ?: 'null', true)
            or throw new \Exception('Could not read repository database: private://system/repo/master.json');

        return $data;
    }

    private function downloadDatabase(): void
    {
        $repo = file_get_contents(self::REPO_URL)
            or throw new \Exception('Could not download repository database: ' . self::REPO_URL);

        mkdir('private://system/repo', 0777, true);
        file_put_contents('private://system/repo/master.json', $repo)
            or throw new \Exception('Could not save repository database: private://system/repo/master.json');
    }

    private function message(string $msg): void
    {
        file_put_contents('php://stderr', "» $msg\n");
    }

    private function printHelp()
    {
        $this->message(<<<'HELP'
            Usage: ./bin/zolinga install \
                [--list] \
                [--refresh] \
                [--module=module-id[@branch],...]]

            See more in WIKI.

            Options:
                --list          List all available modules.
                --refresh       Refresh the repository database.
                --module        Install the module from the GIT repository.

            HELP);
    }
}
