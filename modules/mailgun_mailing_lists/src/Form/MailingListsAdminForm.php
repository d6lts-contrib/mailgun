<?php

namespace Drupal\mailgun_mailing_lists\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
    ];
    $form['create_new_list']['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#description' => $this->t('Enter short description'),
    ];
    $form['create_new_list']['access_level'] = [
      '#title' => $this->t('Access Level'),
      '#type' => 'select',
      '#description' => $this->t('Access level for a list'),
      '#options' => [
        'readonly' => $this->t('Read Only'),
        'members' => $this->t('Members'),
        'everyone' => $this->t('Everyone'),
      ],
      '#defaul_value' => 'readonly',
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
          'access_level' => $list->getAccessLevel(),
          'subscribers' => [
            'data' => [
              '#title' => $this->t('Members'),
              '#type' => 'link',
              '#url' => Url::fromRoute('mailgun_mailing_lists.list', ['list_address' => $list->getAddress()]),
            ],
          ],
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
          $this->t('Access Level'),
          $this->t('Subscribers'),
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
    $description = $form_state->getValue('description');
    $description = $description ? $description : $name;
    try {
      $lists->create($address, $name, $description, $form_state->getValue('access_level'));
      $this->messenger()->addMessage($this->t('List @name was successfully created', ['@name' => $name]));
    }
    catch (HttpClientException $e) {
      $this->messenger()->addMessage($this->t('Error during creation of the list'), 'error');
    }
  }

}
