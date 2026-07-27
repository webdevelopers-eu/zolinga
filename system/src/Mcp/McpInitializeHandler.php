<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\InitializeEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles the MCP `initialize` lifecycle request.
 *
 * Returns the protocol version, server capabilities, server info, and
 * instructions for MCP clients.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpInitializeHandler implements ListenerInterface
{
    /** @var string MCP protocol version this gateway implements. */
    public const PROTOCOL_VERSION = '2025-06-18';

    /** @var array<string, mixed> Server info sent to the client. */
    private array $serverInfo;

    public function __construct()
    {
        global $api;
        $name = $api->config['mcp']['server']['name'] ?: (parse_url($api->config['baseURL'] ?: 'http://localhost/', PHP_URL_HOST));
        $title = $api->config['mcp']['server']['title'] ?: ('MCP Gateway for ' . $name);
        $version = $api->config['mcp']['server']['version'] ?: '9.10.2';
        $this->serverInfo = [
            'name' => $name,
            'title' => $title,
            'version' => $version,
        ];
    }

    /**
     * Handle the `initialize` event.
     *
     * @param InitializeEvent $event
     * @return void
     */
    public function onInitialize(InitializeEvent $event): void
    {
        $event->response = [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => new \stdClass(),
                'prompts' => new \stdClass(),
            ],
            'serverInfo' => $this->serverInfo,
            'instructions' => 'Use `tools/list` to discover available tools and `tools/call` to invoke them. '
                . 'Use `resources/list` to discover available resources and `resources/read` to read their contents. '
                . 'Use `prompts/list` to discover available prompts and `prompts/get` to retrieve prompt messages.',
        ];
        $event->setStatus(StatusEnum::OK, 'Initialized.');
    }
}
