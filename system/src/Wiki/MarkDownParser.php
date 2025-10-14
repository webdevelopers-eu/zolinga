<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;
use Zolinga\System\Wiki\Ref\WikiGeneratedArticle;

class MarkDownParser extends \Parsedown
{
    public function __construct()
    {
        // there is no construct: parent::__construct();
        $this->setMarkupEscaped(true);

        // Hook on '#tag'string in Markdown
        $this->InlineTypes['#'][] = 'Pill';
        $this->inlineMarkerList .= '#';

        // Hook on {{...}}
        $this->InlineTypes['{'][] = 'Templates';
        $this->inlineMarkerList .= '{';
    }

    /**
     * Undocumented function
     *
     * @param array<string, mixed> $excerpt
     * @return ?array<string, mixed>
     */
    protected function inlineTemplates(array $excerpt): ?array
    {
        if (!preg_match('/^\{\{(.+?)\}\}/', $excerpt['text'], $matches)) {
            return null;
        }

        $templateName = $matches[1];
        $reminder = substr($excerpt['text'], strlen($templateName) + 4);

        $article = new WikiGeneratedArticle(":templates:$templateName");
        $templateData = '';
        foreach ($article->contentFiles as $file) {
            $templateData .= $file->content;
        }

        if (!$templateData) {
            $templateData = "*** Template not found: $templateName . You need to create a file `{module}/wiki/templates/$templateName.md` ***";
        }

        $excerpt['extent'] = strlen($templateName) + 4;
        $excerpt['element'] = [
            "name" => "span",
            "attributes" => [
                "class" => "template",
                "data-template" => $templateName
            ],
            "elements" => $this->textElements($templateData)
        ];
        return $excerpt;
    }

    /**
     * Parse a single line of text
     *
     * @param mixed $excerpt
     * @return array<string, mixed>|null
     */
    protected function inlinePill($excerpt): array|null
    {
        if (preg_match('/^#(\w+)(?>[\s,;:.]|$)/', $excerpt['text'], $matches)) {
            return array(

                // How many characters to advance the Parsedown's
                // cursor after being done processing this tag.
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'span',
                    'text' => $matches[1],
                    'attributes' => array(
                        'class' => 'pill ' . $matches[1],
                    ),
                ),

            );
        } else {
            return null;
        }
    }

    public function toHTML(string $text): string
    {
        return $this->text($text);
    }

    /**
     * Parse a single line of text
     *
     * @param mixed $excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineCode($excerpt): array|null
    {
        $ret = parent::inlineCode($excerpt);

        if ($ret === null) {
            trigger_error("inlineCode parent returned null for " . json_encode($excerpt), E_USER_WARNING);
            return null;
        }

        $ret['element'] = [
            'name' => 'code',
            'attributes' => [
                'cls' => 'wiki-inspected-code',
            ],
            "elements" => $this->addLinks($ret['element']['text'])
        ];

        return $ret;
    }

    /**
     * Called for each line of a fenced code block ```php ... ```
     *
     * @param mixed $line
     * @param mixed $block
     * @return array<string, mixed>|null
     */
    protected function blockFencedCodeContinue($line, $block): array|null
    {
        $ret = parent::blockFencedCodeContinue($line, $block);
        // $ret['element']['element']['elements'] = $this->addLinks($line['text']);
        if (($ret['complete'] ?? false) === true && $ret['char'] === '`') {
            if ($ret['element']['name'] !== 'pre') { // ``` code block ```
                $text = $ret['element']['element']['text'];
                $ret['element']['element']['elements'] = $this->addLinks($text);
            } elseif (
                isset($ret['element']['element']['attributes']['class'])
                &&
                $ret['element']['element']['attributes']['class'] == 'language-php'
            ) {
                $text = $ret['element']['element']['text'];
                $html = highlight_string("<" . "?php\n" . $text, true) ?: $text;
                $html = self::linkifyHTML($html);
                $html = "<div class='php-code-block'>$html</div>";
                $ret['element'] = ['rawHtml' => $html];
            }
        }

        return $ret ? $ret : null;
    }

    /**
     * :ref:Zolinga:System:Api
     * :ref:Zolinga:System:Api:dispatchEvent
     * :ref:service
     * :ref:service:fs
     * :ref:event
     * :ref:event:ble-ble
     * :ref:config
     * :ref:module
     * :ref:module:ble-ble
     *
     * @param string $text
     * @return array<string, mixed>
     */
    private function addLinks(string $text): array
    {
        $replaced = self::linkifyMarkdown($text);
        // $this->textElements($replaced)
        $elements = $this->linesElements([$replaced]);
        array_walk($elements, fn (&$el) => $el['name'] = 'span');
        return $elements;
    }

    static function linkifyMarkdown(string $text, ?string $baseNamespace = null, bool $shortClasses = false): string
    {
        return self::linkify($text, fn ($text, $uri) => "[$text]($uri)", $baseNamespace, $shortClasses);
    }

    static function linkifyHTML(string $text, ?string $baseNamespace = null, bool $shortClasses = false): string
    {
        return self::linkify($text, fn ($text, $uri, $type) => "<a class='{$type}' href=\"" . htmlspecialchars($uri) . "\">" . htmlspecialchars($text) . "</a>", $baseNamespace, $shortClasses);
    }

    /**
     * Take a text and replace all class names and method names with links.
     *
     * @param string $text the text to be linked
     * @param callable $linkCallback function(string $text, string $uri, string $type): string
     * @param string|null $baseNamespace the base namespace to use for relative class names
     * @param boolean $shortClasses if true, only the last part of the class name is used
     * @return string
     */
    static function linkify(string $text, callable $linkCallback, ?string $baseNamespace = null, bool $shortClasses = false): string
    {
        $prp = '(?<![a-zA-Z0-9_])[a-z_](?i)[a-z0-9_]*(?![a-z0-9_])(?-i)';
        $var = "\\\${$prp}";
        $op = '(?:->|-&gt;|::)';
        $clsName = '(?<![a-zA-Z0-9_])[A-Z_](?i)[a-z0-9_]*(?![a-z0-9_])(?-i)';
        $cls = "(?<![a-zA-Z0-9\$_\\\\])(?:{$clsName})?(?:\\\\{$clsName})+(?![a-z0-9_\\\\])(?-i)";

        $replaced = preg_replace_callback_array(
            [
                "/(?:(?<variable>$var)|(?<class>$cls))(?:(?<op1>$op)(?<serviceName>$prp))?(?:(?:(?<op2>$op)(?:(?<method>$prp)(?=\()|(?<property>$prp)))?)?/" =>
                function ($m) use ($linkCallback, $baseNamespace, $shortClasses): string {
                    $m = [
                        'variable' => false,
                        'class' => false,
                        'op1' => false,
                        'serviceName' => false,
                        'op2' => false,
                        'method' => false,
                        'property' => false,
                        ...$m
                    ];

                    $uri = ':ref';
                    $text = '';

                    if ($m['variable'] == '$api') {
                        $uri .= ':service';
                        $text .= $linkCallback($m['variable'], ':ref:Zolinga:System:Api', 'class');
                    } elseif ($m['class']) {
                        if (!str_starts_with($m['class'], '\\')) {
                            $m['class'] = $baseNamespace . '\\' . $m['class'];
                        }
                        $uri .= ':' . trim(str_replace('\\', ':', $m['class']), ':');
                        $class = $shortClasses ? substr($m['class'], strrpos($m['class'], '\\') + 1) : $m['class'];
                        $text .= $linkCallback($class, $uri, 'class');
                    } else {
                        return $m[0]; // leave as is, some other code, variable or something
                    }
                    $text .= $m['op1'];
                    if ($m['serviceName']) {
                        $uri .= ':' . $m['serviceName'];
                        $text .= $linkCallback($m['serviceName'], $uri, 'service');
                    }
                    $text .= $m['op2'];
                    if ($m['method']) {
                        $uri .= ':' . $m['method'];
                        $text .= $linkCallback($m['method'], $uri, 'method');
                    } elseif ($m['property']) {
                        $text .= $m['property'];
                    }

                    return $text;
                }
            ],
            $text
        );

        return $replaced;
    }

    static public function removeCommentStars(string $comment): string
    {
        $isDocBlock = str_starts_with($comment, '/**');
        if (!$isDocBlock) {
            return $comment;
        }

        $comment = preg_replace('/^\s*\/\*\*\s?|\*\/$/', '', $comment);
        $comment = preg_replace('/^\s*\*[^\S\n\r]?/m', '', $comment);
        return $comment;
    }

    // Join broken lines
    static public function reflowText(string $comment): string
    {
        // Split per lines
        $output = [];
        $isPlainText = $isPrevPlainText = false;
        $indent = $prevIndent = '';
        foreach (preg_split('/\R/', $comment) ?: [] as $line) {
            preg_match('/^(\s*)(.*?)$/', $line, $m);
            [$all, $indent, $text] = $m;

            $isPlainText = preg_match('/^\w/', $text);

            if ($isPlainText && $isPrevPlainText && $indent === $prevIndent) {
                // Append to previous line
                $output[count($output) - 1] .= ' ' . $line;
            } else {
                $output[] = $line;
            }

            $prevIndent = $indent;
            $isPrevPlainText = $isPlainText;
        }

        return implode("\n", $output);
    }
}
