<?php

namespace Drupal\mailgun_mailing_lists\Controller;

use Drupal\Core\Controller\ControllerBase;
use Mailgun\Mailgun;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Mailgun\Exception\HttpClientException;

/**
 * Class MailingList.
 *
 * @package Drupal\mailgun_mailing_lists\Controller
 */
class MailingList extends ControllerBase {

  /**
   * Mailgun handler.
   *
   * @var \Mailgun\Mailgun
   */
  protected $mailgunClient;
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mailgun.mailgun_client'),
      $container->get('logger.factory')->get('mailgun')
    );
  }

  /**
   * {@inheritdoc}
   * @param \Mailgun\Mailgun $mailgun_client
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(Mailgun $mailgun_client, LoggerInterface $logger) {
    $this->mailgunClient = $mailgun_client;
    $this->logger = $logger;
  }

  /**
   * Return list of the members.
   *
   * @param string $list_address
   *   Mailgun list address.
   *
   * @return array
   *   Page build array
   */
  public function members($list_address) {
    try {
      $rows = [];
      throw new HttpClientException('test', 400);
      $members = $this->mailgunClient->mailingList()
        ->member()
        ->index($list_address)
        ->getItems();
      if (!empty($members)) {

        foreach ($members as $member) {
          $rows[] = [
            'address' => $member->getAddress(),
            'name' => $member->getName(),
            'subscribed' => $member->isSubscribed() ? $this->t('Yes') : $this->t('No'),
          ];
        }
        return [
          '#theme' => 'table',
          '#rows' => $rows,
          '#header' => [
            $this->t('Address'),
            $this->t('Name'),
            $this->t('Subscribed'),
          ],
        ];
      }
      else {
        return [
          '#markup' => $this->t('No subscribers yet.'),
        ];
      }
    }
    catch (HttpClientException $e) {
      $this->logger->error('Error getting the members list : %api_error', ['%api_error' => $e->getMessage()]);
      return [
        '#markup' => $this->t('Error getting the members list. Check the Error log'),
      ];
    }
  }
}
