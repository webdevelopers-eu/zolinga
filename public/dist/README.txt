This folder contains PUBLIC module resources that remain static.

Each module has its own subfolder accessible through the file name schema:

    dist://{MODULE}/...

The public URL corresponds to:

    https://{YOUR_DOMAIN}/dist/{MODULE}/...

IMPORTANT: The module folders are symbolic links pointing to your module's
           modules/{MODULE}/install/dist folder. Data in those folders
           is controlled by distribution and must not be modified dynamically.
           To store custom public data, use the "data" folder instead.

For example:

    file_get_contents("dist://mymodule/myconfig.json");
    echo "<a href='https://example.com/dist/mymodule/myconfig.json'>config</a>";
