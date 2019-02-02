<?php

namespace Drupal\mailgun_mailing_lists\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Mailgun\Mailgun;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mailgun\Exception\HttpClientException;

/**
 * Class MailingListsAdminForm.
 *
 * @package Drupal\mailgun_mailing_lists\Form
 */
class MailingListsAdminForm extends FormBase {

  /**
   * Mailgun handler.
   *
   * @var \Mailgun\Mailgun
   */
  protected $mailgunClient;

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
  public function __construct(Mailgun $mailgunClient) {
    $this->mailgunClient = $mailgunClient;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailgun_mailing_lists_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['create_new_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Create new list'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['create_new_list']['list_address'] = [
      '#title' => $this->t('List address'),
      '#type' => 'email',
      '#required' => TRUE,
      '#description' => $this->t('Enter the new list address'),
    ];
    $form['create_new_list']['list_name'] = [
      '#title' => $this->t('New list name'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#description' => $this->t('Enter the new list name'),
      '#default_value' => '',
    ];
    $form['create_new_list']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create new list'),
    ];

    $mailgun = $this->mailgunClient;
    $lists = $mailgun->mailingList()->pages()->getLists();
    $rows = [];
    if (!empty($lists)) {
      foreach ($lists as $list) {
        $rows[] = [
          'name' => $list->getName(),
          'address' => $list->getAddress(),
          'members' => $list->getMembersCount(),
          'description' => $list->getDescription(),
          'created' => $list->getCreatedAt()->format('d-m-Y H:i'),
        ];
      }
      $form['lists'] = [
        '#theme' => 'table',
        '#rows' => $rows,
        '#header' => [
          $this->t('Name'),
          $this->t('Address'),
          $this->t('members'),
          $this->t('Description'),
          $this->t('Created'),
        ],
      ];
    }
    else {
      $form['lists'] = [
        '#markup' => $this->t('No Lists Found.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $address = $form_state->getValue('list_address');
    $lists = $this->mailgunClient->mailingList();
    try {
      if ($lsit = $lists->show($address)) {
        $form_state->setErrorByName('list_address', $this->t('List with the same address exists'));
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
    $name = $form_state->getValue('list_name');
    $address = $form_state->getValue('list_address');
    $lists = $this->mailgunClient->mailingList();
    try {
      $lists->create($address, $name);
      $this->messenger()->addMessage($this->t('List @name was successfully created', ['@name' => $name]));
    }
    catch (HttpClientException $e) {
      $this->messenger()->addMessage($this->t('Error during creation of the list'), 'error');
    }
  }

}
