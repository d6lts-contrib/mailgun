<?php

namespace Drupal\Tests\mailgun\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base test class for Mailgun functional tests.
 */
abstract class MailgunFunctionalTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['mailgun'];

  /**
   * Permissions required by the user to perform the tests.
   *
   * @var array
   */
  protected $permissions = [
    'administer mailgun',
  ];

}
