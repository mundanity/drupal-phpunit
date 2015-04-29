<?php

namespace Drupal\PhpUnit;


class DrupalUnitTest extends \PHPUnit_Framework_TestCase
{

    public function setUp() {
        $this->env = new DrupalEnvironment();
        $this->env->setUp();
    }


    public function tearDown() {
        $this->env->tearDown();
    }

}