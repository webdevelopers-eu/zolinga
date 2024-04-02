<?php
// You can use this file to debug PHPUnits and other things.
//
// <phpunit 
//     bootstrap="system/loader_xdebug.php"
//     ...
// </phpunit>
if (extension_loaded('xdebug') && is_callable('xdebug_connect_to_client')) {
    xdebug_connect_to_client();
}
require(__DIR__ . '/loader.php');
