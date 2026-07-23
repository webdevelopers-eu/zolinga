<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
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
class McpPromptsGetHandler implements ListenerInterface
{
    /**
     * Handle the `prompts/get:mcp-system` event.
     *
     * @param GetEvent $event The prompts/get event.
     * @return void
     */
    public function onGet(GetEvent $event): void
    {
        $name = $event->request['name'] ?? null;
        if (!is_string($name) || $name === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "name" parameter.');
            return;
        }

        $parts = $this->parseName($name);
        if ($parts === null) {
            // Invalid mcp-system:<module>:<basename> name format or directory traversal attempt.
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Invalid prompt name: ' . $name);
            return;
        }

        $this->getPrompt($event, $parts['module'], $parts['basename'], $name);
    }

    /**
     * Parse a `mcp-system:<module>:<basename>` name into its components.
     *
     * @param string $name
     * @return array{module: string, basename: string}|null
     */
    private function parseName(string $name): ?array
    {
        $parts = explode(':', $name, 3);
        if (count($parts) < 3 || $parts[0] !== 'mcp-system') {
            return null;
        }

        $module = basename($parts[1]);
        $basename = basename($parts[2]);

        // Directory traversal protection.
        if ($module !== $parts[1] || $basename !== $parts[2]) {
            return null;
        }

        return ['module' => $module, 'basename' => $basename];
    }

    /**
     * Load and serve a prompt definition.
     *
     * @param GetEvent $event
     * @param string $module
     * @param string $basename
     * @param string $requestName The original name from the request.
     * @return void
     */
    private function getPrompt(GetEvent $event, string $module, string $basename, string $requestName): void
    {
        global $api;

        // Module existence check.
        if (!in_array($module, $api->manifest->moduleNames, true)) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' for unknown module: $module");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Prompt not found: ' . $requestName);
            return;
        }

        $zPath = "module://$module/mcp/prompts/$basename.meta.json";
        $realPath = $api->fs->toPath($zPath);
        if (!$realPath || !is_file($realPath)) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' for missing prompt file: $zPath");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Prompt not found: ' . $requestName);
            return;
        }

        $meta = json_decode((string) file_get_contents($realPath), true);
        if (!is_array($meta)) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' ($zPath) has invalid JSON in file: $zPath");
            $event->setStatus(StatusEnum::ERROR, 'Prompt definition is invalid: ' . $requestName);
            return;
        }

        // Validate messages field.
        if (!isset($meta['messages']) || !is_array($meta['messages'])) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' ($zPath) is missing field 'messages' or field is not an array in file: $zPath");
            $event->setStatus(StatusEnum::ERROR, "Prompt definition missing 'messages' field: $requestName");
            return;
        }

        // Validate required arguments.
        $args = $event->request['arguments'] ?? [];
        if (!is_array($args)) {
            $args = [];
        }
        $error = $this->validateArguments($meta['arguments'] ?? [], $args);
        if ($error !== null) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' ($zPath) has invalid arguments: $error");
            $event->setStatus(StatusEnum::BAD_REQUEST, $error);
            return;
        }

        // Resolve messages: file references + placeholder substitution.
        $messages = $this->resolveMessages($meta['messages'], $args, $module, $zPath);
        if ($messages === null) {
            $api->log->error('system:mcp', "MCP prompt request '$requestName' ($zPath) failed to resolve prompt messages");
            $event->setStatus(StatusEnum::ERROR, 'Failed to resolve prompt messages: ' . $requestName);
            return;
        }

        // Build response.
        $event->response = ['messages' => $messages];
        if (isset($meta['description']) && is_string($meta['description'])) {
            $event->response['description'] = $meta['description'];
        }

        $event->setStatus(StatusEnum::OK, 'OK');
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
     * @param string $zPath The zPath of the .meta.json file (for logging).
     * @return array<int, array<string, mixed>>|null Resolved messages, or null on error.
     */
    private function resolveMessages(array $messages, array $args, string $module, string $zPath): ?array
    {
        global $api;

        $moduleRoot = realpath($api->fs->toPath("module://$module/"));
        $resolved = [];

        foreach ($messages as $msg) {
            if (!is_array($msg) || !isset($msg['content']) || !is_array($msg['content'])) {
                $api->log->error('system:mcp', "MCP prompt message in file '$zPath' is invalid or missing 'content' field: " . json_encode($msg));
                continue;
            }

            $content = $msg['content'];

            // Resolve content.uri file reference to content.text.
            if (isset($content['uri']) && !isset($content['text'])) {
                $uri = $content['uri']; // real path to the file
                $contentPath = is_string($uri) ? $api->fs->toPath($uri) : null;
                if (!$contentPath || !file_exists($contentPath)) {
                    $api->log->error('system:mcp', "Prompt $zPath content.uri file not found: $uri");
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
                $api->log->error('system:mcp', "Prompt $zPath message has invalid 'role' field: " . json_encode($msg) . ". Resetting to role 'user'.");
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
