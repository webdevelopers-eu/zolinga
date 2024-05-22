<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;

use Zolinga\System\Wiki\WikiSearchResult;

use JsonSerializable;

/**
 * Class WikiArticle
 *
 * This class represents a wiki article.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-26
 * 
 * @property-read float $priority
 * @property-read string $uri URI of the article, starts with ':' and uses ':' as namespace separator
 * @property-read array<WikiFile> $contentFiles List of content files
 * @property-read array<WikiArticle> $children List of child articles
 * @property-read array<int,string> $uriParts URI parts of the fully-qualified article URI
 * 
 */
class WikiArticle implements JsonSerializable
{
    const FILE_EXTENSION = "md";

    /**
     * The first file or directory that hinted creation of this article.
     *
     * @var string
     */
    public readonly ?string $baseFile;

    /**
     * Fully-qualified title of the article
     *
     * @var array<int,string> of namespace parts, first part is always empty (root namespace)
     */
    public readonly array $uriParts;

    /**
     * Name of the article. The human readble last part of the URI.
     *
     * @var string
     */
    public string $title;

    /**
     * List of associated content files.
     *
     * @var array<WikiFile>
     */
    protected array $contentFiles = [];

    /**
     * List of child Articles in this namespace
     *
     * @var ?array<WikiArticle>
     */
    private ?array $children = null;

    /**
     * Constructor
     *
     * @param string $uri Fully-qualified title of the WIKI article starting with ':' and using ':' as namespace separator
     */
    public function __construct(string $uri, ?string $baseFile = null)
    {
        global $api;

        $this->baseFile = $baseFile ? $api->fs->toZolingaUri($baseFile) : null;
        $uriParts = str_replace('/', ':', $uri);

        if (substr($uriParts, 0, 1) !== ':') {
            throw new \Exception("Fully-qualified title must start with a colon: $uriParts");
        }

        $this->uriParts = preg_split('@[:/]+@', rtrim($uriParts, ':')) or throw new \Exception("Invalid fully-quallified title: $uriParts");
        $this->title = $this->fileToName($this->uriParts[count($this->uriParts) - 1]);
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'uri':
                return implode(':', $this->uriParts) ?: ':'; // root namespace
            case 'contentFiles':
                return $this->contentFiles;
            case 'children':
                if ($this->children === null) {
                    $this->children = [];
                    $this->initChildren();
                }
                return $this->children;
            case 'priority':
                // Return the highest priority of contents files
                $priority = $this->contentFiles ? max(array_map(fn (WikiFile $file) => $file->priority ?: 0.5, $this->contentFiles)) : 0.5;
                return 0 < $priority && $priority < 1 ? $priority : 0.5;
            default:
                throw new \Exception("Property $name does not exist.");
        }
    }

    protected function addChild(WikiArticle ...$children): void
    {
        $this->children = [...($this->children ?? []), ...$children];
        usort($this->children, fn ($a, $b) => $b->priority <=> $a->priority);
    }

    protected function sortContentFiles(): void
    {
        usort($this->contentFiles, fn ($a, $b) => $b->priority <=> $a->priority);
    }

    public function addContentFile(string $file): WikiFile
    {
        global $api;

        // Check extension and that it is in the module directory inside 'wiki' folder
        $uri = $api->fs->toZolingaUri($file);
        if (
            !$uri ||
            parse_url($uri, PHP_URL_SCHEME) !== 'wiki' ||
            pathinfo($uri, PATHINFO_EXTENSION) !== self::FILE_EXTENSION
        ) {
            throw new \Exception("File must have ." . self::FILE_EXTENSION . " extension and must be placed inside module's \"wiki\" folder: $file (URI: $uri)");
        }

        $file = new WikiFile($file);
        $this->contentFiles[] = $file;
        $this->sortContentFiles();
        return $file;
    }

    /**
     * Find all articles with the given URI within this branch.
     *
     * @param string $uri
     * @return ?WikiArticle
     */
    public function get(string $uri): ?WikiArticle
    {
        if (strpos($uri, $this->uri) !== 0) {
            return null; // not in this branch
        }

        if ($this->uri === $uri) {
            return $this;
        } else {
            foreach ($this->__get('children') as $child) {
                $ret = $child->get($uri);
                if ($ret) {
                    return $ret;
                }
            }
        }

        return null;
    }

    /** 
     * Search for articles containing all listed search phrases.
     * 
     * @param array<string> $searches
     * @return array<WikiSearchResult>
     */
    public function search(array $searches): array
    {
        $results = [];

        // Search title and URI
        $found = $this->searchText($searches, $this->uri . ' ' . $this->title);
        if ($found) {
            $results[] = $found;
        } else { // search contents
            $contentText = implode("\n", array_map(fn (WikiFile $file) => $file->plainText, $this->contentFiles));
            $found = $this->searchText($searches, $contentText);
            if ($found) {
                $results[] = $found;
            }
        }

        foreach ($this->__get('children') as $child) {
            $results = array_merge($results, $child->search($searches));
        }

        return $results;
    }

    /**
     * Search for the given phrases in the given text.
     * 
     * @param array<string> $searches the search phrases
     * @param string $text
     * @return WikiSearchResult|false
     */
    private function searchText(array $searches, string $text): WikiSearchResult|false
    {
        $searchesRE = array_map(fn ($search) => preg_quote($search, '/'), $searches);

        // Check if the text contains all search phrases
        foreach ($searchesRE as $search) {
            if (!preg_match("/$search/iuS", $text)) {
                return false;
            }
        }

        // search for composite frazes in snippet
        $composite = [];
        foreach ($searchesRE as $search) {
            $composite[] = $search;
            array_unshift($searchesRE, implode(".{0,8}?", $composite));
        }

        // Sort from longest string to shortest string
        usort($searchesRE, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        // It has all the search phrases, extract the snippet.
        $context = 32;
        // the '?' in (?<before>.{0,$context}?) makes the 'match' greedy 
        $snippetRE = "/(?<before>.{0,$context}?)(?<match>" . implode('|', $searchesRE) . ")(?<after>.{0,$context})/iuS";
        // trigger_error($snippetRE, E_USER_NOTICE);
        if (preg_match_all($snippetRE, $text, $matches, PREG_SET_ORDER)) {
            // Only keep the best matches
            $bestMatches = 6;
            $lengths = array_map(fn ($m) => strlen($m['match']), $matches);
            sort($lengths, SORT_NUMERIC);
            $minLength = $lengths[max(0, count($lengths) - $bestMatches)];
            $matches = array_filter($matches, fn ($m) => strlen($m['match']) >= $minLength);
            
            // trigger_error(print_r($matches, true), E_USER_NOTICE);
            $matchesHTML = array_map(fn ($m) => htmlspecialchars($m['before']) . '<b>' . htmlspecialchars($m['match']) . '</b>' . htmlspecialchars($m['after']), $matches);
            $snippetHTML = "…" . implode("<span>…</span>", $matchesHTML) . "…";
        } else {
            throw new \Exception("Snippet not found in text: $text", 404); // should not happen
        }

        return new WikiSearchResult($this->uri, $this->title, $this->priority, $snippetHTML);
    }

    protected function initChildren(): void
    {
        global $api;

        $children = [];

        $iterator = $this->getChildFileIterator();
        $directories = [];
        foreach ($iterator as $fileInfo) {
            $uriParts = parse_url($fileInfo->getPath(), PHP_URL_PATH) . '/' . $fileInfo->getBasename('.' . self::FILE_EXTENSION);
            if (preg_match("@^/(ref|templates)($|/)@", $uriParts)) continue; // :ref:* and :templates:* is special and is handled separately
            if ($fileInfo->isDir() && substr($fileInfo->getBasename(), 0, 1) !== '.') {
                $directories[$uriParts] = $fileInfo;
            } elseif ($fileInfo->isFile() && $fileInfo->getExtension() === self::FILE_EXTENSION) {
                if (!isset($children[$uriParts])) {
                    $children[$uriParts] = new WikiArticle($uriParts, $fileInfo->getPathname());
                }
                $children[$uriParts]->addContentFile($fileInfo->getPathname());
            }
        }

        // Append directories that have no matching .md file as empty articles
        foreach ($directories as $uriParts => $dir) {
            if (!isset($children[$uriParts])) {
                $children[$uriParts] = new WikiArticle($uriParts, $dir->getPathname());
            }
        }
        $this->addChild(...$children);
    }

    protected function getChildFileIterator(): \Iterator
    {
        global $api;

        $path = implode('/', $this->uriParts);

        // Cycle
        $iterator = new \AppendIterator();
        foreach ($api->manifest->moduleNames as $moduleName) {
            $dir = 'wiki://' . $moduleName . $path;
            if (is_dir($dir)) {
                $iterator->append(new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO));
            }
        }

        return $iterator;
    }

    private function fileToName(?string $title): string
    {
        if (!$title) return '';
        $baseName = basename($title, '.' . self::FILE_EXTENSION);
        return str_replace('_', ' ', $baseName);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->uri, // For z-template to identify elements
            'uri' => $this->uri,
            'title' => $this->title,
            'contentFiles' => $this->contentFiles,
            'priority' => $this->priority,
            'children' => $this->__get('children') // lazy initialization
        ];
    }
}
