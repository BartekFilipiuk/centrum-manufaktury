<?php
/**
 * @file
 * Platform.sh settings.
 */

// Configure the database.
if (isset($_ENV['PLATFORM_RELATIONSHIPS'])) {
  $relationships = json_decode(base64_decode($_ENV['PLATFORM_RELATIONSHIPS']), TRUE);

  if (empty($databases['default']['default']) && !empty($relationships['database'])) {
    foreach ($relationships['database'] as $endpoint) {
      $database = [
        'driver' => $endpoint['scheme'],
        'database' => $endpoint['path'],
        'username' => $endpoint['username'],
        'password' => $endpoint['password'],
        'host' => $endpoint['host'],
        'port' => $endpoint['port'],
      ];

      if (!empty($endpoint['query']['compression'])) {
        $database['pdo'][PDO::MYSQL_ATTR_COMPRESS] = TRUE;
      }

      if (!empty($endpoint['query']['is_master'])) {
        $databases['default']['default'] = $database;
      }
      else {
        $databases['default']['replica'][] = $database;
      }
    }
  }

  if (!empty($relationships['applicationcache'][0]) && !drupal_installation_attempted() && extension_loaded('redis')) {
    $redis = $relationships['applicationcache'][0];

    // Set Redis as the default backend for any cache bin not otherwise specified.
    $settings['cache']['default'] = 'cache.backend.redis';
    $settings['redis.connection']['host'] = $redis['host'];
    $settings['redis.connection']['port'] = $redis['port'];

    // Apply changes to the container configuration to better leverage Redis.
    // This includes using Redis for the lock and flood control systems, as well
    // as the cache tag checksum. Alternatively, copy the contents of that file
    // to your project-specific services.yml file, modify as appropriate, and
    // remove this line.
    $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

    // Allow the services to work before the Redis module itself is enabled.
    $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

    // Manually add the classloader path, this is required for the container cache bin definition below
    // and allows to use it without the redis module being enabled.
    $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

    // Use redis for container cache.
    // The container cache is used to load the container definition itself, and
    // thus any configuration stored in the container itself is not available
    // yet. These lines force the container cache to use Redis rather than the
    // default SQL cache.
    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.phpserialize' => [
          'class' => 'Drupal\Component\Serialization\PhpSerialize',
        ],
      ],
    ];
  }
}

if (isset($_ENV['PLATFORM_APP_DIR'])) {

  // Configure private and temporary file paths.
  if (!isset($settings['file_private_path'])) {
    $settings['file_private_path'] = $_ENV['PLATFORM_APP_DIR'] . '/private/' . $_ENV['PLATFORM_SITE'];
  }
  if (!isset($config['system.file']['path']['temporary'])) {
    $config['system.file']['path']['temporary'] = $_ENV['PLATFORM_APP_DIR'] . '/tmp/' . $_ENV['PLATFORM_SITE'];
  }

  // Configure the default PhpStorage and Twig template cache directories.
  if (!isset($settings['php_storage']['default'])) {
    $settings['php_storage']['default']['directory'] = $settings['file_private_path'];
  }
  if (!isset($settings['php_storage']['twig'])) {
    $settings['php_storage']['twig']['directory'] = $settings['file_private_path'];
  }

}

// Set trusted hosts based on Platform.sh routes.
if (isset($_ENV['PLATFORM_ROUTES']) && !isset($settings['trusted_host_patterns'])) {
  $routes = json_decode(base64_decode($_ENV['PLATFORM_ROUTES']), TRUE);
  $settings['trusted_host_patterns'] = [];
  foreach ($routes as $url => $route) {
    $host = parse_url($url, PHP_URL_HOST);
    if ($host !== FALSE && $route['type'] == 'upstream' && $route['upstream'] == $_ENV['PLATFORM_APPLICATION_NAME']) {
      $settings['trusted_host_patterns'][] = '^' . preg_quote($host) . '$';
    }
  }
  $settings['trusted_host_patterns'] = array_unique($settings['trusted_host_patterns']);
}

// Import variables prefixed with 'd8settings:' into $settings and 'd8config:'
// into $config.
// Currently used settings and config:
// - d8settings:ga_code
// - d8settings:ensighten
// - d8settings:f1_endpoint_nl
// - d8settings:f1_endpoint_fr
//if (isset($_ENV['PLATFORM_VARIABLES'])) {
//  $variables = json_decode(base64_decode($_ENV['PLATFORM_VARIABLES']), TRUE);
//  foreach ($variables as $name => $value) {
//    // A variable named "d8settings:example-setting" will be saved in
//    // $settings['example-setting'].
//    if (strpos($name, 'd8settings:') === 0) {
//      $settings[substr($name, 11)] = $value;
//    }
//    // A variable named "drupal:example-setting" will be saved in
//    // $settings['example-setting'] (backwards compatibility).
//    elseif (strpos($name, 'drupal:') === 0) {
//      $settings[substr($name, 7)] = $value;
//    }
//    // A variable named "d8config:example-name:example-key" will be saved in
//    // $config['example-name']['example-key'].
//    elseif (strpos($name, 'd8config:') === 0 && substr_count($name, ':') >= 2) {
//      list(, $config_key, $config_name) = explode(':', $name, 3);
//      $config[$config_key][$config_name] = $value;
//    }
//    // A complex variable named "d8config:example-name" will be saved in
//    // $config['example-name'].
//    elseif (strpos($name, 'd8config:') === 0 && is_array($value)) {
//      $config[substr($name, 9)] = $value;
//    }
//  }
//}

$settings['config_sync_directory'] = '../config/sync';

// Set the project-specific entropy value, used for generating one-time
// keys and such.
if (isset($_ENV['PLATFORM_PROJECT_ENTROPY']) && empty($settings['hash_salt'])) {
  $settings['hash_salt'] = $_ENV['PLATFORM_PROJECT_ENTROPY'];
}

// Set the deployment identifier, which is used by some Drupal cache systems.
if (getenv('PLATFORM_TREE_ID') && empty($settings['deployment_identifier'])) {
  $settings['deployment_identifier'] = getenv('PLATFORM_TREE_ID');
}
