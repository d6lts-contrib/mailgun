<?php

namespace Drupal\mailgun\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
    $link = \Drupal::l($this->t('mailgun.com/app/domains'), $url);

    $form['description'] =
      [
        '#markup' => "Please refer to $link for your settings.",
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

    $form['api_endpoint'] = [
      '#title' => $this->t('Mailgun API Endpoint'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API endpoint.'),
      '#default_value' => $config->get('api_endpoint'),
    ];

    $form['api_version'] = [
      '#title' => t('Mailgun API Version'),
      '#type' => 'textfield',
      '#description' => t('Enter Mailgun API version.'),
      '#default_value' => $config->get('api_version'),
      '#size' => 1,
    ];

    $form['debug_mode'] = [
      '#title' => $this->t('Enable Debug Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('debug_mode'),
    ];

    return parent::buildForm($form, $form_state);
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

    $this->config('mailgun.adminsettings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_endpoint', $form_state->getValue('api_endpoint'))
      ->set('api_version', $form_state->getValue('api_version'))
      ->set('working_domain', $form_state->getValue('working_domain'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();

    drupal_set_message($this->t('The configuration options have been saved.'));
  }

}
