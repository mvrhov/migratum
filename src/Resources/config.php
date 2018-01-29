<?php

return function (\Migratum\Config\Config $config) {
    $e = $config->createEnvironment('default');
    $config->setDefault($e);

    $e->addPath('path/to/migrations/dir');

    $e->setDatabaseDriver(\Migratum\Driver\Postgresql::class);

    $e->setDriverOptions(
        new \Migratum\Config\DriverOptions('hostname', 'database', 'username', 'password', 5432)
    );

    $e->setMigrationParameters(
        [
            //version string prefixed with v
            //the parameters in migration can then be accessed directly
            'v20180129121429' => [
                'param1' => 'value1',
            ],
        ]
    );
};