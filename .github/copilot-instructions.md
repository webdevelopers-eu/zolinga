# Basics

* All modules are stored in `${workspaceFolder}/modulesp/` directory.
    * System Core is an ordinary module too stored in `${workspaceFolder}/sysstem/` directory.
* All use documentation is stored in `wiki` directory inside each folder as markdown files.
* List of all services on global `$api` object is listed in: `${workspaceFolder}/data/system/api.stub.php`
* Each module caries `zolinga.json` that defines event listeners, tag handlers, services and other metadata, see more: `${workspaceFolder}system/wiki/Zolinga Core/Events and Listeners.md`
    * All joined `zolinga.json` files are stored in `${workspaceFolder}/data/system/system.cache.json` file.
* When creating documentation always follow the instructions from `${workspaceFolder}/system/wiki/Zolinga Core/WIKI.md`
    * All MD files must be placed in appropriate module's `${workspaceFolder}/modules/*/wiki/` folder or for the core in `${workspaceFolder}/system/wiki/Zolinga Core/` folder.
* Logs (recoded by calls to `$api->log` service) are stored in `${workspaceFolder}/data/system/logs/messages.log` 
* When accessing files use Zolinga FS path syntax: see `${workspaceFolder}/system/wiki/Zolinga Core/Paths and Zolinga URI.md`

# Testing And Running the Code

* You can run the system by running `bin/zolinga` command. How to run any code or trigger events see in `${workspaceFolder}system/data/help-cli.txt`
    * Using `bin/zolinga` command you can run any event, run arbitrary PHP script or inline PHP code and also start inbuilt PHP web server to run the frontend.


# Coding Style

You are an expert PHP developer. 

* Generate PHP 8.4-compatible code
* Use property accessors for all public properties. For each property if needed:
    1. Declare a private backing field for storage.
    2. Declare a public property with get and set blocks.
    3. In get block, apply any read-time logic using the backing field.
    4. In set block, apply any write-time logic using the backing field.
* Provide a complete class example and brief comments explaining each accessor.
* Private properties are not prefixed with an underscore.
* Use `camelCase` for all variables and method names.
* Use `PascalCase` for all class names.