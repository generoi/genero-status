<?php
/**
 * genero-status.php
 * =================
 *
 * A script to output general health status of a Drupal or Wordpress website.
 *
 * This script is used in our Website overview spreadsheet to monitor the
 * status of our websites.
 *
 * Formula:
 * -------
 * =IF(
 *   AND(D12 = "x", G12 = "x"), -- The website is active and live
 *   IMPORTXML(
 *     CONCAT(CONCAT("http://", A12), "/genero-status.php?key=fAme9hS3Kduggk3F"), -- http://<domain>/genero.status.php?key=<key>
 *     "/status/version" -- xpath selector for which field to fetch
 *   ),
 *   "" -- empty output if the website isnt active or isnt live yet.
 * )
 *
 * Supported versions:
 * - Drupal 6, 7
 * - Wordpress 3.4 (at least)
 */
header('Content-Type: application/xml; charset=utf-8');

define('GENERO_STATUS_VERSION', '0.0.8');
define('GENERO_DEBUG', FALSE);
define('GENERO_KEY', 'fAme9hS3Kduggk3F');
define('GENERO_DRUPAL', 'Drupal');
define('GENERO_WORDPRESS', 'Wordpress');
define('GENERO_UNKNOWN', 'unknown');
define('GENERO_SCRIPT_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));

if (GENERO_DEBUG) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(0);
}

foreach (array('wp', 'web', 'app', 'wordpress') as $subdir) {
  // If there's an index file in any of these sub directories, use that as the
  // root instead.
  if (is_file(GENERO_SCRIPT_ROOT . '/' . $subdir . '/index.php')) {
    define('GENERO_ENV_ROOT', GENERO_SCRIPT_ROOT . '/' . $subdir);
    break;
  }
}
if (!defined('GENERO_ENV_ROOT')) {
  // Fallback to the directory where this script is located.
  define('GENERO_ENV_ROOT', GENERO_SCRIPT_ROOT);
}

if (empty($_GET['key']) || $_GET['key'] != GENERO_KEY) {
  exit;
}

function genero_get_platform() {
  if (file_exists(GENERO_ENV_ROOT . '/includes/bootstrap.inc')) {
    return GENERO_DRUPAL;
  }
  if (file_exists(GENERO_ENV_ROOT . '/wp-includes/version.php')) {
    return GENERO_WORDPRESS;
  }
  return GENERO_UNKNOWN;
}

function genero_drupal_variable_validate($variables) {
  foreach ($variables as $variable_name) {
    if (!variable_get($variable_name, 0)) {
      return FALSE;
    }
  }
  return TRUE;
}

function genero_gather_wordpress_data() {
  $data['version'] = $GLOBALS['wp_version'];
  $data['maintenance'] = file_exists(GENERO_ENV_ROOT . '/.maintenance') ? 'on' : 'off';
  $data['database'] = $GLOBALS['wpdb']->db_connect(FALSE) ? 'on' : 'off';
  return $data;
}

function genero_gather_drupal_data() {
  $data['version'] = VERSION;
  $data['maintenance'] = genero_drupal_variable_validate(array('maintenance_mode')) ? 'on' : 'off';
  $data['caching'] = genero_drupal_variable_validate(array('block_cache', 'cache', 'preprocess_css', 'preprocess_js', 'page_compression')) ? 'on' : 'off';
  try {
    // Drupal 7
    if (class_exists('Database')) {
      Database::getConnection();
    }
    $data['database'] = 'on';
  } catch (Exception $e) {
    // @todo figure out how to prevent Drupal 6 from spitting out an error
    // page.
    $data['database'] = 'off';
  }
  return $data;
}

switch (genero_get_platform()) {
  case GENERO_DRUPAL:
    define('DRUPAL_ROOT', GENERO_ENV_ROOT);
    include_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
    // Required by some caching backends
    include_once DRUPAL_ROOT . '/includes/lock.inc';
    // Version definition exists here on Drupal 6.
    include_once DRUPAL_ROOT . '/modules/system/system.module';
    $data = genero_gather_drupal_data();
    break;
  case GENERO_WORDPRESS:
    // Disable caching.
    define('WP_CACHE', FALSE);
    // Disable the regular bootstrap process, there's lots of exit calls which
    // we cant prevent.
    define('WP_INSTALLING', TRUE);
    // This will prevent a failed database connection from issuing an exit call.
    define('WP_SETUP_CONFIG', TRUE);
    // Bootstrap as little as possible.
    define('SHORTINT', TRUE);
    // Support bedrock as well as traditional structure
    if (is_file(GENERO_ENV_ROOT . '/wp-config.php')) {
      include_once GENERO_ENV_ROOT . '/wp-config.php';
    } else {
      include_once GENERO_SCRIPT_ROOT . '/wp-config.php';
    }
    $data = genero_gather_wordpress_data();
    break;
  default:
    $data = array('version' => 'unknown', 'maintenance' => 'unknown', 'database' => 'unknown');
}

$data['platform'] = genero_get_platform();
$data['php_version'] = PHP_VERSION;
$data['script_version'] = GENERO_STATUS_VERSION;

// Really? short-tags are not disabled?
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<status>
<?php foreach ($data as $key => $value): ?>
<<?php print $key; ?>><?php print $value; ?></<?php print $key; ?>>
<?php endforeach; ?>
</status>
<?php exit; ?>
