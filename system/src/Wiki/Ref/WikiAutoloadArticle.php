<?php

namespace Zolinga\System\Wiki\Ref;

use Zolinga\System\Wiki\WikiArticle;
use Zolinga\System\Wiki\WikiText;
use const Zolinga\System\ROOT_DIR;

class WikiAutoloadArticle extends WikiArticle
{
    // /**
    //  * @var array<class-string>
    //  */
    // private ?array $classList = null;

    public function __construct(string $uri)
    {
        parent::__construct($uri);
        $this->title = "Autoloading";

        $this->contentFiles[] = new WikiText($this->generateContentHtml(), WikiText::MIME_HTML);
    }

    protected function initChildren(): void
    {
        // This causes a lot of memory usage and is not necessary to load ALL classes during searching...
        // So we don't append them to the tree as it would be fully searchable...

        // $uris = array_map(fn ($class) => ':ref:class:' . str_replace('\\', ':', $class), $this->discoverClasses());
        // foreach ($uris as $uri) {
        //     $this->addChild(new WikiClassArticle($uri));
        // }
    }

    private function generateContentHtml(): string
    {
        global $api;
        $autoloads = "<table>";
        foreach ($api->autoloader->namespaces as $namespace => $path) {
            $autoloads .= "<tr><td>$namespace</td><td>$path</td></tr>";
        }
        $autoloads .= "</table>";

        return <<<EOT
        <h1>Autoload Definitions</h1>

        <p>
            This section of the documentation lists the autoloading definitions in the system.
            For more information read <a href=":Zolinga Core:Manifest File">Manifest File</a> article.
        </p>

        $autoloads
        EOT;
    }

    // private function generateContentHtml(): string
    // {
    //     $list = [];
    //     foreach ($this->discoverClasses() as $class) {
    //         $uri = ':ref:class:' . str_replace('\\', ':', $class);
    //         $class = preg_replace('@^(.*\\\\)([^\\\\]+)$@', '<span class="class"><small>$1</small><b>$2</b></span>', $class);
    //         $list[] = "<a href=\"$uri\">$class</a>";
    //     }
    //     $listHtml = '<li>' . implode("</li>\n<li>", $list) . '</li>';

    //     return <<<EOT
    //     <h1>Classes</h1>
    //     <p>This section of the documentation lists important system classes. Note that the class discovery is not exhaustive or accurate.</p>
    //     <ol>$listHtml</ol>
    //     EOT;
    // }

    // /**
    //  * Discover all classes in the system by reading the autoloader configuration and searching the file names.
    //  *
    //  * @return array<class-string>
    //  */
    // private function discoverClasses(): array
    // {
    //     global $api;

    //     if ($this->classList !== null) {
    //         return $this->classList;
    //     }

    //     $list = [];
    //     foreach ($api->autoloader->namespaces as $prefix => $target) {
    //         $dir = ROOT_DIR . '/' . $target;
    //         if (is_file($dir)) {
    //             $list[] = $prefix;
    //             continue;
    //         }
    //         if (!is_dir($dir)) {
    //             trigger_error("Autoloader namespace '$prefix' points to a non-existent directory '$dir'.", E_USER_WARNING);
    //             continue;
    //         }

    //         // Find all '*.php' files in the target directory recursively
    //         /** @var \RecursiveDirectoryIterator $iterator */
    //         $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    //         foreach ($iterator as $file) {
    //             /** @var \SplFileInfo $file */
    //             if ($file->getExtension() === 'php') {
    //                 // Map back to a class name
    //                 $path = $iterator->getSubPathname();
    //                 // token_get_all() is too slow for this purpose
    //                 $class = rtrim($prefix, '\\') . '\\' . str_replace('/', '\\', substr($path, 0, -4));
    //                 $list[] = $class;
    //             }
    //         }
    //     }

    //     sort($list);
    //     $this->classList = $list;

    //     return $this->classList;
    // }
}
