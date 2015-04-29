<?php

namespace Drupal\PhpUnit;

use Database;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Drupal\PhpUnit\Log\PrintLogger;


/**
 * Abstract class representing a Drupal environment in a test state.
 *
 */
abstract class AbstractEnvironment
{
    use LoggerAwareTrait;

    /**
     * The original file directory
     *
     * @var string
     *
     */
    protected $file_dir;


    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     *
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if (!$logger) {
            $logger = new PrintLogger();
        }
        $this->logger = $logger;
    }


    /**
     * Sets up the environment.
     *
     */
    abstract public function setUp();


    /**
     * Tears down this environment.
     *
     */
    abstract public function tearDown();


    /**
     * Returns the prefix for this test run.
     *
     * @return string
     *
     */
    protected function getPrefix()
    {
        if (!$this->prefix) {
            $this->prefix = 'phpunit_' . mt_rand(1000, 1000000);
        }

        return $this->prefix;
    }


    /**
     * Returns the original file directory.
     *
     * @return string
     *
     */
    protected function getOriginalFileDirectory()
    {
        if (!$this->file_dir) {
            $this->file_dir = variable_get('file_public_path', conf_path() . '/files');
        }

        return $this->file_dir;
    }


    /**
     * Switches the database to use the test prefix.
     *
     */
    protected function changeDbState()
    {
        $this->logger->info('Switching to the test database...');

        // Clone the current connection and replace the current prefix.
        $connection_info = Database::getConnectionInfo('default');

        Database::renameConnection('default', 'phpunit_original_default');
        foreach ($connection_info as $target => $value) {
            $connection_info[$target]['prefix'] = array(
                'default' => $value['prefix']['default'] . $this->getPrefix() . '_',
            );
        }
        Database::addConnectionInfo('default', 'default', $connection_info['default']);
    }


    /**
     * Restores the database to the original state.
     *
     */
    protected function restoreDbState()
    {
        $this->logger->info('Restoring the original database state...');

        Database::removeConnection('default');
        Database::renameConnection('phpunit_original_default', 'default');
    }
}