<?php

namespace Drupal\PhpUnit\Log;

use Psr\Log\AbstractLogger;


/**
 * Implementation of psr-3 logger, for printing to stdout.
 *
 */
class PrintLogger extends AbstractLogger
{
    /**
     * {@inheritdoc}
     *
     */
    public function log($level, $message, array $context = array())
    {
        $keys   = array_keys($context);
        $values = array_values($context);

        printf("%s: %s\n", strtoupper($level), str_replace($keys, $values, $message));
    }
}