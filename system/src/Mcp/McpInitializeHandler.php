<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface, McpEvent};
use Zolinga\System\Types\StatusEnum;

/**
 * Handles the MCP `initialize` JSON-RPC method.
 *
 * Returns only the lifecycle initialization payload:
 *
 * - `protocolVersion` — MCP protocol version this server implements.
 * - `capabilities`    — server capabilities (`tools.listChanged`, etc.).
 * - `serverInfo`      — `{ name, title, version }` from the system manifest.
 * - `instructions`    — human-readable description of the server.
 *
 * Tool discovery lives in {@see McpTools::onList()} (the `tools/list` method).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpInitializeHandler implements ListenerInterface
{
    /**
     * MCP protocol version this server implements. Bump when behavior changes
     * in a way that MCP clients need to be aware of.
     */
    public const PROTOCOL_VERSION = '2025-06-18';

    /**
     * Server identification sent back to the client in the `serverInfo` block.
     *
     * @var array{name: string, title: string, version: string}
     */
    private array $serverInfo;

    public function __construct()
    {
        global $api;

        // We don't want to unnecesarily reveal system version
        // $manifest = $api->manifest->getModuleRealPathByName('system') ?: '';
        // $versionFile = $manifest . '/zolinga.json';
        // $version = '0.0.0';
        // if (is_file($versionFile)) {
        //     $data = json_decode((string) file_get_contents($versionFile), true);
        //     $version = is_array($data) ? (string) ($data['version'] ?? $version) : $version;
        // }
        $name = parse_url($api->config['baseURL'], PHP_URL_HOST) ?: 'Zolinga Server';

        $this->serverInfo = [
            'name' => $name,
            'title' => 'MCP Gateway for ' . $name,
            'version' => '1.0.0',
        ];
    }

    /**
     * Handle the MCP `initialize` request.
     *
     * @param McpEvent $event The event whose `type` is `initialize` and whose
     *                        `request` carries the JSON-RPC `params` payload.
     * @return void
     */
    public function onInitialize(McpEvent $event): void
    {
        $event->response = [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => $this->serverInfo,
            'instructions' => 'Use `tools/list` to refresh the list of available tools at any time. Use `tools/call` to invoke a tool by name with arguments.',
        ];

        $event->setStatus(StatusEnum::OK, 'Initialized.');
    }
}
