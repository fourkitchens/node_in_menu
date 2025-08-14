<?php

namespace Drupal\node_in_menu\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters nodes by whether they are referenced in a menu.
 *
 * @ViewsFilter("node_in_menu")
 */
class NodeInMenu extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a NodeInMenu filter plugin.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $menu = $this->value;
    $this->query->addWhereExpression(
      $this->options['group'],
      "EXISTS (
        SELECT 1 FROM {menu_link_content_data} mlc
        LEFT JOIN {path_alias} ua ON ua.path = CONCAT('/node/', node_field_data.nid)
        WHERE (
          mlc.link__uri = CONCAT('internal:/node/', node_field_data.nid)
          OR mlc.link__uri = CONCAT('entity:node/', node_field_data.nid)
          OR (ua.alias IS NOT NULL AND mlc.link__uri = CONCAT('internal:', ua.alias))
        )
        AND mlc.menu_name = :menu_name
        AND mlc.enabled = 1
      )",
      [':menu_name' => $menu]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => $this->getMenuOptions(),
      '#default_value' => $this->value,
    ];
  }

  /**
   * Returns an array of available menus.
   *
   * @return array
   *   An array of menu machine names => labels.
   */
  protected function getMenuOptions() {
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $options = [];
    foreach ($menus as $menu) {
      $options[$menu->id()] = $menu->label();
    }
    return $options;
  }

}
