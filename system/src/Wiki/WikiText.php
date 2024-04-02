<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;

use Zolinga\System\Types\WikiMimeEnum;

/**
 * Represents a in-memory text file in the WIKI
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-26
 *
 * @property-read string $path
 * @property-read string $priority
 * @property-read string $content
 */
class WikiText extends WikiFile
{
    final const MIME_HTML = WikiMimeEnum::HTML;
    final const MIME_MARKDOWN = WikiMimeEnum::MARKDOWN;

    public function __construct(
        public readonly string $text,
        public readonly WikiMimeEnum $mime = self::MIME_MARKDOWN
    ) {
        $dataURI = 'data:text/plain;base64,' . base64_encode($this->text);
        parent::__construct($dataURI);
    }

    public function __get(string $name): mixed
    {
        if ($name == 'html' && $this->mime === self::MIME_HTML) {
            return $this->content;
        }
        return parent::__get($name);
    }
}
