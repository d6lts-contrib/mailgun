<?php 

namespace Drupal\mailgun\Plugin\QueueWorker;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\mailgun\MailgunMailHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base functionality for the SendMail Queue Workers.
 */
class SendMailBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * MailGun config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $mailgunConfig;

  /**
   * MailGun Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * MailGun mail handler.
   *
   * @var \Drupal\mailgun\MailgunMailHandler
   */
  protected $mailgunHandler;

  /**
   * SendMailBase constructor.
   *
   * @param \Drupal\mailgun\MailgunMailHandler $mailgunHandler
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $settings, LoggerInterface $logger, MailgunMailHandler $mailgunHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailgunConfig = $settings;
    $this->logger = $logger;
    $this->mailgunHandler = $mailgunHandler;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('mailgun.adminsettings'),
      $container->get('logger.factory')->get('mailgun'),
      $container->get('mailgun.mail_handler')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $result = $this->mailgunHandler->sendMail($data->message);

    if ($this->mailgunConfig->get('debug_mode')) {
      $this->logger->notice('Successfully sent message on CRON from %from to %to.',
        [
          '%from' => $data->message['from'],
          '%to' => $data->message['to'],
        ]
      );
    }

    if (!$result) {
      throw new RequeueException('Mailgun: email did not pass through API.');
    }
  }

}
