# Basics

* All modules are stored in `${workspaceFolder}/modulesp/` directory.
    * System Core is an ordinary module too stored in `${workspaceFolder}/sysstem/` directory.
* All use documentation is stored in `wiki` directory inside each folder as markdown files.
* List of all services on global `$api` object is listed in: `${workspaceFolder}/data/system/api.stub.php`
* Each module caries `zolinga.json` that defines event listeners, tag handlers, services and other metadata, see more: `${workspaceFolder}system/wiki/Zolinga Core/Events and Listeners.md`
    * All joined `zolinga.json` files are stored in `${workspaceFolder}/data/system/system.cache.json` file.
* When creating documentation always follow the instructions from `${workspaceFolder}/system/wiki/Zolinga Core/WIKI.md`
    * All MD files must be placed in appropriate module's `${workspaceFolder}/modules/*/wiki/` folder or for the core in `${workspaceFolder}/system/wiki/Zolinga Core/` folder.
    * Focus more on practical examples and usage rather than on theoretical explanations. Simply how to get things done.
* Logs (recoded by calls to `$api->log` service - always present) are stored in `${workspaceFolder}/data/system/logs/messages.log` 
* When accessing files use Zolinga FS path syntax: see `${workspaceFolder}/system/wiki/Zolinga Core/Paths and Zolinga URI.md`
* Merged configurations inside module's zolinga.json and config/global.json, config/local.json files are exposed as `$api->config[key1][key2]...` see: `${workspaceFolder}/system/wiki/templates/Config Event.md`
* When creating and firing events from the code update also `emits` sectin in appropriate `zolinga.json` file.

# Translations

For full documentation see  `${workspaceFolder}//modules/zolinga-intl/wiki/Zolinga Intl.md`

* Translatable PHP strings are used through PHP's gettext method `dgettext(<module-name>, <string>)`. `<module-name>` is the module folder name.
* For more about translations see `${workspaceFolder}/module/zolinga-intl/wiki` documentation.
* Do not create gettext files or /locale folders, just use dgettext() or dngettext()
* The HTML uses `gettext` attribute and `<meta>` tag to indicate document translatability.

# Testing And Running the Code

* You can run the system by running `bin/zolinga` command. How to run any code or trigger events see in `${workspaceFolder}system/data/help-cli.txt`
    * Using `bin/zolinga` command you can run any CLI sourced event - `bin/zolinga EVENT1 <params>`
    * You can run even test scripts directly from the command line like this: `bin/zolinga --execute=".../my-script.php"` or `bin/zolinga --eval="echo 'Hello World';"`. 
    * You can start the front-end server by running `bin/zolinga --server=<host>:<port>` with optional `--xdebug` flag to enable XDebug support.

# Coding Style

You are an expert PHP developer. 

* Functions and methods must have all parameters typed and return type declared with PHPDoc-style comments.
* If suitable add simple usage examples in PHPDoc comments. 
* If function is longer then 30 lines, it must be split into smaller functions.
* Use `declare(strict_types=1);` at the top of the file.
* Generate PHP 8.4-compatible code.
* Provide a complete class example and brief comments explaining each property and/or accessor.
* Use `camelCase` for all variables and method names.
* Use `PascalCase` for all class names. 
* Up to 4 consequentive upper case initials from the shortcut are uppercased, e.g. `XMLHttpRequest` or `HTTPClient` or `getURL()`.
* Use `kebab-case` (also known as `lisp-case`) for all file names, e.g. `my-class.php`, `my-module.json`, `my-event.md`. 
    * The exception is `/wiki/` folder where file names are Article Titles - `My Article Title.md`.