<?php
declare(strict_types=1);

namespace Zolinga\System\Wiki;


class WikiNoFile extends WikiFile
{
    public function __get(string $name): mixed
    {
        $title = 'Empty Article';
        $module = parse_url($this->path, PHP_URL_HOST);
        $path = parse_url($this->path, PHP_URL_PATH);

        $msg = sprintf(
            'Create a file %s in any module to add content. E.g %s', 
            "wiki/$path", 
            "modules/$module/wiki/$path"
        );

        switch ($name) {
            case 'content':
                return $msg;
            case 'html':
                return "<h1>".htmlspecialchars($title)."</h1><p><i class='wiki-empty-content'>".htmlspecialchars($msg)."</i></p>";
            default:
                return parent::__get($name);
        }
    }

}