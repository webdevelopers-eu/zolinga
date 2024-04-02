<?php
declare(strict_types=1);


namespace Zolinga\System\Wiki\Ref;
use Zolinga\System\Wiki\WikiArticle;
use Zolinga\System\Wiki\WikiText;
use const Zolinga\System\ROOT_DIR;

class WikiWebComponentListArticle extends WikiArticle
{
    public readonly float $priority;

    public function __construct(string $uri)
    {
        $this->priority = 1;
        parent::__construct($uri, null);
        $this->title = "Web Components";

        $this->contentFiles[] = new WikiText($this->generateContent());

    }

    protected function initChildren(): void
    {
        global $api;

        $components = array_unique(array_map(fn ($wc)=> $wc['tag'], $api->manifest['webComponents']));
        sort($components);
        $this->addChild(...array_map(fn ($tag) =>new WikiWebComponentArticle(":ref:wc:$tag"), $components));
    }

    private function generateContent():string {
        $wcList = $this->getWcListMd();

        $wiki = <<< "WIKI"
        # Web Components
        Following web components are available in the system. For more information read [Web Components](:Zolinga Core:Web Components) article.

        $wcList
        WIKI;

        return $wiki;
    }

    private function getWcListMd():string {
        global $api;

        $this->initChildren();

        $wcList = [];
        $components = $api->manifest['webComponents'];
        foreach($components as $wc) {
            $article = current(array_filter($this->children, fn ($child) => $child->uri === ":ref:wc:{$wc['tag']}"));
            $mdFile = $article ? $article->contentFiles[0] : null;

            $js = basename($wc['module']);
            $zolingaUri = $api->fs->toZolingaUri(ROOT_DIR . '/public' . $wc['module']);
            $zModuleName = parse_url($zolingaUri, PHP_URL_HOST);
            $wcList[$wc['tag']] = "- [<{$wc['tag']}>](:ref:wc:{$wc['tag']}) is handled by [$zModuleName](:ref:module:$zModuleName)'s [{$js}]({$wc['module']})";
            if (isset($mdFile?->meta['description'])) {
                $wcList[$wc['tag']] .= "\n> {$mdFile->meta['description']}";
            }
        }
        return implode("\n", $wcList);
    }
}
