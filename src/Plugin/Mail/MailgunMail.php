<?php

namespace Drupal\mailgun\Plugin\Mail;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Html2Text\Html2Text;
use Drupal\Component\Utility\Html;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modify the Drupal mail system to use Mandrill when sending emails.
 *
 * @Mail(
 *   id = "mailgun_mail",
 *   label = @Translation("Mailgun mailer"),
 *   description = @Translation("Sends the message using Mailgun.")
 * )
 */
class MailgunMail implements MailInterface, ContainerFactoryPluginInterface {

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $drupalConfig;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Mailgun constructor.
   */
  function __construct(ImmutableConfig $settings, LoggerInterface $logger, RendererInterface $renderer) {
    $this->drupalConfig = $settings;
    $this->logger = $logger;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('config.factory')->get('mailgun.adminsettings'),
      $container->get('logger.factory')->get('mailgun'),
      $container->get('renderer')
    );
  }

  /**
   * Concatenate and wrap the e-mail body for either plain-text or HTML e-mails.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }

    if ($this->drupalConfig->get('use_theme')) {
      $render = [
        '#theme' => isset($message['params']['theme']) ? $message['params']['theme'] : 'mailgun',
        '#message' => $message,
      ];
      $message['body'] = $this->renderer->renderRoot($render);

      $converter = new Html2Text($message['body']);
      $message['plain'] = $converter->getText();
    }

    // If text format is specified in settings, run the message through it.
    $format = $this->drupalConfig->get('format_filter');

    if (!empty($format)) {
      $message['body'] = check_markup($message['body'], $format, $message['langcode']);
    }

    return $message;
  }

  /**
   * Send the e-mail message.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *   $message['params'] may contain additional parameters. See mailgun_send().
   *
   * @return bool
   *   TRUE if the mail was successfully accepted or queued, FALSE otherwise.
   *
   * @see drupal_mail()
   * @see https://documentation.mailgun.com/api-sending.html#sending
   */
  public function mail(array $message) {
    // Build the Mailgun message array.
    $mailgun_message = [
      'from' => $message['from'],
      'to' => $message['to'],
      'subject' => $message['subject'],
      'text' => Html::escape($message['body']),
      'html' => $message['body'],
    ];

    if (isset($message['plain'])) {
      $mailgun_message['text'] = $message['plain'];
    }
    else {
      $converter = new Html2Text($message['body']);
      $mailgun_message['text'] = $converter->getText();
    }

    // Add the CC and BCC fields if not empty.
    if (!empty($message['params']['cc'])) {
      $mailgun_message['cc'] = $message['params']['cc'];
    }
    if (!empty($message['params']['bcc'])) {
      $mailgun_message['bcc'] = $message['params']['bcc'];
    }

    // Support CC / BCC provided by webform module.
    if (!empty($message['params']['cc_mail'])) {
      $mailgun_message['cc'] = $message['params']['cc_mail'];
    }
    if (!empty($message['params']['bcc_mail'])) {
      $mailgun_message['bcc'] = $message['params']['bcc_mail'];
    }

    // For a full list of allowed parameters,
    // see: https://documentation.mailgun.com/api-sending.html#sending.
    $allowed_params = [
      'o:tag',
      'o:campaign',
      'o:deliverytime',
      'o:dkim',
      'o:testmode',
      'o:tracking',
      'o:tracking-clicks',
      'o:tracking-opens',
    ];

    foreach ($message['params'] as $key => $value) {
      // Check if it's one of the known parameters.
      $allowed = (in_array($key, $allowed_params)) ? TRUE : FALSE;

      if ($allowed) {
        $mailgun_message[$key] = $value;
      }
      // Check for custom MIME headers or custom JSON data.
      if (substr($key, 0, 2) == 'h:' || substr($key, 0, 2) == 'v:') {
        $mailgun_message[$key] = $value;
      }
    }

    // Make sure the files provided in the attachments array exist.
    if (!empty($message['params']['attachments'])) {
      $attachments = [];
      foreach ($message['params']['attachments'] as $attachment) {
        if (file_exists($attachment)) {
          $attachments[] = $attachment;
        }
      }

      if (count($attachments) > 0) {
        $mailgun_message['attachments'] = $attachments;
      }
    }

    if ($this->checkTracking($message)) {
      $track_opens = $this->drupalConfig->get('tracking_opens');
      if (!empty($track_opens)) {
        $mailgun_message['o:tracking-opens'] = $track_opens;
      }
      $track_clicks = $this->drupalConfig->get('tracking_clicks');
      if (!empty($track_clicks)) {
        $mailgun_message['o:tracking-clicks'] = $track_opens;
      }
    }
    else {
      $mailgun_message['o:tracking'] = 'no';
    }

    if ($this->drupalConfig->get('use_queue')) {
      /** @var \Drupal\Core\Queue\QueueFactory $queue_factory */
      // TODO: Use injections.
      $queue_factory = \Drupal::service('queue');

      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = $queue_factory->get('mailgun_send_mail');

      $item = new \stdClass();
      $item->message = $mailgun_message;
      $queue->createItem($item);

      // Debug mode: log all messages.
      if ($this->drupalConfig->get('debug_mode')) {
        $this->logger->notice('Successfully queued message from %from to %to.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
          ]
        );
      }
      return TRUE;
    }

    // TODO: We can inject our service here.
    return \Drupal::service('mailgun.mail_handler')->sendMail($mailgun_message);
  }

  /**
   * Checks, if the mail key is excempted from tracking.
   *
   * @param array $message
   *   A message array.
   *
   * @return bool
   *   TRUE if the tracking is allowed, otherwise FALSE.
   */
  protected function checkTracking(array $message) {
    $tracking = TRUE;
    $tracking_exception = $this->drupalConfig->get('tracking_exception');
    if (!empty($tracking_exception)) {
      $tracking = !in_array($message['module'] . ':' . $message['key'], explode("\n", $tracking_exception));
    }
    return $tracking;
  }

}
