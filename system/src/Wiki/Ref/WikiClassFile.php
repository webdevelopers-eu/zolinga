<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki\Ref;

use Illuminate\Mail\Markdown;
use Zolinga\System\Wiki\{WikiFile, MarkDownParser};
use Zolinga\System\Events\StoppableInterface;
use ReflectionClass, ReflectionType, ReflectionMethod, ReflectionProperty, ReflectionNamedType, ReflectionIntersectionType, ReflectionUnionType, Reflector, ReflectionParameter;

/**
 * Class representing a PHP class file.
 * 
 * @property class-string $path
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-03-13
 */
class WikiClassFile extends WikiFile
{
    public function __construct(string $className)
    {
        // If the last part starts with a lower case letter, it's a method name or property name, remove it
        $parts = explode('\\', $className);
        if (preg_match('/^[a-z]/', $parts[count($parts) - 1])) {
            array_pop($parts);
            $className = implode('\\', $parts);
        }


        parent::__construct($className);
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case "content":
                return "**Generated Article**";
            case "html":
                return $this->generateHTML();
            default:
                return parent::__get($name);
        }
    }

    private function generateHTML(): string
    {
        global $api;

        $ref = new ReflectionClass($this->path);

        $html = "<main class='wiki-generated-article wiki-ref-class'>";
        $html .= "<h1>Class {$this->path}</h1>";

        $fileName = $ref->getFileName();
        if (is_string($fileName)) { // if internal classes then it is bool
            $zolingaUri = $api->fs->toZolingaUri($fileName) or throw new \Exception("Cannot convert file path to Zolinga URI: " . $ref->getFileName());
        } else {
            $zolingaUri = '';
        }
        $module = parse_url($zolingaUri, PHP_URL_HOST);
        $path = ltrim(parse_url($zolingaUri, PHP_URL_PATH) ?: '', '/');
        $html .= "<div class='path'>Declared in <a class='module' href=':ref:module:$module'>$module</a>: <code>$path</code></div>";

        // Extends/implements/traits list
        $extends = $ref->getParentClass();
        $implements = $ref->getInterfaceNames();
        $traits = $ref->getTraitNames();
        if ($extends || $implements || $traits) {
            $html .= "<ul class='inheritance'>";
            if ($extends) {
                $html .= "<li><span class='extends'>extends</span> <span class='name'>" . $this->typeToNameHTML($extends) . "</span></li>\n";
            }
            if ($implements) {
                $implementsHTML = array_map(fn ($name) => $this->typeToNameHTML(new ReflectionClass($name)), $implements);
                $html .= "<li><span class='implements'>implements</span> <span class='name'>" . implode(", ", $implementsHTML) . "</span></li>\n";
            }
            if ($traits) {
                $traitsHTML = array_map(fn ($name) => $this->typeToNameHTML(new ReflectionClass($name)), $traits);
                $html .= "<li><span class='uses'>uses</span> <span class='name'>" . implode(", ", $traitsHTML) . "</span></li>\n";
            }
            $html .= "</ul>";
        }

        $html .= $this->formatCommentHTML($ref);
        $html .= $this->generateConstantsHTML($ref);
        $html .= $this->generatePropertiesHTML($ref);
        $html .= $this->generateMethodsHTML($ref);

        $html .= "</main>";
        return $html;
    }

    /**
     * Generate HTML for all methods in the class.
     *
     * @param ReflectionClass<object> $ref
     * @return string
     */
    private function generateMethodsHTML(ReflectionClass $ref): string
    {
        if (count($ref->getMethods()) === 0) return "";

        $html = "<h2>Methods</h2>";
        $classMethods = [];
        foreach ($this->sortReflections($ref->getMethods()) as $method) {
            /** @var ReflectionMethod $method */
            $declaringClass = $method->getDeclaringClass();
            $methodHtml = "<li>";
            // if ($method->isConstructor()) $methodHtml .= "<span class='constructor'>constructor</span> ";
            // if ($method->isDestructor()) $methodHtml .= "<span class='destructor'>destructor</span> ";
            if ($method->isStatic()) $methodHtml .= "<span class='static'>static</span> ";
            if ($method->isAbstract()) $methodHtml .= "<span class='abstract'>abstract</span> ";
            if ($method->isFinal()) $methodHtml .= "<span class='final'>final</span> ";
            if ($method->isPublic()) $methodHtml .= "<span class='public'>public</span> ";
            if ($method->isProtected()) $methodHtml .= "<span class='protected'>protected</span> ";
            if ($method->isPrivate()) $methodHtml .= "<span class='private'>private</span> ";

            $methodHtml .= $this->getDeclaringClassLink($ref, $method);

            $methodHtml .= "<span class='name'>{$method->name}</span>";
            $methodHtml .= $this->generateMethodParamsHTML($method);
            if ($method->hasReturnType()) {
                $methodHtml .= " <span class='return'><span>:</span> <span>" . self::typeToNameHTML($method->getReturnType()) . "</span></span>";
            }
            $methodHtml .= $this->formatCommentHTML($method);
            $methodHtml .= "</li>";

            if (!isset($classMethods[$declaringClass->name])) $classMethods[$declaringClass->name] = [];
            $classMethods[$declaringClass->name][] = $methodHtml;
        }

        // Sort by inheritance
        $html .= "<ul class='wiki-ref-list wiki-ref-methods'>";
        foreach ($classMethods as $className => $methods) {
            $html .= implode("\n", $methods);
        }
        $html .= "</ul>";
        return $html;
    }

    /**
     * Undocumented function
     *
     * @param ReflectionClass<object> $refClass
     * @param ReflectionProperty|ReflectionMethod $refItem
     * @return string
     */
    private function getDeclaringClassLink(ReflectionClass $refClass, ReflectionProperty|ReflectionMethod $refItem): string
    {
        $originClass = $refItem->getDeclaringClass();
        if ($originClass->name === $refClass->name) return "";

        $uri = ':ref:' . implode(':', explode('\\', $originClass->name));
        return " <a href='{$uri}' class='pill inherited' title='Declared on {$originClass->name}'>↳</a>";
    }

    private function generateMethodParamsHTML(ReflectionMethod $method): string
    {
        if (count($method->getParameters()) === 0) return "()";

        $html = "<span class='brackets'>(</span><span class='params'>";
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramHtml = "";
            if ($param->hasType()) {
                $paramHtml = self::typeToNameHTML($param->getType()) . " ";
            }
            if ($param->isPassedByReference()) $paramHtml .= "<span class='reference'>&</span>";
            $paramHtml .= "<span class='name'>\${$param->name}</span>";

            if (($param instanceof ReflectionParameter && $param->isDefaultValueAvailable()) /* || ($param instanceof ReflectionProperty && $param->hasDefaultValue()) */) {
                $var = var_export($param->getDefaultValue(), true);
                $paramHtml .= " <span class='default'><span>=</span> <span>" . htmlspecialchars($var) . "</span></span>";
            }
            $params[] = $paramHtml;
        }
        $html .= implode(", ", $params);
        $html .= "</span><span class='brackets'>)</span>";
        return $html;
    }

    /**
     * Generate HTML for all constants in the class.
     *
     * @param ReflectionClass<object> $ref
     * @return string
     */
    private function generateConstantsHTML(ReflectionClass $ref): string
    {
        if (count($ref->getConstants()) === 0) return "";

        $html = "<h2>Constants</h2>";
        $html .= "<ul class='wiki-ref-list wiki-ref-constants'>";
        foreach ($ref->getConstants() as $name => $value) {
            $html .= "<li>";
            $html .= "<span class='name'>{$name}</span>";
            $html .= " <span class='assignment'>=</span>";
            $html .= " <span class='value'>" . htmlspecialchars(var_export($value, true)) . "</span>";
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    /**
     * Generate HTML for all properties in the class.
     *
     * @param ReflectionClass<object> $ref
     * @return string
     */
    private function generatePropertiesHTML(ReflectionClass $ref): string
    {
        if (count($ref->getProperties()) === 0) return "";

        $html = "<h2>Properties</h2>";

        $itemsList = [];
        foreach ($this->sortReflections($ref->getProperties()) as $prop) {
            /** @var ReflectionProperty $prop */
            $declaringClass = $prop->getDeclaringClass();
            $itemHtml = "<li>";

            if ($prop->isStatic()) $itemHtml .= "<span class='static'>static</span> ";
            if ($prop->isPublic()) $itemHtml .= "<span class='public'>public</span> ";
            if ($prop->isProtected()) $itemHtml .= "<span class='protected'>protected</span> ";
            if ($prop->isPrivate()) $itemHtml .= "<span class='private'>private</span> ";
            if ($prop->isReadOnly()) $itemHtml .= "<span class='readonly'>readonly</span> ";

            $itemHtml .= $this->getDeclaringClassLink($ref, $prop);

            if ($prop->hasType()) {
                $itemHtml .= self::typeToNameHTML($prop->getType()) . " ";
            }

            $itemHtml .= "<span class='name'>\${$prop->name}</span>";

            if ($prop->hasDefaultValue()) {
                $var = var_export($prop->getDefaultValue(), true);
                $itemHtml .= " <span class='default'><span>=</span> <span>" . htmlspecialchars($var) . "</span></span>";
            }

            $itemHtml .= $this->formatCommentHTML($prop);
            $itemHtml .= "</li>";

            if (!isset($itemsList[$declaringClass->name])) $itemsList[$declaringClass->name] = [];
            $itemsList[$declaringClass->name][] = $itemHtml;
        }

        $html .= "<ul class='wiki-ref-list wiki-ref-properties'>";
        foreach ($itemsList as $className => $items) {
            $html .= implode("\n", $items);
        }
        $html .= "</ul>";
        return $html;
    }

    /**
     * Format the comment block as HTML.
     *
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty $ref
     * @return string
     */
    private function formatCommentHTML(ReflectionClass|ReflectionMethod|ReflectionProperty $ref): string
    {
        $comment = $ref->getDocComment();
        if (!$comment) return "";

        $comment = MarkDownParser::removeCommentStars($comment);
        $comment = MarkDownParser::reflowText($comment);

        $html = "<div class='comment-block'><pre class='comment'>" . htmlspecialchars($comment) . "</pre></div>";

        $baseNamespace = str_replace('/', '\\', dirname(str_replace('\\', '/', $this->path)));
        $html = MarkDownParser::linkifyHTML($html, $baseNamespace);

        return $html;
    }

    /**
     * Convert a ReflectionType to a human-readable string.
     *
     * @param ReflectionClass<object>|ReflectionType $ref
     * @return string
     */
    static public function typeToNameHTML(ReflectionClass|ReflectionType $ref): string
    {
        $separator = '|';
        $list = [];

        if ($ref instanceof ReflectionClass) {
            $list = [$ref->getName()];
        } elseif ($ref instanceof ReflectionNamedType) {
            $list = [$ref->getName()];
        } elseif ($ref instanceof ReflectionUnionType) {
            /** @phpstan-ignore-next-line */
            $list = array_map(fn ($type) =>
            /** @var ReflectionType $type */
            $type->getName(), $ref->getTypes());
        } elseif ($ref instanceof ReflectionIntersectionType) {
            $separator = '&';
            /** @phpstan-ignore-next-line */
            $list = array_map(fn ($type) => $type->getName(), $ref->getTypes());
        } else {
            $list = ['unknown'];
        }

        $isNullable = is_callable([$ref, 'allowsNull']) ? $ref->allowsNull() : false;
        $list = array_unique($list);

        // Shorten the Class names
        $list = array_map(function (string $name) {
            $parts = explode('\\', $name);
            if (count($parts) < 2) return $name;
            $uri = ':ref:' . implode(':', $parts);
            $flavors = '';
            if (is_subclass_of($name, StoppableInterface::class)) {
                $flavors .= '<span class="pill stoppable">stoppable</span>';
            }
            return "<a href=\"$uri\" title='" . $name . "'>" . array_pop($parts) . $flavors . "</a>";
        }, $list);

        return '<span class="type">' . ($isNullable ? '?' : '') . implode($separator, $list) . '</span>';
    }

    /**
     * Sort the Reflections by visibility and name.
     *
     * @param array<ReflectionMethod|ReflectionProperty> $reflections
     * @return array<ReflectionMethod|ReflectionProperty>
     */
    private function sortReflections(array $reflections): array
    {
        // Sort public->protected->private and then by name 
        usort($reflections, function ($a, $b) {
            $aName =
                ($a->isPublic() ? 'A' : 'B') .
                ($a->isProtected() ? 'A' : 'B') .
                ($a->isPrivate() ? 'A' : 'B') .
                $a->name;
            $bName =
                ($b->isPublic() ? 'A' : 'B') .
                ($b->isProtected() ? 'A' : 'B') .
                ($b->isPrivate() ? 'A' : 'B') .
                $b->name;

            return strcmp($aName, $bName);
        });
        return $reflections;
    }
}
