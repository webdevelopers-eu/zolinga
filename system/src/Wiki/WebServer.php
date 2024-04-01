<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;

use Zolinga\System\Events\{ListenerInterface, ContentEvent};

/**
 * Serves the main wiki page.
 * 
 * The URL prefix for the wiki is specified in a configuration file's wiki.urlPrefix property.
 * The password for the wiki is specified in a configuration file's wiki.password property.
 * 
 * If the wiki.urlPrefix property is not defined or is false or the wiki.password is not defined
 * then the wiki is disabled.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-10
 */
class WebServer implements ListenerInterface
{
    /**
     * If prefix is not defined or is false then the wiki is not enabled.
     *
     * @var string|false
     */
    private readonly string|false $prefix;

    public function __construct()
    {
        global $api;

        if (empty($api->config['wiki']['urlPrefix']) || empty($api->config['wiki']['password'])) {
            $this->prefix = false;
        } else {
            $this->prefix = rtrim($api->config['wiki']['urlPrefix'], '/') ?: false;
        }
    }

    public function onContent(ContentEvent $event): void
    {
        global $api;

        if (!$this->prefix || $event->status !== ContentEvent::STATUS_UNDETERMINED) {
            return;
        }

        $wikiAuth = new WikiAuth();
        if (!$wikiAuth->isEnabled()) {
            return;
        }

        // Is it WIKI page?
        if (!preg_match('@^'.preg_quote($this->prefix.'', '@').'(/|$)@', $event->path)) {
            return;
        }

        $content = file_get_contents(__DIR__ . '/../../data/wiki.html') or throw new \RuntimeException('Failed to load wiki page');
        $event->setContentHTML($content);

        $base = $event->content->getElementsByTagName('base')->item(0);
        if ($base instanceof \DOMElement) {
            $urlBase = str_replace('${urlPrefix}', $this->prefix, $base->getAttribute('href'));
            $base->setAttribute('href', $urlBase);
        }
        
        $event->setStatus(ContentEvent::STATUS_OK, 'Wiki page loaded');
    }
}
