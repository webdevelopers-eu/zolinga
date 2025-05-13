# Basics

* All modules are stored in `${workspaceFolder}/modulesp/` directory.
    * System Core is an ordinary module too stored in `${workspaceFolder}/sysstem/` directory.
* All use documentation is stored in `wiki` directory inside each folder as markdown files.
* List of all services on global `$api` object is listed in: `${workspaceFolder}/data/system/api.stub.php`
* Each module caries `zolinga.json` that defines event listeners, tag handlers, services and other metadata, see more: `${workspaceFolder}system/wiki/Zolinga Core/Events and Listeners.md`
    * All joined `zolinga.json` files are stored in `${workspaceFolder}/data/system/system.cache.json` file.
* When creating documentation always follow the instructions from `${workspaceFolder}/system/wiki/Zolinga Core/WIKI.md` and all MD must be placed in appropriate module's `wiki` folder or for the core in `${workspaceFolder}/system/wiki/Zolinga Core/` folder.
* Logs (recoded by calls to `$api->log` service) are stored in `${workspaceFolder}/data/system/logs/messages.log` 

# Testing And Running the Code

* You can run the system by running `bin/zolinga` command. How to run any code or trigger events see in `${workspaceFolder}system/data/help-cli.txt`
    * Using `bin/zolinga` command you can run any event, run arbitrary PHP script or inline PHP code and also start inbuilt PHP web server to run the frontend.
