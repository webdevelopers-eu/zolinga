<?php

declare(strict_types=1);


namespace Zolinga\System\Wiki\Ref;

use Zolinga\System\Wiki\WikiArticle;
use Zolinga\System\Wiki\WikiText;
use Zolinga\System\Config\Atom\{AtomInterface, ListenAtom, EmitAtom};
use Zolinga\System\Wiki\MarkDownParser;

class WikiEventListArticle extends WikiArticle
{
    public readonly float $priority;

    public function __construct(string $uri)
    {
        $this->priority = 1;
        parent::__construct($uri, null);
        $this->title = "Events";

        $this->contentFiles[] = new WikiText($this->generateContent());
    }

    protected function initChildren(): void
    {
        global $api;

        // There can be hundreds of events...
        $events = array_unique(array_map(
            fn (ListenAtom|EmitAtom $i) => $i['event'],
            [...$api->manifest['listen'], ...$api->manifest['emit']]
        ));

        sort($events);

        $this->addChild(...array_map(function ($event) {
            return new WikiEventArticle(":ref:event:$event");
        }, $events));
    }

    private function generateContent(): string
    {
        $eventList = $this->getEventListWiki();

        $wiki = <<< "WIKI"
        # Events
        The following events are available in the system. For more information read 
        [Events and Listeners](:Zolinga Core:Events and Listeners) article.

        $eventList
        WIKI;

        return $wiki;
    }

    private function getEventListWiki(): string
    {
        global $api;

        $events = [];
        foreach (["emits" => $api->manifest['emit'], "listen" => $api->manifest['listen']] as $source => $list) {
            foreach ($list as $info) {
                foreach ($info['origin'] as $origin) {
                    if (!isset($events[$origin->value][$info['event']])) {
                        $events[$origin->value][$info['event']] = ["name" => "", "descriptions" => []];
                    }
                    $events[$origin->value][$info['event']]["name"] = "[{$info['event']}](:ref:event:{$info['event']})";
                    if (!empty($info['description'])) {
                        $description = "";
                        if ($source == 'listen') {
                            $description .= $info['class'] . ': ';
                        }
                        $description .= $info['description'];
                        $events[$origin->value][$info['event']]["descriptions"][] = MarkDownParser::linkifyMarkdown($description, shortClasses: true);
                    }
                }
            }
        }
        ksort($events);

        $text = "";
        foreach ($events as $origin => $event) {
            $text .= "\n## #$origin\n";
            foreach ($event as $eventInfo) {
                $text .= "- {$eventInfo['name']}\n";
                if (!empty($eventInfo['descriptions'])) {
                    $text .= "  - " . implode("\n  - ", array_unique($eventInfo['descriptions'])) . "\n";
                }
            }
            $text .= "\n";
        }

        return $text;
    }
}
