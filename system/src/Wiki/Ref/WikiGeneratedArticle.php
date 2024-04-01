<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\Ref;

use Zolinga\System\Wiki\{WikiArticle, WikiFile, WikiText};
use const Zolinga\System\ROOT_DIR;

/**
 * Class WikiArticle
 *
 * This class represents a wiki article that finds all .md files in all modules for the given URI and adds them to the contentFiles array.
 * 
 * E.g. 
 * 
 *  $article = new WikiGeneratedArticle('ref:class:My:Super:Class');
 * 
 * This will load all {module}/wiki/ref/class/My/Super/Class.md files to the contentFiles array.  
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-03-15
 */
class WikiGeneratedArticle extends WikiArticle
{

    public function __construct(string $uri)
    {
        parent::__construct($uri);
        // Find all .md fiels for the class
        $this->addContentFilesMatching($uri);
    }

    /**
     * Find all .md files for the WIKI URI and add them to the contentFiles array.
     * 
     * Example:
     * 
     *   $this->addContentFilesMatching('ref:class:My:Super:Class');
     * 
     * This will add all {module}/wiki/ref/class/My/Super/Class.md files to the contentFiles array.
     *
     * @param string $uri
     * @return void
     */
    protected function addContentFilesMatching(string $uri): void
    {
        global $api;

        // $searched = [];
        $found = false;
        $path = "wiki" . str_replace(':', '/', $uri) . "." . self::FILE_EXTENSION;
        foreach ($api->manifest->modulePaths as $modulePath) {
            $file = ROOT_DIR . $modulePath . "/" . $path;
            if (file_exists($file)) {
                $found = true;
                $this->contentFiles[] = new WikiFile($file);
            }
            // $searched[] = $file;
        }

        $this->sortContentFiles();
    }

    protected function addContentFileTip(): void
    {
        $path = "wiki" . str_replace(':', '/', $this->uri) . "." . self::FILE_EXTENSION;

        // $list = '<ul><li>'.implode("</li>\n<li>", array_map('htmlspecialchars', $searched)).'</li></ul>';
        $this->contentFiles[] = new WikiText(<<<EOT
                Priority: 0.01

                <div class="tip content-file-tip">
                    <p>
                        If you want to add additional content for this <a href="$this->uri">$this->uri</a> page, create a file <code><var>{module}</var>/$path</code> 
                        in the module's directory. You can use <code>Priority: <var>float:(0,1)</var></code> in the file header to set the content order. 
                        Default priority is <code>0.5</code>.
                    </p>
                </div>
                EOT, WikiText::MIME_HTML);

        $this->sortContentFiles();
    }
}
