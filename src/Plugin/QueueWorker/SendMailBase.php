<?php 

namespace Drupal\mailgun\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;

/**
 * Provides base functionality for the SendMail Queue Workers.
 */
class SendMailBase extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $result = \Drupal::service('mailgun.mail_handler')->sendMail($data->message);

    if (\Drupal::config('mailgun.adminsettings')->get('debug_mode')) {

      \Drupal::logger('mailgun')->notice('Successfully sent message on CRON from %from to %to.',
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
