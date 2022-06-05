<?php return [
    'routes:generate' => '\\App\\Commands\\Autorouter@generate',
    
    'migrate' => '\\App\\Commands\\Migrations@run',
    'migration:create' => '\\App\\Commands\\Migrations@create',
    'migration:rollback' => '\\App\\Commands\\Migrations@rollback',
    'facade:create' => '\\App\\Commands\\Facade@create',
    'facades:generate' => '\\App\\Commands\\Facade@generate',
];
