<?php

/**
 * @file
 * Views data for the node_in_menu module.
 */

/**
 * Implements hook_views_data_alter().
 */
function node_in_menu_views_data_alter(array &$data) {
  if (!isset($data['node_field_data'])) {
    return;
  }

  $data['node_field_data']['in_menu'] = [
    'title' => t('In menu'),
    'help' => t('Filter nodes by whether they are referenced in a menu.'),
    'group' => t('Content'),
    'filter' => [
      'id' => 'node_in_menu',
      'provider' => 'node_in_menu',
    ],
  ];
}
