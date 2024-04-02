<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\Ref;

use Zolinga\System\Wiki\WikiArticle;
use Zolinga\System\Wiki\WikiText;

class WikiServiceListArticle extends WikiArticle
{
    public readonly float $priority;

    /**
     * The map of services to classes
     *
     * @var array<string,string>
     */
    private array $map = [];

    public function __construct(string $uri)
    {
        $this->priority = 1;
        parent::__construct($uri, null);
        $this->title = "Services";

        $this->contentFiles[] = new WikiText($this->generateContent());
    }

    protected function initChildren(): void
    {
        // This causes a lot of memory usage and is not necessary to load ALL classes during searching...
        // So we don't append them to the tree as it would be fully searchable...

        $uris = array_map(fn ($class) => ':ref:class' . str_replace('\\', ':', $class), $this->map);
        foreach ($uris as $service => $uri) {
            $article = new WikiClassArticle($uri);
            $article->title = "Service \$api->{$service}";
            $this->addChild($article);
        }
    }

    private function generateContent(): string
    {
        global $api;

        /** @var array<string, array<string>> */
        $services = [];
        foreach ($api->manifest['listen'] as $listener) {
            if (preg_match('/^system:service:(?<service>.+)$/', $listener['event'], $m)) {
                $services[$m['service']] = [...($services[$m['service']] ?? []), $listener['class']];
            }
        }
        ksort($services);

        //Return only the first class in the array
        $this->map = array_map(fn ($classes) => $classes[0], $services);

        $servicesMd = implode("\n", array_map(function (array $classes, string $service) {
            $serviceClass = array_shift($classes);
            $uri = ":ref" . str_replace('\\', ':', $serviceClass);
            $ret = "- [\$api](:ref:Zolinga:System:Api)->[{$service}]({$uri}) : `{$serviceClass}`";

            if ($classes) {
                $ret .= " (alternative service candidates with lower priority: `" . implode('`, `', $classes) . "`)";
            }

            return $ret;
        }, $services, array_keys($services)));

        $wiki = <<< "WIKI"
        # Services
        The following services are available on the global `\$api` object. For more information read [Services](:Zolinga Core:Services) article.

        $servicesMd
        WIKI;

        return $wiki;
    }
}
