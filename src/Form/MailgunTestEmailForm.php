<?php

namespace Drupal\mailgun\Form;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mailgun\MailgunMailHandler;

/**
 * Class MailgunTestEmailForm.
 *
 * @package Drupal\mailgun\Form
 */
class MailgunTestEmailForm extends FormBase {

  /**
   * Drupal\mailgun\MailgunMailHandler definition.
   *
   * @var \Drupal\mailgun\MailgunMailHandler
   */
  protected $mailgunMailHandler;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * Mail Manager.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * MailgunTestEmailForm constructor.
   */
  public function __construct(MailgunMailHandler $mailgun_mail_handler, AccountProxyInterface $user, MailManager $mailManager, FileSystem $fileSystem) {
    $this->mailgunMailHandler = $mailgun_mail_handler;
    $this->user = $user;
    $this->mailManager = $mailManager;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mailgun.mail_handler'),
      $container->get('current_user'),
      $container->get('plugin.manager.mail'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailgun_test_email_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // TODO: Show a warning message if API is not configured.
    // TODO: Show current mail system to make sure that Mailgun is enabled.
    // But we can test all mail systems with this form.
    $form['to'] = [
      '#type' => 'email',
      '#title' => $this->t('To'),
      '#required' => TRUE,
      '#description' => $this->t('Email will be sent to this address.'),
      '#default_value' => $this->user->getEmail(),
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#default_value' => $this->t('Howdy!

If this e-mail is displayed correctly and delivered sound and safe, congrats! You have successfully configured Mailgun.'),
    ];

    $form['include_attachment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include attachment'),
      '#description' => $this->t('If checked, an image will be included as an attachment with the test e-mail.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('mailgun.admin_settings_form'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $to = $form_state->getValue('to');

    $params = [
      'subject' => $this->t('Mailgun is working!'),
      'body' => $form_state->getValue('body'),
    ];

    if (!empty($form_state->getValue('include_attachment'))) {
      $params['params']['attachments'][] = $this->fileSystem->realpath('core/misc/druplicon.png');
    }

    // TODO: Reply-To parameter can be added to form.
    // Other parameters (cc, bcc, etc.) can be added for testing as well.
    $result = $this->mailManager->mail('mailgun', 'test_form_email', $to, $this->user->getPreferredLangcode(), $params, NULL, TRUE);

    if ($result['result'] === TRUE) {
      drupal_set_message(t('Successfully sent message to %to.', ['%to' => $to]));
    }
    else {
      drupal_set_message(t('Something went wrong. Please check @logs for details.', [
        '@logs' => Link::createFromRoute($this->t('logs'), 'dblog.overview')->toString(),
      ]));
    }
  }

}
