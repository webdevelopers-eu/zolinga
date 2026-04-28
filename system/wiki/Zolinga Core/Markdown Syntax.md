# Markdown Syntax

Zolinga's wiki uses [Parsedown](https://parsedown.org/) as its Markdown parser with Zolinga-specific extensions. Below is the complete reference of supported formatting.

# Standard Markdown (Parsedown)

## Headings

```md
# H1
## H2
### H3
#### H4
##### H5
###### H6
```

Setext-style headings also work:

```md
H1
===

H2
---
```

## Paragraphs

One or more consecutive lines of text separated by blank lines form a paragraph.

```md
This is the first paragraph.

This is the second paragraph.
```

## Emphasis

```md
*italic* or _italic_
**bold** or __bold__
~~strikethrough~~
```

## Line Breaks

A line ending with two or more spaces (or a backslash `\`) creates a `<br>`:

```md
First line  
Second line
```

## Links

### Inline Links

```md
[link text](https://example.com)
[link with title](https://example.com "Title")
```

### Reference Links

```md
[link text][ref]

[ref]: https://example.com
[ref]: https://example.com "Optional Title"
```

### URL Auto-Links

Bare URLs are automatically converted to clickable links:

```md
https://example.com
```

Angle-bracket URL tags:

```md
<https://example.com>
```

### Email Auto-Links

```md
<user@example.com>
<mailto:user@example.com>
```

## Images

```md
![alt text](https://example.com/image.png)
![alt text](https://example.com/image.png "Title")
```

## Code

### Inline Code

```md
Use `code` in a sentence.
```

### Fenced Code Blocks

````md
```javascript
console.log("hello");
```
````

The language identifier after the opening fence adds a `language-*` class to the `<code>` element:

```md
```php
echo "hello";
```
```

### Indented Code Blocks

Four spaces of indentation creates a code block:

```md
    This is a code block.
    Second line.
```

## Lists

### Unordered

```md
- Item 1
- Item 2
- Item 3
```

Also works with `*` or `+` markers.

### Ordered

```md
1. First
2. Second
3. Third
```

Start number can be changed:

```md
5. Fifth
6. Sixth
```

### Nested

Indent list items to nest them:

```md
- Item 1
  - Sub-item 1
  - Sub-item 2
- Item 2
```

### Loose Lists

A blank line between list items creates "loose" lists (each item wrapped in `<p>`):

```md
- Item 1

- Item 2
```

## Blockquotes

```md
> This is a blockquote.
> It can span multiple lines.
```

## Horizontal Rules

Three or more `*`, `-`, or `_` characters on a line:

```md
---
***
___
```

## Tables

```md
| Header 1 | Header 2 | Header 3 |
|----------|:---------|:--------:|
| Cell 1   | Cell 2   | Cell 3   |
| Cell 4   | Cell 5   | Cell 6   |
```

Alignment: `:---` left, `---:` right, `:---:` center, `---` default.

Leading/trailing pipes are optional.

## HTML

Raw HTML is passed through (when `markupEscaped` is off):

```md
<div class="note">
  Raw HTML content
</div>
```

HTML comments are preserved:

```md
<!-- This is a comment -->
```

## Escape Sequences

Backslash escapes special characters:

```md
\*not italic\*
\[not a link\]
```

Escapable characters: `\ ` ` * _ { } [ ] ( ) > # + - . ! | ~`

## Special Characters

HTML entities are preserved:

```md
&amp; &copy; &#1234;
```

# Zolinga Extensions

## Wiki Links

Links to other wiki pages use the colon-separated path syntax:

```md
[:Zolinga Core:WIKI]
[:Zolinga Core:Events and Listeners]
```

## Template Includes — `{{name}}`

Double-brace syntax includes a template file from `{module}/wiki/templates/NAME.md`:

```md
{{YOUR NAME}}
```

The template content is rendered inline at that position. If the template file does not exist, a warning is displayed.

## Class/Method Linkification — `` `Namespace\Class` ``

Fully-qualified class names inside backticks are automatically converted to links to the class documentation:

```md
`Zolinga\System\Api`
`Zolinga\System\Events\ListenerInterface`
```

Method chains also work:

```md
`$api->log->message()`
`Zolinga\System\Api::dispatchEvent()`
```

The linkification applies inside both inline code and fenced code blocks (PHP blocks get syntax highlighting too).

## Pill Tags — `#tag`

A hash followed by a word creates a styled pill element:

```md
#important #deprecated
```

Renders as a `<span class="pill">` element.

## PHP Syntax Highlighting

Fenced code blocks with the `php` language identifier are automatically syntax-highlighted using PHP's built-in `highlight_string()`:

````md
```php
$api->log->message("Hello");
```
````

# Not Supported

The following CommonMark features are **not** supported by Parsedown:

- **Task lists** (`- [x] item`) — not parsed as checkboxes
- **Footnotes** — no native support
- **Definition lists** — not supported
- **Abbreviations** — not supported