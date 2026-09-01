<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Events\Mcp\Prompts\GetEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles MCP `prompts/get` requests for the `mcp-system` URI scheme.
 *
 * Parses the `mcp-system:<module>:<basename>` name, resolves the
 * corresponding `.meta.json` prompt definition, resolves any `content.uri`
 * file references, applies `{{arg}}` substitution, and returns the
 * `{ description?, messages: [...] }` response.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpPromptsGetHandler extends AbstractMcpActionHandler
{
    protected const SUBDIR = 'prompts';
    protected const REQUEST_KEY = 'name';

    /**
     * Load and serve a prompt definition.
     *
     * @param AbstractActionEvent $event
     * @param string $module
     * @param string $basename
     * @param string $requestName The original name from the request.
     * @return void
     */
    protected function doAction(AbstractActionEvent $event, string $module, string $basename, string $requestName): ?array
    {
        assert($event instanceof GetEvent);
        global $api;

        $meta = parent::doAction($event, $module, $basename, $requestName);
        if ($meta === null) {
            return null;
        }

        if (!isset($meta['messages']) || !is_array($meta['messages'])) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' is missing field 'messages' or field is not an array");
            $event->setStatus(StatusEnum::ERROR, "Prompt definition missing 'messages' field: $requestName");
            return null;
        }

        $args = $event->request['arguments'] ?? [];
        if (!is_array($args)) {
            $args = [];
        }
        $error = $this->validateArguments($meta['arguments'] ?? [], $args);
        if ($error !== null) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' has invalid arguments: $error");
            $event->setStatus(StatusEnum::BAD_REQUEST, $error);
            return null;
        }

        $messages = $this->resolveMessages($meta['messages'], $args, $module);
        if ($messages === null) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' failed to resolve prompt messages");
            $event->setStatus(StatusEnum::ERROR, 'Failed to resolve prompt messages: ' . $requestName);
            return null;
        }

        $event->response = ['messages' => $messages];
        if (isset($meta['description']) && is_string($meta['description'])) {
            $event->response['description'] = $meta['description'];
        }

        $event->setStatus(StatusEnum::OK, 'OK');
        return null;
    }

    /**
     * Validate that all required arguments are present.
     *
     * @param array<int, array<string, mixed>> $definitions Argument definitions from .meta.json.
     * @param array<string, mixed> $provided Arguments provided by the client.
     * @return string|null Error message, or null if valid.
     */
    private function validateArguments(array $definitions, array $provided): ?string
    {
        foreach ($definitions as $def) {
            if (!is_array($def)) {
                continue;
            }
            $argName = $def['name'] ?? '';
            $required = $def['required'] ?? false;
            if ($required && !array_key_exists($argName, $provided)) {
                return "Missing required argument: $argName";
            }
        }
        return null;
    }

    /**
     * Resolve messages: read file references and apply placeholder substitution.
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $args
     * @param string $module The module name for path containment checks.
     * @return array<int, array<string, mixed>>|null Resolved messages, or null on error.
     */
    private function resolveMessages(array $messages, array $args, string $module): ?array
    {
        global $api;

        $moduleRoot = realpath($api->fs->toPath("module://$module/"));
        $resolved = [];

        foreach ($messages as $msg) {
            if (!is_array($msg) || !isset($msg['content']) || !is_array($msg['content'])) {
                $api->log->error('system:mcp', "MCP prompt message is invalid or missing 'content' field: " . json_encode($msg));
                continue;
            }

            $content = $msg['content'];

            // Resolve content.uri file reference to content.text.
            if (isset($content['uri']) && !isset($content['text'])) {
                $uri = $content['uri']; // real path to the file
                $contentPath = is_string($uri) ? $api->fs->toPath($uri) : null;
                if (!$contentPath || !file_exists($contentPath)) {
                    $api->log->error('system:mcp', "Prompt content.uri file not found: $uri");
                    continue;
                }

                $content['text'] = (string) file_get_contents($contentPath);
            }

            // Always strip internal uri field — it must never leak to the client.
            unset($content['uri']);

            // Apply {{arg}} substitution to text content.
            if (isset($content['text']) && is_string($content['text'])) {
                $content['text'] = $this->replacePlaceholders($content['text'], $args);
            }

            $msg['content'] = $content;

            // Check role is valid (user, system, assistant, or function).
            $role = $msg['role'] ?? '';
            if (!in_array($role, ['user', 'system', 'assistant', 'function'], true)) {
                $api->log->error('system:mcp', "Prompt message has invalid 'role' field: " . json_encode($msg) . ". Resetting to role 'user'.");
                $msg['role'] = 'user';
            }

            $resolved[] = $msg;
        }

        return $resolved;
    }

    /**
     * Substitute `{{argName}}` placeholders with argument values.
     *
     * Loops up to 16 times to handle nested substitutions (where an argument
     * value itself contains `{{...}}` placeholders). After the loop, any
     * remaining `{{...}}` placeholders are replaced with empty string.
     *
     * @param string $text The text with placeholders.
     * @param array<string, mixed> $args The arguments to substitute.
     * @return string The substituted text.
     */
    private function replacePlaceholders(string $text, array $args): string
    {
        for ($i = 0; $i < 16; $i++) {
            foreach ($args as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $text = str_replace('{{' . $key . '}}', (string) $value, $text);
                }
            }
            // Stop early if no placeholders remain.
            if (!preg_match('/\{\{[^}]+\}\}/', $text)) {
                break;
            }
        }
        // Replace any remaining placeholders with empty string.
        return preg_replace('/\{\{[^}]+\}\}/', '', $text) ?? $text;
    }
}
