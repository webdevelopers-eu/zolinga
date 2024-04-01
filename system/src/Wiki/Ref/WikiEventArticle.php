<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\Ref;

use Zolinga\System\Wiki\{WikiArticle, WikiText, MarkDownParser};
use Zolinga\System\Events\Event;
use Zolinga\System\Config\Atom\ListenAtom;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class WikiEventArticle extends WikiGeneratedArticle
{
    public readonly float $priority;
    private readonly string $event;


    public function __construct(string $uri)
    {
        global $api;

        $this->priority = 0.6;
        parent::__construct($uri);

        // :ref:event:my-event
        if (!preg_match('/^:ref:event:(?<event>.+)$/', $uri, $matches)) {
            throw new \Exception("Invalid event URI: $uri");
        }

        $this->event = $matches['event'];
        $this->title = "" . $this->event;

        // If it is a service then add the class content
        if (preg_match('/^system:service:/', $this->event)) {
            $serviceInfo = $api->manifest->findByEvent(new Event($this->event, Event::ORIGIN_INTERNAL), 'listen', 1);
            if ($serviceInfo) {
                $classUri = ":ref:class" . str_replace('\\', ':', $serviceInfo['class']);
                $article = new WikiClassArticle($classUri);
                foreach($article->contentFiles as $file) {
                    $this->contentFiles[] = $file;
                }
            }
        }

        if (!$this->contentFiles) {
            $this->addContentFileTip();
        }

        $this->contentFiles[] = new WikiText($this->generateContentHtml(), WikiText::MIME_HTML);
        $this->sortContentFiles();
    }

    protected function initChildren(): void
    {
    }

    private function generateContentHtml(): string
    {
        global $api;

        $errors = '';
        $event = new Event($this->event, Event::ORIGIN_ANY);

        $listeners = $api->manifest->findByEvent($event, 'listen');
        $emits = $api->manifest->findByEvent($event, 'emit');

        if (!$listeners && !$emits) {
            return "<p>No listeners neither emitters found for event <code>{$this->event}</code></p>";
        }

        $eventClassListHtml = [];
        $originList = [];

        if ($emits) { // If emit is defined we prefere it over heuristic digging of parameters from listeners 
            foreach ($emits as $emitInfo) {
                $eventClassListHtml[] = WikiClassFile::typeToNameHtml(new ReflectionClass($emitInfo['class']));
                $originList = [...$originList, ...$emitInfo['origin']];
            }
        } else {
            foreach ($listeners as $k => $listener) {
                $originList = [...$originList, ...$listener['origin']];

                if (empty($listener['method'])) continue;
                try {
                    $refMethod = new ReflectionMethod($listener['class'], $listener['method']);
                    $firstParam = $refMethod->getParameters()[0] ?? null;
                    if ($firstParam) {
                        $type = $firstParam->getType();
                        $eventClassListHtml[] = WikiClassFile::typeToNameHtml($type);
                    }
                } catch (\Exception $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                    $errors .= '<p class="error">Error: ' . $e->getMessage() . ' Check your <code>zolinga.json</code> file.</p>';
                    unset($listeners[$k]);
                }
            }
        }
        if ($eventClassListHtml) {
            $eventType = '<span class="separ">:</span> ' . implode("|", array_unique($eventClassListHtml));
        } else {
            $eventType = '';
        }

        // Make it unique, in php8.2 array_unique does not work with Enumerations and __toString cannot be defined
        $originList = array_combine(array_map(fn ($i) => $i->value, $originList), $originList);
        $originListHtml = implode(" ", array_map(function ($origin) {
            return "<span title='Event origin \"$origin\"' class='origin $origin pill'>$origin</span>";
        }, array_keys($originList)));

        $chart = $this->getFlowChart($listeners);

        $descriptionList = [];
        foreach ($originList as $origin) {
            foreach ($api->manifest->findByEvent(new Event($this->event, $origin), 'emit') as $info) {
                $html = MarkDownParser::linkifyHtml($info['description'], shortClasses: true);
                $descriptionList[] = "<li><span class='origin pill {$origin->value}'>{$origin->value}</span> " . $html . "</li>";
            }
        }
        $description = implode("", $descriptionList);

        $wiki = <<< "WIKI"
        <main class="wiki-ref-event">
        <h1>Listeners for <code>{$this->event}</code></h1>
        <div class="event">
            <b>Event {$this->event}</b> {$eventType} {$originListHtml}
            <ul class="description">{$description}</ul>
        </div>
        $errors
        $chart
        </main>
        WIKI;

        return $wiki;
    }

    /**
     * Generate a flow chart of the event listeners
     *
     * @param array<ListenAtom> $listeners
     * @return string
     */
    private function getFlowChart(array $listeners): string
    {
        global $api;

        // [event] => wiki:article
        // [class] => \Zolinga\System\Wiki\WebComponents\WikiArticle
        // [method] => onArticle
        // [origin] => Array
        //     (
        //         [0] => remote
        //     )
        $ret = "<div class='wiki-ref-event-chart'>";
        foreach ($listeners as $listener) {
            $uri = ":ref" . str_replace("\\", ":", $listener['class']);

            $ret .= "<div class='wiki-event-connector'>";
            $ret .= "  <div class='above'>";
            foreach ($listener['origin'] as $origin) {
                $ret .= "<span title='Event origin' class='origin pill {$origin->value}'>{$origin->value}</span>";
            }
            $ret .= "  </div>";
            $ret .= "  <div class='bellow'>";
            $ret .= "    <div title='Listen priority' class='priority pill'>" . ($listener['priority'] ?? '0.5') . "</div>";
            $ret .= "  </div>";

            $ret .= "</div>";

            $ret .= "<div class='listener'>";

            $ref = new ReflectionClass($listener['class']);
            $path = $ref->getFileName();
            $zUri = $api->fs->toZolingaUri($path);
            $module = parse_url($zUri, PHP_URL_HOST);
            $ret .= "<a href=':ref:module:{$module}' title='{$zUri}' class='module'>{$module}</a>";

            $ret .= $this->mkClassLink($listener['class'], "entrypoint", $listener['method'] ?: null);

            if ($this->event !== $listener['event']) { // may be matched by '*' patterns
                $ret .= "<div class='event'>Listens for <a href=\":ref:event:{$listener['event']}\">{$listener['event']}</a> event</div>";
            }

            $ret .= "<div class='description'>" . MarkDownParser::linkifyHtml($listener['description'] ?: '', shortClasses: true) . "</div>";
            if ($listener['right']) {
                $ret .= "<div class='right'><a href=':ref:class:Zolinga:System:Events:AuthorizeEvent'>Authorization</a> required: <code>{$listener['right']}</code></div>";
            }
            $ret .= "</div>";
        }
        $ret .= "</div>";

        return $ret;
    }

    private function mkClassLink(string $className, string $cssClasses = "", ?string $method = null): string
    {
        global $api;

        $uri = ':ref' . str_replace("\\", ":", $className);
        $classNameShort = basename(str_replace("\\", "/", $className));

        $ret  = "<span class='class-link {$cssClasses}'>";

        $ret .= "<a title='Class {$className}' href='{$uri}''>{$classNameShort}</a>";
        if ($method) {
            $ret .= "<span class='separ'>::</span>";
            $ret .= "<a title='Method {$className}::{$method}' href='{$uri}:{$method}''>{$method}</a>";
            $ret .= "<span class='brackets'>(</span><span class='params'>";


            $ref = new ReflectionMethod($className, $method);
            foreach ($ref->getParameters() as $param) {
                $ret .= WikiClassFile::typeToNameHtml($param->getType());
            }

            $ret .= "</span><span class='brackets'>)</span>";
        }

        $ret .= "</span>";

        return $ret;
    }
}
