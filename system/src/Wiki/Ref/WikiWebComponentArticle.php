<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\Ref;

use ReflectionClass;
use Zolinga\System\Wiki\{WikiArticle, WikiText, WikiFile};
use Zolinga\System\Config\Atom\WebComponentAtom;
use const Zolinga\System\ROOT_DIR;
use ReflectionMethod;

class WikiWebComponentArticle extends WikiArticle
{
    public readonly float $priority;

    public function __construct(string $uri)
    {
        global $api;

        $this->priority = 0.6;
        parent::__construct($uri, null);

        // :ref:wc:my-wc
        if (!preg_match('/^:ref:wc:(?<wc>.+)$/', $uri, $matches)) {
            throw new \Exception("Invalid web component URI: $uri");
        }

        list($wc) = array_values(array_filter($api->manifest['webComponents'], fn (WebComponentAtom $wc) => $wc['tag'] === $matches['wc']));
        $this->title = "<" . $wc['tag'] . ">";

        $jsFile = ROOT_DIR . '/public' . $wc['module'];
        $mdFile = preg_replace('/\.[a-z0-9]+$/', '.md', $jsFile);
        if (!file_exists($jsFile)) {
            throw new \Exception("Web component <{$wc['tag']}>'s handling ECMAScript module not found: $jsFile");
        }

        if (file_exists($mdFile)) {
            $this->contentFiles[] = new WikiFile($mdFile);
        } else {
            $mdBasenameHtml = htmlspecialchars(basename($mdFile));
            $jsModuleHtml = htmlspecialchars($wc['module']);
            $jsBasenameHtml = htmlspecialchars(basename($wc['module']));
            $zUri = $api->fs->toZolingaUri($mdFile);
            $zModuleName = parse_url($zUri, PHP_URL_HOST);
            $this->contentFiles[] = new WikiText(
                "<h1>Missing &lt;{$wc['tag']}&gt; Documentation</h1>" .
                    "<p>To add a content to this page create a file named <code>{$mdBasenameHtml}</code> in the same directory " .
                    "within the <code>{$zModuleName}</code> module as your ECMAScript module file " .
                    "<a href='{$jsModuleHtml}'>{$jsBasenameHtml}</a>.</p>",
                WikiText::MIME_HTML
            );
        }
    }

    protected function initChildren(): void
    {
    }
}
