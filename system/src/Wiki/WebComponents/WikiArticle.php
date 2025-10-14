<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\WebComponents;

use Zolinga\System\Events\{WikiRefIntegrationEvent, Event, WebEvent, ListenerInterface};
use Zolinga\System\Wiki\WikiNoFile;
use Zolinga\System\Wiki\Ref\{WikiAutoloadArticle, WikiWebComponentListArticle, WikiWebComponentArticle, WikiRefArticle, WikiClassArticle, WikiEventListArticle, WikiEventArticle, WikiServiceListArticle};
use Zolinga\System\Wiki\WikiArticle as WikiFileArticle;
use Exception;

class WikiArticle implements ListenerInterface
{
    public function onArticle(WebEvent $event): void
    {
        global $api;

        $article = $this->getArticle($event->request['uri'] ?: ':ref');

        $event->response['uri'] = $article->uri;
        $event->response['title'] = $article->title;
        $event->response['files'] = [];

        foreach ($article->contentFiles ?: [new WikiNoFile($article->baseFile . '.md')] as $file) {
            $event->response['files'][] = [
                // 'path' => $file->path, - can be long data uri
                'html' => $file->html,
                'meta' => $file->meta,
            ];
        }

        $event->setStatus($event::STATUS_OK, "WikiExplorer created");
    }

    private function getArticle(string $uri): WikiFileArticle
    {
        global $api;

        list($rootLevel, $topLevel, $mainLevel, $paramLevel) = explode(':', $uri . ':::');

        // Detect if URI is generated content URI starting with :ref
        // short circuit so we don't need to build a whole tree $api->wiki
        if ($topLevel === 'ref') {
            switch ($mainLevel) {
                case 'service': // Find service class
                    if ($paramLevel === '') {
                        $article = new WikiServiceListArticle($uri);
                    } else {
                        $article = $this->getServiceArticle($paramLevel);
                    }
                    break;
                case 'event':
                    if ($paramLevel === '') {
                        $article = new WikiEventListArticle($uri);
                    } else {
                        $article = new WikiEventArticle($uri);
                    }
                    break;
                case 'wc':
                    if ($paramLevel === '') {
                        $article = new WikiWebComponentListArticle($uri);
                    } else {
                        $article = new WikiWebComponentArticle($uri);
                    }
                    break;

                case 'module':
                case 'config':
                case '': // only :ref
                    $article = new WikiRefArticle($uri);
                    break;
                case 'class':
                    if ($paramLevel === '') {
                        $article = new WikiAutoloadArticle($uri);
                    } else {
                        $article = new WikiClassArticle($uri);
                    }
                    break;
                default:
                    // If it starts with upper case then it is a class :ref:ClassName
                    if (ctype_upper($mainLevel[0])) {
                        $article = new WikiClassArticle($uri);
                    } else {
                        $article = $this->getIntegredArticle($uri);
                    }
            }
        } else {
            $article = $api->wiki->get($uri);
        }

        if (!$article) {
            throw new Exception("Article $uri not found");
        }

        return $article;
    }

    private function getIntegredArticle(string $uri): WikiFileArticle
    {
        global $api;

        $event = new WikiRefIntegrationEvent("system:wiki:ref:discovery", WikiRefIntegrationEvent::ORIGIN_INTERNAL);
        $api->dispatchEvent($event);

        foreach ($event->articles as $article) {
            if ($article->uri === $uri) {
                return $article;
            }
        }

        throw new Exception("Article $uri not found");
    }

    private function getServiceArticle(string $service): WikiClassArticle
    {
        global $api;

        $event = new Event("system:service:$service", Event::ORIGIN_INTERNAL);
        $subscription = $api->manifest->findByEvent($event, 'listen', 1);
        if (!$subscription) {
            throw new Exception("Service '$service' (a listener for $event) not found.");
        }

        $uri = ':ref' . str_replace('\\', ':', $subscription['class']);
        return new WikiClassArticle($uri);
    }
}
