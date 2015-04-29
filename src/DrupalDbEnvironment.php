<?php

namespace Drupal\PhpUnit;

use Database;
use PDO;


/**
 * Helper class to prepare the database environment.
 *
 * Most of this functionality is shamelessly stolen from
 * modules/simpletest/drupal_web_test_case.php
 *
 */
class DrupalDbEnvironment extends AbstractEnvironment
{

    protected $setupEnvironment = false;
    protected $prefix = null;
    protected $profile = 'standard';


    /**
     * Prepares the database for testing.
     *
     */
    public function setUp()
    {
        global $conf;

        $conf['install_profile'] = $this->profile;

        include_once DRUPAL_ROOT . '/includes/install.inc';

        $this->cleanUpDb();
        $this->prepareEnvironment();
        $this->changeDbState();

        drupal_install_system();

        $this->preloadRegistry();
        $this->enableProfileModules();
        $this->resetCaches();
        $this->finishSetup();
    }


    /**
     * Removes test tables from the active database.
     *
     */
    protected function cleanUpDb()
    {
        $this->logger->info('Cleaning up database tables from previous test runs...');

        $info   = Database::getConnectionInfo('default');
        $prefix = $info['default']['prefix']['default'];
        $length = strlen($prefix);

        $tables = db_find_tables('phpunit_%');

        foreach($tables as $table) {
            // If we're cleaning up a previous run, we likely don't need to
            // account for a prefix.
            $table_to_drop = $prefix ? substr($table, $length) : $table;

            if (db_drop_table($table_to_drop)) {
                $this->logger->debug('Dropping table :table', [':table' => $table]);
            } else {
                $this->logger->warning("Couldn't drop $table");
            }
        }
    }


    /**
     * Sets up the test environment.
     *
     */
    protected function prepareEnvironment()
    {
        if ($this->setupEnvironment) {
            return;
        }

        global $user, $language, $conf;

        $this->logger->info('Setting up the new test environment...');

        // Store necessary current values before switching to prefixed database.
        $this->originalLanguage = $language;
        $this->originalLanguageDefault = variable_get('language_default');
        $this->originalProfile = drupal_get_profile();
        $this->originalCleanUrl = variable_get('clean_url', 0);
        $this->originalUser = $user;

        // Set to English to prevent exceptions from utf8_truncate() from t()
        // during install if the current language is not 'en'.
        // The following array/object conversion is copied from language_default().
        $language = (object) array('language' => 'en', 'name' => 'English', 'native' => 'English', 'direction' => 0, 'enabled' => 1, 'plurals' => 0, 'formula' => '', 'domain' => '', 'prefix' => '', 'weight' => 0, 'javascript' => '');

        // Save and clean the shutdown callbacks array because it is static cached
        // and will be changed by the test run. Otherwise it will contain callbacks
        // from both environments and the testing environment will try to call the
        // handlers defined by the original one.
        $callbacks = &drupal_register_shutdown_function();
        $this->originalShutdownCallbacks = $callbacks;
        $callbacks = array();

        // Create test directory ahead of installation so fatal errors and debug
        // information can be logged during installation process.
        // Use temporary files directory with the same prefix as the database.
        $this->public_files_directory = $this->getOriginalFileDirectory() . '/phpunit/' . $this->getPrefix();
        $this->private_files_directory = $this->public_files_directory . '/private';
        $this->temp_files_directory = $this->private_files_directory . '/temp';

        // Create the directories
        file_prepare_directory($this->public_files_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        file_prepare_directory($this->private_files_directory, FILE_CREATE_DIRECTORY);
        file_prepare_directory($this->temp_files_directory, FILE_CREATE_DIRECTORY);


        // Indicate the environment was set up correctly.
        $this->setupEnvironment = TRUE;
    }


    /**
     * Pre-load the registry from the testing site.
     *
     * This method is called by DrupalWebTestCase::setUp(), and pre-loads the
     * registry from the testing site to cut down on the time it takes to
     * set up a clean environment for the current test run.
     */
    protected function preloadRegistry()
    {
        $this->logger->info('Pre-loading the registry from the existing install...');

        // Use two separate queries, each with their own connections: copy the
        // {registry} and {registry_file} tables over from the parent installation
        // to the child installation.
        $original_connection = Database::getConnection('default', 'phpunit_original_default');
        $test_connection = Database::getConnection();

        foreach (array('registry', 'registry_file') as $table) {
            // Find the records from the parent database.
            $source_query = $original_connection
                ->select($table, array(), array('fetch' => PDO::FETCH_ASSOC))
                ->fields($table);
            $dest_query = $test_connection->insert($table);

            $first = TRUE;
            foreach ($source_query->execute() as $row) {
                if ($first) {
                    $dest_query->fields(array_keys($row));
                    $first = FALSE;
                }
                // Insert the records into the child database.
                $dest_query->values($row);
            }

            $dest_query->execute();
        }
    }


    /**
     * Sets various enironment variables.
     *
     */
    protected function enableProfileModules()
    {
        $this->logger->info('Enabling modules from the install profile...');

        // Set path variables.
        variable_set('file_public_path', $this->public_files_directory);
        variable_set('file_private_path', $this->private_files_directory);
        variable_set('file_temporary_path', $this->temp_files_directory);

        // Include the testing profile.
        variable_set('install_profile', $this->profile);
        $profile_details = install_profile_info($this->profile, 'en');

        // Install the modules specified by the testing profile.
        module_enable($profile_details['dependencies'], FALSE);

        // Run the profile tasks.
        $install_profile_module_exists = db_query("SELECT 1 FROM {system} WHERE type = 'module' AND name = :name", array(
          ':name' => $this->profile,
        ))->fetchField();
        if ($install_profile_module_exists) {
          module_enable(array($this->profile), FALSE);
        }
    }


    /**
     * Refreshes all caches
     *
     */
    protected function resetCaches()
    {
        $this->logger->info('Resetting caches...');

        // Reset all static variables.
        drupal_static_reset();
        // Reset the list of enabled modules.
        module_list(TRUE);

        // Reset cached schema for new database prefix. This must be done before
        // drupal_flush_all_caches() so rebuilds can make use of the schema of
        // modules enabled on the cURL side.
        drupal_get_schema(NULL, TRUE);

        // Perform rebuilds and flush remaining caches.
        drupal_flush_all_caches();

        // Reload global $conf array and permissions.
        $this->refreshVariables();
//        $this->checkPermissions(array(), TRUE);
    }


    /**
     * Refresh the in-memory set of variables. Useful after a page request is made
     * that changes a variable in a different thread.
     *
     * In other words calling a settings page with $this->drupalPost() with a changed
     * value would update a variable to reflect that change, but in the thread that
     * made the call (thread running the test) the changed variable would not be
     * picked up.
     *
     * This method clears the variables cache and loads a fresh copy from the database
     * to ensure that the most up-to-date set of variables is loaded.
     */
    protected function refreshVariables() {
        global $conf;
        cache_clear_all('variables', 'cache_bootstrap');
        $conf = variable_initialize();
    }


    /**
     * Finish the setup
     *
     */
    protected function finishSetup()
    {
        global $user, $conf, $language;

        $this->logger->info('Finishing setup...');

        // Run cron once in that environment, as install.php does at the end of
        // the installation process.
        drupal_cron_run();

        // Ensure that the session is not written to the new environment and replace
        // the global $user session with uid 1 from the new test site.
        drupal_save_session(FALSE);
        // Login as uid 1.
        $user = user_load(1);

        // Restore necessary variables.
        variable_set('install_task', 'done');
        variable_set('clean_url', $this->originalCleanUrl);
        variable_set('site_mail', 'phpunit@example.com');
        variable_set('date_default_timezone', date_default_timezone_get());

        // Set up English language.
        unset($conf['language_default']);
        $language = language_default();

        // Use the test mail class instead of the default mail handler class.
        variable_set('mail_system', array('default-system' => 'TestingMailSystem'));
    }


    /**
     * Restores the original environment
     *
     */
    public function tearDown()
    {
        global $user, $language, $conf;

        $this->logger->info('Tearing down the test environment...');

        /*
        $emailCount = count(variable_get('drupal_test_email_collector', array()));
        if ($emailCount) {
            $message = format_plural($emailCount, '1 e-mail was sent during this test.', '@count e-mails were sent during this test.');
            $this->pass($message, t('E-mail'));
        }
        */

        // Delete temporary files directory.
        file_unmanaged_delete_recursive($this->getOriginalFileDirectory() . '/phpunit/' . $this->getPrefix());

        // Remove tests tables.
        $this->cleanUpDb();

        // Get back to the original connection.
        $this->restoreDbState();

        // Restore original shutdown callbacks array to prevent original
        // environment of calling handlers from test run.
        $callbacks = &drupal_register_shutdown_function();
        $callbacks = $this->originalShutdownCallbacks;

        // Return the user to the original one.
        $user = $this->originalUser;
        drupal_save_session(TRUE);

        // Ensure that internal logged in variable and cURL options are reset.
        $this->loggedInUser = FALSE;
        $this->additionalCurlOptions = array();

        // Reload module list and implementations to ensure that test module hooks
        // aren't called after tests.
        module_list(TRUE);
        module_implements('', FALSE, TRUE);

        // Reset the Field API.
        field_cache_clear();

        // Rebuild caches.
        $this->refreshVariables();

        // Reset public files directory.
        $conf['file_public_path'] = $this->getOriginalFileDirectory();

        // Reset language.
        $language = $this->originalLanguage;
        if ($this->originalLanguageDefault) {
            $GLOBALS['conf']['language_default'] = $this->originalLanguageDefault;
        }
    }
}