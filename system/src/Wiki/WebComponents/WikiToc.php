<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\WebComponents;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Events\ListenerInterface;

class WikiToc implements ListenerInterface
{
    public function onToc(RequestResponseEvent $event): void
    {
        global $api;
        $event->response['toc'] = $api->wiki;
        $event->setStatus($event::STATUS_OK, "WikiExplorer created");
    }
}