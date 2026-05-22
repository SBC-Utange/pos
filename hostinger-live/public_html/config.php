<?php

declare(strict_types=1);

$config = [
    'db_host' => getenv('SRM_DB_HOST') ?: '127.0.0.1',
    'db_user' => getenv('SRM_DB_USER') ?: 'root',
    'db_pass' => getenv('SRM_DB_PASS') ?: '',
    'db_name' => getenv('SRM_DB_NAME') ?: 'srm_db',
    'app_name' => 'Shakesbeard CYBER Sales Manager',
    'currency_symbol' => 'KES',
    'timezone' => 'Africa/Nairobi',
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
