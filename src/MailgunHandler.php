<?php

namespace Drupal\mailgun;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Mailgun\Mailgun;
use Mailgun\Exception;

/**
 * Mail handler to send out an email message array to the Mailgun API.
 */
class MailgunHandler {

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $mailgunConfig;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Mailgun client.
   *
   * @var \Mailgun\Mailgun
   */
  protected $mailgun;

  /**
   * Constructs a new \Drupal\mailgun\MailHandler object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->mailgunConfig = $configFactory->get(MAILGUN_CONFIG_NAME);
    $this->logger = $logger;
    $this->mailgun = Mailgun::create($this->mailgunConfig->get('api_key'));
  }

  /**
   * Connects to Mailgun API and sends out the email.
   *
   * @param array $mailgun_message
   *   A message array, as described in
   *   https://documentation.mailgun.com/en/latest/api-sending.html#sending.
   *
   * @return bool
   *   TRUE if the mail was successfully accepted by the API, FALSE otherwise.
   *
   * @see https://documentation.mailgun.com/en/latest/api-sending.html#sending
   */
  public function sendMail(array $mailgun_message) {
    try {
      if (self::checkApiSettings() === FALSE) {
        $this->logger->error('Failed to send message from %from to %to. Please check the Mailgun settings.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
          ]
        );
        return FALSE;
      }

      $response = $this->mailgun->messages()->send($this->getDomain(), $mailgun_message);

      // Debug mode: log all messages.
      if ($this->mailgunConfig->get('debug_mode')) {
        $this->logger->notice('Successfully sent message from %from to %to. %id %message.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
            '%id' => $response->getId(),
            '%message' => $response->getMessage(),
          ]
        );
      }
      return TRUE;
    }
    catch (Exception $e) {
      $this->logger->error('Exception occurred while trying to send test email from %from to %to. @code: @message.',
        [
          '%from' => $mailgun_message['from'],
          '%to' => $mailgun_message['to'],
          '@code' => $e->getCode(),
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
  }

  /**
   * Get domains list from API.
   */
  public function getDomains() {
    $domains = [];
    try {
      $result = $this->mailgun->domains()->index();
      foreach ($result->getDomains() as $domain) {
        $domains[$domain->getName()] = $domain->getName();
      }
    }
    catch (Exception $e) {
      $this->logger->error('Could not retrieve domains from Mailgun API. @code: @message.', [
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
      ]);
    }

    return $domains;
  }

  /**
   * Get working domain for the message.
   */
  private function getDomain() {
    return $this->mailgunConfig->get('working_domain');
  }

  /**
   * Check Mailgun library and API settings.
   */
  public static function status($show_message = FALSE) {
    return self::checkLibrary($show_message) && self::checkApiSettings($show_message);
  }

  /**
   * Check that Mailgun PHP SDK is installed correctly.
   */
  public static function checkLibrary($show_message = FALSE) {
    $library_status = class_exists('\Mailgun\Mailgun');
    if ($show_message === FALSE) {
      return $library_status;
    }

    if ($library_status === FALSE) {
      drupal_set_message(t('The Mailgun library has not been installed correctly.'), 'warning');
    }
    return $library_status;
  }

  /**
   * Check if API settings are correct and not empty.
   */
  public static function checkApiSettings($show_message = FALSE) {
    $mailgun_settings = \Drupal::config(MAILGUN_CONFIG_NAME);
    $api_key = $mailgun_settings->get('api_key');
    $working_domain = $mailgun_settings->get('working_domain');

    if (empty($api_key) || empty($working_domain)) {
      if ($show_message) {
        drupal_set_message(t("Please check your API settings. API key and domain shouldn't be empty."), 'warning');
      }
      return FALSE;
    }

    if (self::validateKey($api_key) === FALSE) {
      if ($show_message) {
        drupal_set_message(t("Couldn't connect to the Mailgun API. Please check your API settings."), 'warning');
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates Mailgun API key.
   */
  public static function validateKey($key) {
    if (self::checkLibrary() === FALSE) {
      return FALSE;
    }
    $mailgun = Mailgun::create($key);

    try {
      $mailgun->domains()->index();
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

}