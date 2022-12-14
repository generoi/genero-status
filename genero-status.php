<?php
/**
 * genero-status.php
 */

header('Cache-Control: max-age=0, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Content-Type: text/plain; charset=utf-8');

function exitWithError(string $message): void
{
  http_response_code(503);
  echo $message . PHP_EOL;
  exit;
}

if (! is_readable($bootstrap = __DIR__ . '/wp-config.php')) {
  exitWithError('Filesystem not found.');
}

// Minimally bootstrap WordPress
define('WP_INSTALLING', TRUE);
define('WP_SETUP_CONFIG', TRUE);
define('SHORTINIT', TRUE);
include_once $bootstrap;

// Optionally authenticate the HTTP request
if (defined('STATUS_API_KEY') && STATUS_API_KEY) {
  $user = $_SERVER['PHP_AUTH_USER'] ?? null;
  $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
  if (STATUS_API_KEY !== "$user:$pass") {
    header('WWW-Authenticate: Basic realm="genero"');
    http_response_code(401);
    echo 'Not allowed' . PHP_EOL;
    exit;
  }
}

// Validate WordPress environment variable
if (defined('WP_ENV') && WP_ENV !== 'production') {
  exitWithError(sprintf('In "%s" mode', WP_ENV));
}

// Validate database connection
if (! $wpdb->db_connect(false)) {
  exitWithError('Database is down');
}

/** @var WP_Object_Cache $wp_object_cache */
global $wp_object_cache;

// Validate redis-cache plugin can connect to redis
if (method_exists($wp_object_cache, 'redis_status') && ! $wp_object_cache->redis_status()) {
  exitWithError('Redis cant connect');
}
// Validate wp-redis plugin can connect to redis
if (isset($wp_object_cache->is_redis_connected) && ! $wp_object_cache->is_redis_connected) {
  exitWithError('Redis cant connect');
}

// Validate there's no "noindex" tag on the frontpage
$content = file_get_contents(WP_HOME);
if (preg_match('~<meta[^>]+robots[^>]+noindex~', $content) !== 0) {
  exitWithError('Meta robots has noindex');
}

// Validate there's no Disallow all in the robots.txt
$content = file_get_contents(rtrim(WP_HOME, '/') . '/robots.txt');
if (preg_match('~Disallow:\h*/(?:\R|$)~i', $content) !== 0) {
  exitWithError('robots.txt disallow /');
}

// Everything passed.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK' . PHP_EOL;
