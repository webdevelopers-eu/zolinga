# Basics

* All modules are stored in `${workspaceFolder}/modulesp/` directory.
    * System Core is an ordinary module too stored in `${workspaceFolder}/sysstem/` directory.
* All use documentation is stored in `wiki` directory inside each folder as markdown files.
* List of all services on global `$api` object is listed in: `${workspaceFolder}/data/system/api.stub.php`
* Each module caries `zolinga.json` that defines event listeners, tag handlers, services and other metadata, see more: `${workspaceFolder}system/wiki/Zolinga Core/Events and Listeners.md`
    * All joined `zolinga.json` files are stored in `${workspaceFolder}/data/system/system.cache.json` file.

# Testing And Running the Code

* You can run the system by running `bin/zolinga` command. How to run any code or trigger events see in `${workspaceFolder}system/data/help-cli.txt`
