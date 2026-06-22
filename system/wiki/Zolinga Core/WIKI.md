 # WIKI

The inbuilt Zolinga WIKI system uses customizable URI path and password. Both can be modified in your configuration file.

Example:

```json
{
    "wiki": {
      "# urlPrefix": "WIKI URL prefix. If not set then wiki is disabled completely.",
      "urlPrefix": "/wiki",

      "# password": "WIKI password. If not set then wiki is disabled completely.",
      "password": "ZOLINGA",

      "# maxAttempts": "Maximum number of login attempts. When exceeded the user must wait.",
      "maxAttempts": 5,

      "# maxAttemptsTimeframe": "Timeframe in seconds for maxAttempts.",
      "maxAttemptsTimeframe": 300,

      "# allowedIps": "List of IP addresses that can see wiki or '*' for all. You can use '*' and '?' as wildcards. E.g. 192.168.* or 192.168.1.?",
      "allowedIps": [
        "*"
      ]
    }
}
```

# Writing Style

The wiki is read by **users** - people who want to get something done with Zolinga, not by programmers reading source code. Write accordingly.

- **Lead with the outcome.** The first line answers "What can I do with this?" not "What is this class called?"
- **Structure around tasks.** Title articles "How to ...", "Configuring ...", "Using ...". Avoid titles that are class names or internal identifiers.
- **Plain language.** Short sentences. Explain jargon in one line the first time it appears.
- **Show what the user does.** A config block to copy, a CLI command to run, or numbered UI steps - not PHP class definitions or method bodies.
- **Keep code user-facing.** Config snippets, CLI invocations, and front-end calls are welcome. Internal PHP wiring (`zolinga.json` listeners, class paths, event origins) belongs in a separate "Technical Details" section at the end, or in the `ref/` reference branch - never in the main flow.
- **One article, one goal.** Split multi-task features into sub-pages.

# Structure

The page names are used as the URI path. The subpages must be placed in a folder that is named the same as the parent page but without the ".md" extension. Example:

```
system/wiki/Zolinga Core/WIKI.md
system/wiki/Zolinga Core/WIKI/Syntax.md
system/wiki/Zolinga Core/WIKI/Structure.md
```

# Merging Pages

The wiki is merged from all `{module}/wiki` folders. The order of articles is determined by the natural order of the file names or `Priority: NUMBER` header in .md files. Example:

```md
Priority: 0.9

The first article.
```

The header section is separated from the content by an empty line.

# Links

All the .md files from all modules are merged into a single wiki. The links to the articles are relative to the wiki root, so you can link to any article using the path like `:Zolinga Core:WIKI:Syntax`. This will display to the `wiki/Zolinga Core/WIKI/Syntax.md` file. The "parent" page `:Zolinga Core:WIKI` will be mapped to the `wiki/Zolinga Core/WIKI.md` file.

# Syntax

The base parser is great [Parsedown](https://github.com/erusev/parsedown/wiki/) with few tweaks.

See [:Zolinga Core:Markdown Syntax] for the complete formatting reference including all standard Markdown and Zolinga-specific extensions.