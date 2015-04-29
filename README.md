# About

Provides PHPUnit based unit test cases for Drupal 7 installations. This essentially copies most of the functionality from the built-in SimpleTest.

# Configuration

In the module you wish to test, setup your composer file as follows:

```
    ...
    "require": {
        "mundanity/drupal-phpunit": "dev-master"
    },
    "repositories" [
        {
            "type": "vcs",
            "url": "https://github.com/mundanity/drupal-phpunit.git"
        }
    ]
    ...
```

Your PHPUnit config should look like this (assuming your tests are in "my_module/tests":

```
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="./vendor/bin/bootstrap.php">
    <testsuites>
        <testsuite name="Sample Tests">
            <directory>./tests</directory>
        </testsuite>
    </testsuites>
    <filter>
        <blacklist>
            <directory>./vendor</directory>
        </blacklist>
    </filter>
</phpunit>
```

# Usage

Your tests can use ```Drupal\PhpUnit\DrupalDbTest``` or ```Drupal\PhpUnit\DrupalUnitTest```. If using the latter, you have very limited access to Drupal functions (much like in simpletest), and will need to manually include / require any files you need (e.g. other .module files)

# Todo

- Lots of cleanup available
- This specifically avoids a lot of the web test functionality.
