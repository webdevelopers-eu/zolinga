Minimal usage: `bin/zolinga skeleton:apache --serverName=example.com`

Complete example:
```bash
# Generate example Apache configuration file and put it into /etc/apache2/sites-available
$ bin/zolinga skeleton:apache --serverName=example.com --ip=1.252.23.256 > /etc/apache2/sites-available/010-zolinga.conf

# Enable the newly created configuration file
$ a2ensite 010-zolinga.conf

# Restart Apache
$ systemctl restart apache2
```

Supported parameters:

- `serverName` - Required. ServerName directive value
- `ip` - Default: `*`. IP address to listen on.
- `port` - Default: `80`. Port to listen on
- `documentRoot` - Default: `/var/www/${serverName}/public`. The public document root directory.
- `errorLogPrefix` - Default: `${APACHE_LOG_DIRECTORY}/${serverName}` log files will be named as `${errorLogPrefix}-error.log` and `${errorLogPrefix}-access.log`

Run `bin/zolinga skeleton:apache --help` to see the full list of supported parameters.


