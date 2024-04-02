# Folder Structure

This is the folder structure of a module:

```
📁 {module} // folder name is used as module name
│   
├── 📁 wiki
│   ├── 📁 ...
│   └── 📄 ...
│
├── 📁 install
│   ├── 📁 install  ▶ executed during first run
│   │   └── 📄 {number}-{name}.{extension}
│   │
│   ├── 📁 update   ▶ executed when zolinga.json changes
│   │   └── 📄 {number}-{name}.{extension} 
│   │
│   ├── 📁 private  ▶ copied to private://{module}/ during installation
│   │   ├── 📁 ... 
│   │   └── 📄 ...
│   │
│   ├── 📁 public   ▶ copied to public://{module}/ during installation
│   │   ├── 📁 ...
│   │   └── 📄 ... 
│   │
│   └── 📁 dist     ▶ symlinked too ./public/dist/{module}
│       ├── 📁 ...
│       └── 📄 ... 
│   
├── 📁 src          ▶ your source code
│   ├── 📁 ...
│   └── 📄 ... 
│
├── 📁 ...          ▶ anything else you may need
├── 📄 ... 
└── 📄 zolinga.json
```

- Each module is a directory inside the `modules` directory.
- Each module directory contains a [zolinga.json](:Zolinga Core:Manifest File) manifest file that describes the module. Namely it contains the script autoload rules and a list of events that the module listens to.
- Each module should contain a [documentation folder `wiki`](:Zolinga Core:WIKI) that contains the module's documentation in Markdown format.
- Each module may contain a `install` folder with following subfolders:
    - `install`  
        > contains the installation scripts that are executed when the module is installed for the first time. For more information refer to [Installation and Updates](:Zolinga Core:Module Installation and Updates)
    - `update`
        > contains the update scripts that get executed when the module is updated. For more information refer to [Installation and Updates](:Zolinga Core:Module Installation and Updates)
    - `private`
        > contains the default files that will be _copied_ during installation to the _read-write_ file data storage `./data/MODULE/` inaccessible from the web server. No `.php` files are allowed in this folder. 
    - `public`
        > contains the default files that will be _copied_ during installation to the _read-write_ file data storage `./public/data/MODULE` (`https://example.com/data/MODULE`) accessible from the web server. No `.php` files are allowed in this folder.
    - `dist`
        > this _read-only_ folder will be _symlinked_ to the public folder `public/dist/MODULE` (`https://example.com/dist/MODULE`) so that the files are accessible from the web server. It may contain `.php` files that are executed on HTTP request.
- The optional `wiki` folder is meant to house the documentation for your module. This folder will be automatically used by inbuilt [Zolinga WIKI](:Zolinga Core:WIKI).

## Example
The example of the simple 'hello-world' module with more advanced "install" folder demonstration is as follows:

```
📁 hello-world
├── 📁 wiki
│   └── 📄 ...
├── 📁 install
│   ├── 📁 install
│   │   └── 📄 010-create-users.php
│   ├── 📁 update
│   │   ├── 📄 020-update-users.php
│   │   └── 📄 030-update-locations.php  
│   ├── 📁 private
│   │   ├── 📁 uploads
│   │   └── 📄 template.html
│   ├── 📁 public
│   │   ├── 📁 cache
│   │   └── 📄 customizable-bg.png 
│   └── 📁 dist
│       ├── 📄 logo.png
│       └── 📄 soap.php
├── 📁 src
│   └── 📄 Server.php
└── 📄 zolinga.json
```

