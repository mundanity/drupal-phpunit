<?php

namespace Drupal\PhpUnit;


class DrupalDbTest extends \PHPUnit_Framework_TestCase
{

    public function setUp() {
        $this->env = new DrupalDbEnvironment();
        $this->env->setUp();
    }


    public function tearDown() {
        $this->env->tearDown();
    }

}