<?php

declare(strict_types=1);

namespace Zolinga\System\Cms;

use Zolinga\System\Events\{ListenerInterface,ContentEvent};

class Page implements ListenerInterface
{
    public function onContent(ContentEvent $event): void
    {
        global $api;

        // Page was already served by other CMS
        if ($event->status != ContentEvent::STATUS_UNDETERMINED) {
            return;
        }
        $html = file_get_contents('module://system/data/welcome.html') ?: '<h1>Welcome to Zolinga CMS</h1>';

        // Include a link if the wiki path is still /wiki - there is nothing to hide here.
        $vars = [
            "display" => "hidden"
        ];
        if (($api->config['wiki']['urlPrefix'] ?? false) === '/wiki') {
            $vars['display'] = 'initial';
        }
        $html = str_replace(array_map(fn ($k) => '{{'.$k.'}}', array_keys($vars)), array_values($vars), $html);

        $event->setContentHTML($html);
        $event->setStatus(ContentEvent::STATUS_OK, 'Page served by Zolinga CMS');
    }
}
