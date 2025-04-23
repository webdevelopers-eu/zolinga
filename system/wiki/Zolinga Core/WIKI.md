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

# Syntax

The base parser is great [Parsedown](https://github.com/erusev/parsedown/wiki/) with few tweaks.

Syntax should be [standard Markdown](https://www.markdownguide.org/basic-syntax/).

The custom tweaks are:

- **\{{YOUR NAME}}** - will be replaced with the content of the template file from the `{module}/wiki/templates/YOUR NAME.md` file.
- **\`NAMESPACE\CLASS\`** - the class names and links inside backticks will be converted to links to the class documentation.