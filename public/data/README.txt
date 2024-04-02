This folder contains PUBLIC module data that are supposed to be accessible directly 
by the browser.

Each module has its own subfolder that is accessible through file name schema

    public://{MODULE}/...

E.g. 

    file_put_contents("public://mymodule/images/logo.png", $data);

