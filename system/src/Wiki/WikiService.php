<?php
declare(strict_types=1);

namespace Zolinga\System\Wiki;

use JsonSerializable;
use Zolinga\System\Events\{RequestResponseEvent, AuthorizeEvent};
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\ServiceInterface;
use Zolinga\System\Wiki\Ref\WikiRefArticle;
use const Zolinga\System\ROOT_DIR;

/**
 * The WIKI service and also the WikiArticle tree root.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-23
 */
class WikiService extends WikiArticle implements ServiceInterface, JsonSerializable, ListenerInterface
{
    public function __construct()
    {
        $_SESSION['systemWiki'] = $_SESSION['systemWiki'] ?? [];

        parent::__construct(":");
        $this->contentFiles[] = new WikiFile(ROOT_DIR.'/README.md');
    }

    protected function initChildren(): void
    {
        parent::initChildren();
        $this->addChild(new WikiRefArticle(':ref'));
    }

    public function jsonSerialize(): mixed
    {
        return parent::jsonSerialize();
    }

}