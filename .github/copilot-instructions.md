# Basics

* All modules are stored in `${workspaceFolder}/modulesp/` directory.
    * System Core is an ordinary module too stored in `${workspaceFolder}/system/` directory.
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
    * `global.json` and other config files in `config/{module-name}/...` folder (accessible through `config://{module-name}/...` Zolinga URI) are global per-project configuration shared between development and production environments 
    * `local.json` is a local configuration with settings valid only for this particular environment.
    * Modules may choose if to put things into `global.json` or `local.json` files. Both files are loaded at runtime and merged and available through `$api->config`. Bigger/complex/non-JSON config files should go to separate `config://{module-name}/...` files and modules should handle them independently.   
* When creating and firing events from the code update also `emits` sectin in appropriate `zolinga.json` file.
* Do not use dependency injection logic - no need for it it is PHP, e.g. for accessing `$api` services use `global $api` and then use `$api->serviceName` 
* The module structure is as follows: `${workspaceFolder}/system/wiki/Zolinga Core/Module Anatomy.md`
   * It also explains where to put PHP scripts and other code that needs to be accessible from the web.
* The System caches all zolinga.json files. If the contents changes it gets automatically reloaded. So bump up minor version of the module in `zolinga.json` file to trigger the reload.
* Database is usually accessed through `$api->db` with `query` and `queryExpand` methods. DB installation scripts are stored in `${workspaceFolder}/modules/{module-name}/install/install/*.sql` - see `Module Installation and Updates.md`
* Note that modules `install/dist` folder is automatically symlinked to public folder `${workspaceFolder}/public/dist/{module-name}` and can be accessed through `https://example.com/dist/{module-name}/...` URL.

# Web Components

* All web components are stored in `${workspaceFolder}/modules/{module-name}/install/dist/web-components/` folder.
* For whole documentation see `${workspaceFolder}/system/wiki/Zolinga Core/Web Components.md`
* When creating a web component, do not forget to update `zolinga.json` in respective module folder - this replaces the need for `customElements.define()` call - system does it.
* The web components documentation is stored in the same file as main `.js` component but has `.md` extension, e.g. `${workspaceFolder}/modules/{module-name}/install/dist/web-components/my-component/my-component.md`

# Translations

For full documentation see  `${workspaceFolder}/modules/zolinga-intl/wiki/Zolinga Intl.md`

* Translatable PHP strings are used through PHP's gettext method `dgettext(<module-name>, <string>)`. `<module-name>` is the module folder name.
* For more about translations see `${workspaceFolder}/modules/zolinga-intl/wiki` documentation.
* Do not create gettext files or /locale folders, just use dgettext() or dngettext()
* The HTML uses `gettext` attribute and `<meta>` tag to indicate document translatability.

# Testing And Running the Code

* You can run the system by running `bin/zolinga` command. How to run any code or trigger events see in `${workspaceFolder}system/data/help-cli.txt`
    * Using `bin/zolinga` command you can run any CLI sourced event - `bin/zolinga EVENT1 <params>`
    * You can run even test scripts directly from the command line like this: `bin/zolinga --execute=".../my-script.php"` or `bin/zolinga --eval="echo 'Hello World';"`. 
    * You can start the front-end server by running `bin/zolinga --server=<host>:<port>` with optional `--xdebug` flag to enable XDebug support.
* Accessing the web front-end is done through `http://{hostname}/...`
    * if the front-end is run using `bin/zolinga --server=localhost:8080` then the hostname is `localhost` and the port is `8080`.
    * if it runs through existing webserver the URL address is in `config/local.json` (higher priority) or `config/global.json` (lower) `baseURL` property.  

# Coding Style

You are an expert PHP developer. 

* All PHP code should go to the `${module}/src/` folder.
* Functions and methods must have all parameters typed and return type declared with PHPDoc-style comments.
* If suitable add simple usage examples in PHPDoc comments. 
* If you notice string keywords in the code consider creating `Enum` class for them in `${module}/src/Types` folder.
* Classes representing DB objects should go to `${module}/src/Model` folder.
* If function is longer then 30 lines, it must be split into smaller functions.
* Use `declare(strict_types=1);` at the top of the file.
* Generate PHP 8.4-compatible code.
* Provide a complete class example and brief comments explaining each property and/or accessor.
* Use `camelCase` for all variables and method names.
* Use `PascalCase` for all class names. 
* Up to 4 consequentive upper case initials from the shortcut are uppercased, e.g. `XMLHttpRequest` or `HTTPClient` or `getURL()`.
* Use `kebab-case` (also known as `lisp-case`) for all file names, e.g. `my-class.php`, `my-module.json`, `my-event.md`. 
    * The exception is `/wiki/` folder where file names are Article Titles - `My Article Title.md`.
* The `zolinga.json` is documented in `${workspaceFolder}/system/wiki/Zolinga Core/Manifest File.md` and must follow the rules described there.
    * In need refer to other `zolinga.json` files in `${workspaceFolder}/modules/*/zolinga.json` files.

# Services

* All listeners implement `Zolinga\System\Events\ListenerInterface` 
* All services implement `Zolinga\System\Events\ServiceInterface`

# Custom API Endpoints

* They go to `${module}/install/dist/...` folder and can be accessed through `https://example.com/dist/{module}/...`
* How to structure it, load the system - refer to default Zolinga API endpoint `${workspaceFolder}/system/install/dist/gate/index.php`

