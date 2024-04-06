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
            $this->message(<<<'HELP'
                Usage: ./bin/zolinga install \
                    [--list] \
                    [--refresh] \
                    [--module=module-id[|branch],...]]

                See more in WIKI.

                Options:
                    --list          List all available modules.
                    --refresh       Refresh the repository database.
                    --module        Install the module from the GIT repository.

                HELP);
        }
        if (isset($event->request['refresh'])) {
            $this->downloadDatabase();
            $this->data = $this->readDatabase();
        }
        if (isset($event->request['list'])) {
            $this->listModules($event);
        }
        if (!empty($event->request['module'])) {
            foreach (explode(',', $event->request['module']) as $module) {
                $this->installModule($event, $module);
            }
        }
    }

    /**
     * Install the module.
     *
     * @param RequestResponseEvent $event
     * @param string $module
     * @return void
     */
    private function installModule(RequestResponseEvent $event, string $module): void
    {
        list($module, $branch) = explode('|', $module . '|');

        list($info) = [...array_filter(
            $this->data['list'],
            fn ($item) => $item['id'] === $module
        ), null];

        if (!$info) {
            throw new \Exception('Module not found: ' . $module);
        }

        echo "Installing module: $module from {$info['source']}\n";

        $params = [
            $info['source'],
            ROOT_DIR . "/modules/{$module}"
        ];
        if ($branch) $params = ["-b", $branch, ...$params];

        $paramsEsc = implode(" ", array_map('escapeshellarg', $params));
        // We use the command -p to use the system PATH variable.
        // We redirect the output to stderr to avoid messing up default JSON output.
        $cmd = "command -p git clone $paramsEsc > /dev/stderr";

        if (passthru($cmd, $return) !== null || $return !== 0) {
            throw new \Exception("Could not install module: $module ($cmd)");
        }
        $event->setStatus($event::STATUS_OK, "Module installed: $module");
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
}
