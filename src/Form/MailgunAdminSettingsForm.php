<?php

namespace Drupal\mailgun\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class MailgunAdminSettingsForm.
 *
 * @package Drupal\mailgun\Form
 */
class MailgunAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mailgun.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailgun_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mailgun.adminsettings');

    $url = Url::fromUri('https://mailgun.com/app/domains');
    $link = Link::fromTextAndUrl('mailgun.com/app/domains', $url);

    $form['description'] = [
      '#markup' => $this->t('Please refer to @link for your settings.', [
        '@link' => $link->toString(),
      ]),
    ];

    $form['api_key'] = [
      '#title' => $this->t('Mailgun API Key'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API key.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['working_domain'] = [
      '#title' => $this->t('Mailgun API Working Domain'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API working domain.'),
      '#default_value' => $config->get('working_domain'),
    ];

    $form['debug_mode'] = [
      '#title' => $this->t('Enable Debug Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('debug_mode'),
      '#description' => $this->t('Enable to log every email and queuing.'),
    ];

    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $url = Url::fromUri('https://documentation.mailgun.com/en/latest/user_manual.html#tracking-opens');
    $link = Link::fromTextAndUrl('https://documentation.mailgun.com/en/latest/user_manual.html#tracking-opens', $url);
    $form['advanced_settings']['tracking_opens'] = [
      '#title' => $this->t('Enable Track Opens'),
      '#type' => 'select',
      '#options' => [
        '' => $this->t('Use domain setting'),
        'no' => $this->t('No'),
        'yes' => $this->t('Yes'),
      ],
      '#default_value' => $config->get('tracking_opens'),
      '#description' => $this->t('Enable to track the opening of an email. See: @link', ['@link' => $link->toString()]),
    ];
    $url = Url::fromUri('https://documentation.mailgun.com/en/latest/user_manual.html#tracking-clicks');
    $link = Link::fromTextAndUrl('https://documentation.mailgun.com/en/latest/user_manual.html#tracking-clicks', $url);
    $form['advanced_settings']['tracking_clicks'] = [
      '#title' => $this->t('Enable Track Clicks'),
      '#type' => 'select',
      '#options' => [
        '' => $this->t('Use domain setting'),
        'no' => $this->t('No'),
        'yes' => $this->t('Yes'),
        'htmlonly' => $this->t('HTML only'),
      ],
      '#default_value' => $config->get('tracking_clicks'),
      '#description' => $this->t('Enable to track the clicks of within an email. See: @link', ['@link' => $link->toString()]),
    ];
    $form['advanced_settings']['tracking_exception'] = [
      '#title' => $this->t('Do not track the following mails'),
      '#type' => 'textarea',
      '#default_value' => $config->get('tracking_exception'),
      '#description' => $this->t('Add all mail keys you want to except from tracking. One key per line. Format: module:key (e.g.: user:password_reset)'),
    ];
    $options = [
      '' => $this->t('None'),
    ];
    $filter_formats = filter_formats();
    foreach ($filter_formats as $filter_format_id => $filter_format) {
      $options[$filter_format_id] = $filter_format->label();
    }
    $form['advanced_settings']['format_filter'] = [
      '#title' => $this->t('Format filter'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $config->get('format_filter'),
      '#description' => $this->t('Format filter to use to render the message'),
    ];
    $form['advanced_settings']['use_theme'] = [
      '#title' => $this->t('Use theme'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('use_theme'),
      '#description' => $this->t('Enable to pass the message through a theme function. Default "mailgun" or pass one with $message["params"]["theme"]'),
    ];
    $form['advanced_settings']['use_queue'] = [
      '#title' => $this->t('Enable Queue'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('use_queue'),
      '#description' => $this->t('Enable to queue mails and send them out in background by cron'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mailgun.adminsettings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('working_domain', $form_state->getValue('working_domain'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('tracking_opens', $form_state->getValue('tracking_opens'))
      ->set('tracking_clicks', $form_state->getValue('tracking_clicks'))
      ->set('tracking_exception', $form_state->getValue('tracking_exception'))
      ->set('format_filter', $form_state->getValue('format_filter'))
      ->set('use_queue', $form_state->getValue('use_queue'))
      ->set('use_theme', $form_state->getValue('use_theme'))
      ->save();

    drupal_set_message($this->t('The configuration options have been saved.'));
  }

}
