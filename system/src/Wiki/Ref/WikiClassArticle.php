<?php
declare(strict_types=1);


namespace Zolinga\System\Wiki\Ref;

class WikiClassArticle extends WikiGeneratedArticle
{
    private readonly string $className;

    public function __construct(string $uri)
    {
        $uriClass = preg_replace('@^:ref:(class:)?@', '', $uri);
        $this->className = '\\' . implode('\\', explode(':', $uriClass));
        $uri = ":ref:class" . str_replace('\\', ':', $this->className);

        parent::__construct($uri);
        $this->title = "Class {$this->className}";
        $this->addContentFile($this->className);
        // $this->addContentFilesMatching($uri); - parent does that
        if (count($this->contentFiles) == 1) {
            $this->addContentFileTip();
        }
    }

    protected function initChildren(): void
    {
    }

    public function addContentFile(string $className): WikiClassFile 
    {
        $file = new WikiClassFile($className);
        $this->contentFiles[] = $file;     
        return $file;
    }
}
