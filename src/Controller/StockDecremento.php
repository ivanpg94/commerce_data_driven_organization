<?php

namespace Drupal\commerce_data_driven_organization\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class StockDecremento extends ControllerBase {

  public function ajaxUpdateOrder(Request $request)
  {
    $order = \Drupal::routeMatch()->getParameter('commerce_order');
    $order_id = $order;
    $database = \Drupal::database();

    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);

    foreach ($order->getItems() as $order_item) {
      $quantity = $order_item->quantity->value;
      $quantity = $quantity * -1;
      $variation_id = $order_item->getPurchasedEntity()->variation_id->value;
      $id = $variation_id;
      $var = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($id);
      $var->set('field_stock', $quantity);
      $var->save();
    }
    $stock_decrementado = 'Si';
    $query = $database->upsert('commerce_order__field_stock_decrementado');
    $query->fields([
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'langcode',
      'delta',
      'field_stock_decrementado_value',
    ]);
    $query->values([
      'physical',
      0,
      $order_id,
      $order_id,
      'und',
      0,
      $stock_decrementado,
    ]);
    $query->key('entity_id');
    $query->execute();
    \Drupal::entityTypeManager()->getStorage('commerce_order')->resetCache();


    return new RedirectResponse('/admin/commerce/orders/' . $order_id);
  }
  public function ajaxCanceladoOrder(Request $request)
  {
    $order = \Drupal::routeMatch()->getParameter('commerce_order');
    $order_id = $order;
    $database = \Drupal::database();

    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);

    foreach ($order->getItems() as $order_item) {
      $quantity = $order_item->quantity->value;
      $quantity = $quantity;
      $variation_id = $order_item->getPurchasedEntity()->variation_id->value;
      $id = $variation_id;
      $var = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($id);
      $var->set('field_stock', $quantity);
      $var->save();
    }
    $stock_decrementado = 'No';
    $query = $database->upsert('commerce_order__field_stock_decrementado');
    $query->fields([
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'langcode',
      'delta',
      'field_stock_decrementado_value',
    ]);
    $query->values([
      'physical',
      0,
      $order_id,
      $order_id,
      'und',
      0,
      $stock_decrementado,
    ]);
    $query->key('entity_id');
    $query->execute();
    \Drupal::entityTypeManager()->getStorage('commerce_order')->resetCache();

    return new RedirectResponse('/admin/commerce/orders/' . $order_id);
  }

}
