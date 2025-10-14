<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\WebComponents;
use Zolinga\System\Events\WebEvent;
use Zolinga\System\Events\ListenerInterface;

class WikiSearch implements ListenerInterface
{
    public function onSearch(WebEvent $event):void
    {
        global $api;

        $search = $event->request['search'];
        $event->response['results'] = [];

        if (!$search) {
            $event->setStatus($event::STATUS_BAD_REQUEST, "Missing search parameter");
            return;
        }
        
        $keywords = preg_split('/\s+/', trim($search)) ?: [];
        
        $event->response['results'] = $api->wiki->search($keywords);
        $event->setStatus(
            $event->response['results'] ? $event::STATUS_OK : $event::STATUS_NOT_FOUND, 
            sprintf("Found %s results.", count($event->response['results']))
        );
    }
}