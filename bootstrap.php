<?php

require 'vendor/autoload.php';

phpunit_init();
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);


/**
 * Initializes the environment.
 *
 * Shamelessly copied from scripts/run-tests.sh
 *
 */
function phpunit_init($args = [])
{
    $host = 'localhost';
    $path = '';

    // Determine location of php command automatically, unless a command line argument is supplied.
    if (!empty($args['php'])) {
        $php = $args['php'];
    } elseif ($php_env = getenv('_')) {
        // '_' is an environment variable set by the shell. It contains the command that was executed.
        $php = $php_env;
    } elseif ($sudo = getenv('SUDO_COMMAND')) {
        // 'SUDO_COMMAND' is an environment variable set by the sudo program.
        // Extract only the PHP interpreter, not the rest of the command.
        list($php, ) = explode(' ', $sudo, 2);
    } else {
        echo 'Unable to automatically determine the path to the PHP interpreter. Supply the --php command line argument.';
        exit();
    }

    // Get URL from arguments.
    if (!empty($args['url'])) {
        $parsed_url = parse_url($args['url']);
        $host = $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    
        // If the passed URL schema is 'https' then setup the $_SERVER variables
        // properly so that testing will run under HTTPS.
        if ($parsed_url['scheme'] == 'https') {
            $_SERVER['HTTPS'] = 'on';
        }
    }

    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
//    $_SERVER['SERVER_SOFTWARE'] = $server_software;
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['REQUEST_URI'] = $path .'/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = $path .'/index.php';
    $_SERVER['PHP_SELF'] = $path .'/index.php';
    $_SERVER['HTTP_USER_AGENT'] = 'Drupal command line';

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
        // Ensure that any and all environment variables are changed to https://.
        foreach ($_SERVER as $key => $value) {
            $_SERVER[$key] = str_replace('http://', 'https://', $_SERVER[$key]);
        }
    }

    // Ensure that the script runs from the Drupal root directory.
    if ($drupal_root = phpunit_find_drupal_root()) {
        define('DRUPAL_ROOT', $drupal_root);
        chdir($drupal_root);
        require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    } else {
        echo "Unable to determine the Drupal root in order to bootstrap.";
    }
}


/**
 * Attempts to find the Drupal root directory.
 *
 * @param string $dir
 *   The directory to start at. If none provided, assumes the current directory.
 *
 * @return string
 *   The drupal root directory, or FALSE if one cannot be found.
 *
 */
function phpunit_find_drupal_root($dir = NULL)
{
    if (!$dir) {
        $dir = getcwd();
    }

    $candidate = implode(DIRECTORY_SEPARATOR, [$dir, 'includes', 'bootstrap.inc']);

    if ($dir == DIRECTORY_SEPARATOR) {
        return FALSE;
    } elseif (is_file($candidate)) {
        return $dir;
    } else {
        $new_dir = realpath(implode(DIRECTORY_SEPARATOR, [$dir, '..']));
        return phpunit_find_drupal_root($new_dir);
    }
}