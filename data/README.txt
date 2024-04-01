This folder contains module data that must not be accessible directly 
by the browser (web root is in the "public" folder).

Each module has its own subfolder that is accessible through file name schema

    private://{MODULE}/...

The public URL maps to

    https://{YOUR_DOMAIN}/data/{MODULE}/...

E.g. 

    file_get_contents("private://mymodule/myconfig.json");
    echo "<a href='https://example.com/data/mymodule/myconfig.json'>config</a>";
