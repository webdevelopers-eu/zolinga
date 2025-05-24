Priority: 0.65

# Paths and Zolinga URI

Your module has 5 important paths that you should know about:

- **Module Path** - The path to your module. It is where your module is located: `./modules/{module}`.
    - read-only
- **Private Data Path** - This path is used for private files that should not be accessible from the web: `./data/{module}`.
    - read-write
    - the initial contents of this folder are copied from the `./modules/{module}/install/private` folder
    - No `.php` files are allowed in this folder.
- **Public Data Path** - This path is used for public files that should be accessible from the web: `./public/data/{module}`.
    - read-write
    - the initial contents of this folder are copied from the `./modules/{module}/install/public` folder
    - No `.php` files are allowed in this folder.
- **Distribution Path** - This path is used for files that are distributed with your module and should be accessible from the web: `./public/dist/{module}`.
    - read-only
    - this folder is **symlinked** to the `./modules/{module}/dist` folder
- **Config Path** - This path is used for environment-specific configuration files: `./config/{module}`.
    - read-write
    - used to override module defaults without modifying module code
    - No `.php` files are allowed in this folder.

Your module will never need any other folders to write data then your module's private and public data folders. You should never write data anywhere else.

# Zolinga URI

The Zolinga URI is a way to access those paths from PHP in simple way. It is a URI string in format `{type}://{module}/{path}`.

Supported URIs:

- `module://{module}/{path}` - Module Path
- `private://{module}/{path}` - Private Data Path
- `public://{module}/{path}` - Public Data Path
- `dist://{module}/{path}` - Distribution Path
- `wiki://{module}/{path}` - Path to a wiki page inside `./modules/{module}/wiki` folder
- `config://{module}/{path}` - Configuration files in `./config/{module}` folder

You can use these paths transparently in all your PHP code. For example, to get the path to a file in your module, you can use the following code:

```php
$contents = file_get_contents('module://my-module/data.json');

if (!is_dir('private://my-module/storage')) {
    mkdir('private://my-module/storage', 0777, true);
}
file_put_contents('private://my-module/storage/data.json', json_encode($data));

// Load configuration from config directory
$config = json_decode(file_get_contents('config://my-module/settings.json'), true);
```

# FS Service

The `$api->fs` is a service that allows you to convert paths between system paths and Zolinga URIs.

## Configuration Scheme

The `config://` scheme provides access to module-specific configuration files stored in the `./config/{module}/` directory. This is particularly useful for:

- **Environment-specific configurations** - Override default module settings
- **Custom configurations** - Store user-defined settings separately from module defaults
- **External configurations** - Keep sensitive or environment-specific data outside the module

Example usage:
```php
// Check if custom config exists
if (file_exists('config://my-module/database.json')) {
    $dbConfig = json_decode(file_get_contents('config://my-module/database.json'), true);
} else {
    // Fall back to default configuration
    $dbConfig = $defaultConfig;
}


```

Configuration files in the config directory take precedence over module defaults and can be used to customize module behavior without modifying the module code itself.


