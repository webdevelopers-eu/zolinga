<?php

declare(strict_types=1);

namespace Zolinga\System\Cms;

use Zolinga\System\Events\{CliRequestResponseEvent, ListenerInterface};

/**
 * CLI listener for processing HTML/XML content through the CMS pipeline.
 *
 * Mimics the web request flow from public/index.php by creating a ContentEvent
 * and dispatching it, then outputting the resulting HTML.
 *
 * Usage:
 *
 *   bin/zolinga process:content --input=page.html --url=/test/page
 *   cat page.html | bin/zolinga process:content --url=/test/page
 *   bin/zolinga process:content --input=page.html --output=result.html
 */
class ProcessContentCli implements ListenerInterface
{
    /**
     * Handle the process:content CLI event.
     *
     * Parses input HTML/XML and runs it through the CMS content parser
     * to expand custom elements (e.g. <markdown-to-html>, <html-to-markdown>).
     *
     * @param CliRequestResponseEvent $event
     * @return void
     */
    public function onProcessContent(CliRequestResponseEvent $event): void
    {
        global $api;

        $inputFile = $event->request['input'] ?? null;
        $outputFile = $event->request['output'] ?? null;
        $url = $event->request['url'] ?? null;

        // Read input
        $html = $this->readInput($inputFile);
        if ($html === null) {
            $event->setStatus($event::STATUS_BAD_REQUEST, 'No input provided. Use --input=<file> or pipe via stdin.');
            return;
        }

        // Determine the URL path
        $path = $this->resolvePath($url);

        // Set up $_SERVER variables to mimic a web request
        $this->setupServerGlobals($path, $url);

        // Parse input HTML and run through CMS content parser
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->substituteEntities = false;
        $doc->strictErrorChecking = false;
        $doc->recover = true;
        $doc->formatOutput = false;
        $doc->resolveExternals = false;
        $doc->validateOnParse = false;
        $doc->xmlStandalone = true;
        @$doc->loadHTML('<!DOCTYPE html>' . PHP_EOL . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);

        if ($doc->documentElement) {
            $api->cmsParser->parse($doc->documentElement);
        }

        $result = $doc->saveHTML() ?: '';

        // Write or print result
        if ($outputFile) {
            file_put_contents($outputFile, $result) !== false
                ? $event->setStatus($event::STATUS_OK, "Output written to $outputFile")
                : $event->setStatus($event::STATUS_ERROR, "Failed to write to $outputFile");
        } else {
            echo $result;
            $event->setStatus($event::STATUS_OK, 'Content processed and printed to stdout.');
        }

        $event->response['path'] = $path;
    }

    /**
     * Read input from file or stdin.
     *
     * @param string|null $inputFile
     * @return string|null
     */
    private function readInput(?string $inputFile): ?string
    {
        $stdinIndicators = ['-', 'php://stdin', 'stdin', '/dev/stdin', 'php://input'];

        if ($inputFile && !in_array($inputFile, $stdinIndicators, true)) {
            if (!file_exists($inputFile) || !is_readable($inputFile)) {
                throw new \Exception("Input file not found or not readable: $inputFile");
            }
            $content = file_get_contents($inputFile);
            return $content !== false ? $content : null;
        }

        // Read from stdin if available
        $stdin = fopen('php://stdin', 'r');
        if (!$stdin) {
            return null;
        }

        $content = stream_get_contents($stdin);
        fclose($stdin);

        return $content !== false && strlen($content) > 0 ? $content : null;
    }

    /**
     * Resolve the URL path from --url or baseURL config.
     *
     * @param string|null $url
     * @return string
     */
    private function resolvePath(?string $url): string
    {
        global $api;

        if ($url) {
            return parse_url($url, PHP_URL_PATH) ?: '/';
        }

        $baseURL = $api->config['baseURL'] ?? $api->config['baseUrl'] ?? 'http://localhost/';
        return parse_url($baseURL, PHP_URL_PATH) ?: '/';
    }

    /**
     * Set up $_SERVER globals to mimic a web request.
     *
     * @param string $path
     * @param string|null $url
     * @return void
     */
    private function setupServerGlobals(string $path, ?string $url): void
    {
        global $api;

        $baseURL = $api->config['baseURL'] ?? 'http://localhost/';
        $parsed = parse_url($url ?: $baseURL);

        $_SERVER['REQUEST_URI'] = $path . ($parsed['query'] ?? '' ? '?' . $parsed['query'] : '');
        $_SERVER['PATH_INFO'] = $path;
        $_SERVER['HTTP_HOST'] = $parsed['host'] ?? 'localhost';
        $_SERVER['SERVER_NAME'] = $parsed['host'] ?? 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTPS'] = ($parsed['scheme'] ?? 'http') === 'https' ? 'on' : 'off';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }
}
