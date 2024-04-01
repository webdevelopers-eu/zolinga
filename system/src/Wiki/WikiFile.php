<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;

use JsonSerializable;

/**
 * Represents a path in the WIKI
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-26
 *
 * @property-read string $path
 * @property-read string $priority
 * @property-read string $content
 * @property-read string $html
 * @property-read array<string, string> $meta
 * @property-read string $plainText
 */
class WikiFile implements JsonSerializable
{
    /**
     * Meta data of the file
     *
     * @var array<string, string>
     */
    private array $meta = [];
    private int $contentStart = 0;
    protected readonly string $moduleName;

    public function __construct(
        public readonly string $path
    ) {
        global $api;

        if (is_file($this->path) || str_starts_with($this->path, 'data:')) {
            $this->loadMeta();
        }
        $this->moduleName = $api->fs->getModuleNameByPath($this->path) ?: '';
    }

    // protected function getSourceFooter(string $mime): string
    // {
    //     if (!$this->moduleName) {
    //         return '';
    //     }
    //     return match ($mime) {
    //         'text/markdown' => "\n\n[Source: {$this->moduleName} module]",
    //         'text/html' => '<div class="module-source"><i>Source</i>: <span class="module">' . $this->moduleName . '</span></div>',
    //         default => "\n\n\[Source: module {$this->moduleName}]",
    //     };
    // }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'path':
                return $this->path;
            case 'priority':
                return floatval($this->meta['priority'] ?? 0.5);
            case 'content': // MD format
                $content = rtrim(file_get_contents($this->path, false, null, $this->contentStart) ?: '');
                if (!$content) {
                    $content = sprintf(
                        'Empty article. Add content to this file: %s',
                        parse_url($this->path, PHP_URL_HOST) . '/wiki' . parse_url($this->path, PHP_URL_PATH)
                    );
                }
                return $content; // problems with {{templates}} . $this->getSourceFooter('text/markdown');
            case 'html': // html format
                $parser = new MarkDownParser();
                return $parser->toHtml($this->content);
            case 'plainText':
                return html_entity_decode(strip_tags($this->html)); // . $this->getSourceFooter('text/plain');
            case 'meta':
                return $this->meta;
            default:
                return $this->meta[$name] ?? null;
        }
    }

    private function loadMeta(): void
    {
        // Read HTTP header style headers from the path $this->path
        $f = fopen($this->path, "r") or throw new \Exception("Cannot open path: $this->path");
        $name = false;
        while (($line = fgets($f)) !== false) {
            if (trim($line) === "") {
                $this->contentStart = ftell($f) ?: 0;
                break;
            }
            
            // Multiline header
            if ($name && preg_match('/^\s+/', $line)) {
                $this->amendMeta($name, ltrim($line));
                continue;
            }

            list($name, $value) = array_map('trim', [...explode(":", $line, 2), '', '']);
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $name) || !strlen($value)) {
                // Invalid header => there are no headers it is just a WIKI path without headers
                $this->meta = [];
                break;
            }
            $this->addMeta($name, $value);
        }
        fclose($f);
    }

    private function addMeta(string $name, string $value): void
    {
        // Convert $name into camel case like CSS javascript object element.styles does it
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        $name = lcfirst($name);
        $this->meta[$name] = $value;
    }

    private function amendMeta(string $name, string $value): void
    {
        if (isset($this->meta[$name])) {
            $this->meta[$name] .= "\n" . $value;
        } else {
            $this->addMeta($name, $value);
        }
    }

    public function jsonSerialize(): mixed
    {
        return $this->path;
    }
}
