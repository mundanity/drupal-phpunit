<?php

namespace Drupal\PhpUnit;


class DrupalEnvironment extends AbstractEnvironment
{

    /**
     * {@inheritdoc}
     *
     */
    public function setUp()
    {
        global $conf;

        // Reset all statics so that test is performed with a clean environment.
        drupal_static_reset();

        // Create test directory.
        $public_files_directory = $this->getOriginalFileDirectory() . '/phpunit/' . $this->getPrefix();
        file_prepare_directory($public_files_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        $conf['file_public_path'] = $public_files_directory;

        $this->changeDbState();
        $this->disableModules();
    }


    /**
     * {@inheritdoc}
     *
     */
    public function tearDown()
    {
        global $conf;

        $this->restoreDbState();
        $conf['file_public_path'] = $this->getOriginalFileDirectory();

        // Restore modules if necessary.
        if (isset($this->originalModuleList)) {
            module_list(TRUE, FALSE, FALSE, $this->originalModuleList);
        }
    }


    /**
     * Disable locale, if necessary.
     *
     */
    protected function disableModules()
    {
        // If locale is enabled then t() will try to access the database and
        // subsequently will fail as the database is not accessible.
        $module_list = module_list();
        if (isset($module_list['locale'])) {
            // Transform the list into the format expected as input to module_list().
            foreach ($module_list as &$module) {
                $module = array('filename' => drupal_get_filename('module', $module));
            }
            $this->originalModuleList = $module_list;
            unset($module_list['locale']);
            module_list(TRUE, FALSE, FALSE, $module_list);
        }
    }

}