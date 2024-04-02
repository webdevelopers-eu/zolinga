This directory may contain two important files:

1. global.json:
    - Overwrites the default "config" sections in {MODULE}/zolinga.json files.

2. local.json:
    - Overwrites the default "config" sections in {MODULE/zoinga.json} files and global.json.
    - This file is meant to contain configurations that pertain to this particular machine installation.
    - This file is not supposed to be synced or deployed to other machines.
    - Rule of thumb: If you have configurations that are the same on all machines, they should go in global.json.
      If you have special configurations specific to this machine (e.g., database access, debug settings),
      they should go in local.json, and this file should never be copied to other machines.

The ArrayObject service "config" contains merged configurations in following order:

    all zolinga.json's config sections from {modules/*,system,vendor/*}/zolinga.json
    +
    config/global.json
    +
    config/local.json

Example:

    echo $GLOBALS['api']->config['ecs']['currency'];
