<?php

namespace Drupal\mailgun_mailing_lists\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mailgun\Mailgun;
use Drupal\mailgun_mailing_lists\Form\MailingListSubscribe as MailingListSubscribeForm;

/**
 * Provides a 'MailingListSubscribe' block.
 *
 * @Block(
 *  id = "mailing_list_subscribe",
 *  admin_label = @Translation("Mailing list subscribe"),
 * )
 */
class MailingListSubscribe extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Mailgun handler.
   *
   * @var \Mailgun\Mailgun
   */
  protected $mailgunClient;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Mailgun\Mailgun $mailgun
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Mailgun $mailgun_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailgunClient = $mailgun_client;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $form = new MailingListSubscribeForm($this->mailgunClient, $config['mailing_list']);
    $build = \Drupal::formBuilder()->getForm($form);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $config = $this->getConfiguration();
    $lists = $this->mailgunClient->mailingList();
    $name = $lists->show($config['mailing_list'])->getList()->getName();
    return $this->t('Subscribe to @name', ['@name' => $name]);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $lists = $this->mailgunClient->mailingList()->pages()->getLists();
    $options = ['' => $this->t('Please Select')];
    foreach ((array) $lists as $list) {
      $options[$list->getAddress()] = $list->getName();
    }
    $form['mailing_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Mailing List'),
      '#options' => $options,
      '#default_value' => isset($config['mailing_list']) ? $config['mailing_list'] : '',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['mailing_list'] = $form_state->getValue('mailing_list');
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mailgun.mailgun_client')
    );
  }

}
