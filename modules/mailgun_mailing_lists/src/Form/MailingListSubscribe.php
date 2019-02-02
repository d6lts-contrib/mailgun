<?php

namespace Drupal\mailgun_mailing_lists\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Mailgun\Exception\HttpClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mailgun\Mailgun;

/**
 * Class MailingListSubscribe.
 */
class MailingListSubscribe extends FormBase {

  /**
   * Mailgun\Mailgun definition.
   *
   * @var \Mailgun\Mailgun
   */
  protected $mailgunMailgunClient;

  /**
   * String definition.
   *
   * @var \Mailgun\Mailgun
   */
  protected $listAddress;

  /**
   * Constructs a new MailingListSubscribe object.
   */
  public function __construct(Mailgun $mailgun_mailgun_client, $list_address = NULL) {
    $this->mailgunMailgunClient = $mailgun_mailgun_client;
    $this->listAddress = $list_address;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mailgun.mailgun_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailing_list_subscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $email = $form_state->getValue('email');
    try {
      if ($this->mailgunMailgunClient->mailingList()->member()->show($this->listAddress, $email)) {
        $form_state->setErrorByName('name', $this->t("You are already subscribed to this list."));
      }
    }
    catch (HttpClientException $e) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    try {
      $this->mailgunMailgunClient->mailingList()->member()->create($this->listAddress, $email);
      $this->messenger()->addMessage($this->t("You've successfully subscribed."));
    }
    catch (HttpClientException $e) {
      $this->messenger()->addMessage($this->t("Error occurred. Please try again later."));
    }

  }

}
