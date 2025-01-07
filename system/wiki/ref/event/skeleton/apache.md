Usage example:
```bash
# Generate example Apache configuration file and put it into /etc/apache2/sites-available
$ bin/zolinga skeleton:apache --serverName=zolinga.local --ip=127.0.0.1 > /etc/apache2/sites-available/010-zolinga.conf

# Enable the newly created configuration file
$ a2ensite 010-zolinga.conf

# Restart Apache
$ systemctl restart apache2
```

Supported parameters:

- `serverName` - ServerName directive value
- `ip` - IP address to listen on
- `port` - Port to listen on
- `documentRoot` - DocumentRoot directive value
- `errorLogPrefix` - log files will be named as `${errorLogPrefix}-error.log` and `${errorLogPrefix}-access.log`



