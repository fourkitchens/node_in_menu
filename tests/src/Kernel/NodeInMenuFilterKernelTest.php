<?php

namespace Drupal\Tests\node_in_menu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use Drupal\views\Views;
use Drupal\Core\Form\FormState;
use Drupal\views\Entity\View;

/**
 * Kernel tests for the Node in Menu Views filter.
 *
 * @group node_in_menu
 */
class NodeInMenuFilterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'views',
    'menu_link_content',
    'path',
    'path_alias',
    'link',
    'language',
    'node_in_menu',
  ];

  /**
   * Node IDs created during setup.
   *
   * @var int[]
   */
  protected $nodeIds = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required entity schemas and configs.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'node', 'views', 'menu_link_content', 'path', 'path_alias', 'language']);

    // Create a basic content type.
    $type = NodeType::create(['type' => 'page', 'name' => 'Page']);
    $type->save();

    // Create menus.
    $main = Menu::create(['id' => 'main', 'label' => 'Main navigation']);
    $main->save();
    $footer = Menu::create(['id' => 'footer', 'label' => 'Footer']);
    $footer->save();

    // Create nodes.
    $n1 = Node::create(['type' => 'page', 'title' => 'Direct']);
    $n1->save();
    $this->nodeIds['n1'] = (int) $n1->id();

    $n2 = Node::create(['type' => 'page', 'title' => 'Entity']);
    $n2->save();
    $this->nodeIds['n2'] = (int) $n2->id();

    $n3 = Node::create(['type' => 'page', 'title' => 'Alias']);
    $n3->save();
    $this->nodeIds['n3'] = (int) $n3->id();

    $n4 = Node::create(['type' => 'page', 'title' => 'Disabled']);
    $n4->save();
    $this->nodeIds['n4'] = (int) $n4->id();

    $n5 = Node::create(['type' => 'page', 'title' => 'Other menu']);
    $n5->save();
    $this->nodeIds['n5'] = (int) $n5->id();

    $n6 = Node::create(['type' => 'page', 'title' => 'No link']);
    $n6->save();
    $this->nodeIds['n6'] = (int) $n6->id();

    // Create alias for n3. Path must start with a leading slash.
    $this->container->get('path_alias.repository')->save('/node/' . $this->nodeIds['n3'], '/about', 'en');

    // Add menu links in different forms.
    $this->createMenuLink('main', 'internal:/node/' . $this->nodeIds['n1'], TRUE);
    $this->createMenuLink('main', 'entity:node/' . $this->nodeIds['n2'], TRUE);

    // Alias link for n3.
    $this->createMenuLink('main', 'internal:/about', TRUE);

    // Disabled link for n4.
    $this->createMenuLink('main', 'internal:/node/' . $this->nodeIds['n4'], FALSE);

    // Link in other menu for n5.
    $this->createMenuLink('footer', 'internal:/node/' . $this->nodeIds['n5'], TRUE);
  }

  /**
   * Helper to create a menu link content entity.
   */
  protected function createMenuLink(string $menu, string $uri, bool $enabled): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $storage->create([
      'menu_name' => $menu,
      'link' => ['uri' => $uri],
      'enabled' => $enabled ? 1 : 0,
      'title' => $uri,
    ])->save();
  }

  /**
   * Tests that only nodes linked in the selected menu are returned.
   */
  public function testFilterReturnsOnlyLinkedNodesInSelectedMenu(): void {
    $view = $this->buildAdHocView();

    // Add the custom filter and target the 'main' menu.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'in_menu' => [
        'id' => 'in_menu',
        'table' => 'node_field_data',
        'field' => 'in_menu',
        'plugin_id' => 'node_in_menu',
        'value' => 'main',
      ],
    ]);

    $view->executeDisplay('default');

    $nids = array_map(static function ($row) {
      return (int) $row->_entity->id();
    }, $view->result);
    sort($nids);

    // Expected: n1 via internal:/node/NID, n2 via entity:node/NID, n3 via alias.
    $expected = [
      $this->nodeIds['n1'],
      $this->nodeIds['n2'],
      $this->nodeIds['n3'],
    ];
    sort($expected);

    $this->assertSame($expected, $nids);
  }

  /**
   * Tests that the filter form lists available menus.
   */
  public function testFilterFormListsMenus(): void {
    $view = $this->buildAdHocView();
    $view->initDisplay();
    $view->initHandlers();

    // Attach the filter handler so we can build its form.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'in_menu' => [
        'id' => 'in_menu',
        'table' => 'node_field_data',
        'field' => 'in_menu',
        'plugin_id' => 'node_in_menu',
      ],
    ]);

    $view->initDisplay();
    $view->initHandlers();

    $filters = $view->display_handler->getHandlers('filter');
    $this->assertArrayHasKey('in_menu', $filters);

    $form = [];
    $filters['in_menu']->valueForm($form, new FormState());

    $this->assertArrayHasKey('#options', $form['value']);
    $this->assertArrayHasKey('main', $form['value']['#options']);
    $this->assertArrayHasKey('footer', $form['value']['#options']);
  }

  /**
   * Builds a minimal programmatic View over nodes with an output field.
   */
  protected function buildAdHocView() {
    $view = View::create([
      'id' => 'node_in_menu_test',
      'base_table' => 'node_field_data',
      'label' => 'Node in Menu test',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'display_options' => [
            'access' => ['type' => 'none'],
            'query' => ['type' => 'views_query'],
            'filters' => [],
            'fields' => [
              'nid' => [
                'id' => 'nid',
                'table' => 'node_field_data',
                'field' => 'nid',
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    return Views::getView('node_in_menu_test');
  }

}
