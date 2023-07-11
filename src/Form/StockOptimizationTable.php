<?php

namespace Drupal\commerce_data_driven_organization\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class StockOptimizationTable extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'log_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pageNo = NULL)
  {
    $datos = \Drupal::formBuilder()->getForm('Drupal\commerce_data_driven_organization\Form\DateForm');
    $date_start_form = $datos['fecha_inicio']['#value']['date'];
    $date_end_form = $datos['fecha_fin']['#value']['date'];

    $date_start = date("d-m-Y", strtotime($date_start_form));
    $date_end = date("d-m-Y", strtotime($date_end_form));

    $date_input_start = $date_start;
    $date_input_end = $date_end;

    $date_unix_start = strtotime($date_input_start);
    $date_unix_end = strtotime($date_input_end);


    $database = \Drupal::database();
    $database->truncate('commerce_data_driven_organization__stock_optimization')->execute();
    //Valoracion stock = stock que tenemos actualente  + el precio coste de ese estock
    $valoracion_stock = $database->query('SELECT SUM(total_cost_stock) FROM (SELECT a.entity_id, a.qty*b.field_cost_number as total_cost_stock
                       FROM commerce_stock_location_level a, commerce_product_variation__field_cost b
                        where a.entity_id = b.entity_id) src')->fetchCol()[0];
    $valoracion_stock = floatval($valoracion_stock);

    //sumamos los campos de la tabla de commerce order filtrada por el ultimo año     (falta filtrarla por año)
    //   $order_total_price = $database->query("SELECT SUM(total_price__number) FROM commerce_order where completed > '.$date_unix.'")->fetchCol()[0];
    $order_total_price = $database->query("SELECT SUM(total_price__number) FROM (SELECT order_id, total_price__number FROM commerce_order
                                            where created > $date_unix_start AND created < $date_unix_end AND state != 'draft' ) src")->fetchCol()[0];
    //Accedemos a la tabla de commerce_order_report__precio_coste         (falta filtrarla por año)
    $order_price_cost = $database->query("SELECT SUM(precio_coste_number) FROM (SELECT a.order_id, b.field_coste_pedido_number as precio_coste_number FROM commerce_order a, commerce_order__field_coste_pedido b
                                        where a.order_id = b.entity_id AND a.created > $date_unix_start AND a.created < $date_unix_end) src")->fetchCol()[0];
    $order_price_cost = floatval($order_price_cost);
    $total_benefit = ($order_total_price) - ($order_price_cost);

    $margin_over_sale = ($total_benefit) / ($order_total_price);



    //Cantidad de stock actual en la tienda
    $total_stock = $database->query("SELECT SUM(qty) FROM commerce_stock_location_level")->fetchCol()[0];

    //Estos son los datos que necesitamos mostrar
    $global_rotation = (floatval($order_total_price) / (1 - floatval($margin_over_sale/100))) / floatval($valoracion_stock);

    $query = $database->select('commerce_data_driven_organization__stock_optimization', 'm')
      ->condition('valoracion_stock', $valoracion_stock)
      ->fields('m');
    $data = $query->execute()->fetchAssoc();

    if ($data == false) {
      $result = $database->insert('commerce_data_driven_organization__stock_optimization')
        ->fields([
          'valoracion_stock' => number_format($valoracion_stock, 2, ",", ".") . ' €',
          'order_total_price' => number_format($order_total_price, 2, ",", ".") . ' €',
          'order_price_cost' => number_format($order_price_cost, 2, ",", ".") . ' €',
          'total_benefit' => number_format($total_benefit, 2, ",", ".") . ' €',
          'margin_over_sale' => number_format($margin_over_sale * 100, 2, ",", ".") . ' %',
          'global_rotation' => number_format($global_rotation, 2, ",", ".") . ' veces',
        ]);
      $result->execute();
    }
    $header = [
      'valoracion_stock' => $this->t('Valoracion de Stock'),
      'order_total_price' => $this->t('Ventas + IVA'),
      'order_price_cost' => $this->t('Precio Coste de los pedidos'),
      'total_benefit' => $this->t('Beneficio Total'),
      'margin_over_sale' => $this->t('Margen de venta'),
      'global_rotation' => $this->t('Rotation Global de Stock'),
    ];

      $form['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $this->get_log(),
        '#empty' => $this->t('No logs found'),
      ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Calcular stock máximo y mínimo'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {

  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $database = \Drupal::database();

      for ($id = 29; $id <= 4438; $id++) {
        //OBTENER LA VARIACION
        // $product = \Drupal::routeMatch()->getParameter('commerce_product');
        // $id = \Drupal\commerce_product\Entity\Product::load((int)$product->id())
        //  ->getVariationIds()[0];
        if (!is_null(\Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($id)))  {
          $parameter = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($id);
          if (!is_null($parameter->field_supplier->target_id)) {
            $supplier_id = $parameter->field_supplier->target_id;
            $supplier = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($supplier_id);

            if ($supplier->hasField('field_plazo_envio') && !$supplier->get('field_plazo_envio')->isEmpty()) {
              $plazo_envio = $supplier->get('field_plazo_envio')->value;
            } else {
              $plazo_envio = 2;
            }
          } else {
            $plazo_envio = 2;
          }
          //TITULO DE LA VARIACION
          $title = $parameter->title->value;
          //FECHA
          $fecha_actual = date("d-m-Y");
          $fecha_año = date("d-m-Y", strtotime($fecha_actual . "- 365 days"));
          $date_unix_minimo = strtotime($fecha_año);

          //CONSULTA A LA TABLA DE LOS REPORTES QUE CUENTA EL NUMERO DE VENTAS CONTANDO EL TITULO DEL PRODUCTO(CADA VENTA GENERA UNA CONSULAT NUEVA A ESE PRODUCTO, CONTANDO
/*
          $stock_minimo = $database->query("SELECT COUNT(ventas)
          FROM (SELECT b.title_value as ventas
          FROM commerce_order_report a, commerce_order_report__title b
          where a.report_id = b.entity_id AND b.title_value = '$title' AND created >= $date_unix_minimo) src")
            ->fetchCol()[0];
*/
          $stock_minimo = $database->query("SELECT SUM(ventas)
                FROM (SELECT a.qty as ventas
                FROM commerce_stock_transaction a, commerce_product_variation_field_data b
                WHERE a.entity_id = b.variation_id AND b.title = '$title' AND transaction_time >= $date_unix_minimo) src")
            ->fetchCol()[0];
          if(!is_null($stock_minimo)){
            $stock_minimo = $stock_minimo;
          }else{
            $stock_minimo = 1;
          }

          $stock_minimo = ceil($stock_minimo/365)*$plazo_envio;
          //METEMOS EL VALOR OBTENIDO DE LA CONSULATA EN EL CAMPO DE STOCK MINIMO
          $parameter->field_stock_minimo->setValue($stock_minimo + 1);
          $minimo = $parameter->field_stock_minimo->getValue()[0]['value'];

          //POR ULTIMO LO GUARDAMOS EN LA BASE DE DATOS
          $query = \Drupal::database()->upsert('commerce_product_variation__field_stock_minimo');
          $query->fields([
            'bundle',
            'deleted',
            'entity_id',
            'revision_id',
            'langcode',
            'delta',
            'field_stock_minimo_value',
          ]);
          $query->values([
            'product',
            0,
            $id,
            $id,
            'es',
            0,
            $minimo,
          ]);
          $query->key('entity_id');
          $query->execute();

          //FECHA
          $fecha_actual = date("d-m-Y");
          $fecha_año = date("d-m-Y", strtotime($fecha_actual . "- 365 days"));
          $date_unix_maximo = strtotime($fecha_año);


          $stock_maximo = $database->query("SELECT SUM(ventas)
                FROM (SELECT a.qty as ventas
                FROM commerce_stock_transaction a, commerce_product_variation_field_data b
                WHERE a.entity_id = b.variation_id AND b.title = '$title' AND transaction_time >= $date_unix_maximo) src")
            ->fetchCol()[0];
          if(!is_null($stock_maximo)){
            $stock_maximo = $stock_maximo;
          }else{
            $stock_maximo = 1;
          }
/*
          $stock_maximo = $database->query("SELECT COUNT(ventas)
          FROM (SELECT b.title_value as ventas
          FROM commerce_order_report a, commerce_order_report__title b
          where a.report_id = b.entity_id AND b.title_value = '$title' AND created >= $date_unix_maximo) src")
            ->fetchCol()[0];
*/
          $form_days = 30;

          $stock_maximo = (ceil(($stock_maximo/365)*$form_days))+1;
          //METEMOS EL VALOR OBTENIDO DE LA CONSULATA EN EL CAMPO DE STOCK MINIMO
          $parameter->field_stock_maximo->setValue($stock_maximo);
          $maximo = $parameter->field_stock_maximo->getValue()[0]['value'];

          //POR ULTIMO LO GUARDAMOS EN LA BASE DE DATOS
          $query = \Drupal::database()->upsert('commerce_product_variation__field_stock_maximo');
          $query->fields([
            'bundle',
            'deleted',
            'entity_id',
            'revision_id',
            'langcode',
            'delta',
            'field_stock_maximo_value',
          ]);
          $query->values([
            'product',
            0,
            $id,
            $id,
            'es',
            0,
            $maximo,
          ]);
          $query->key('entity_id');
          $query->execute();
        }
    }
  }

  function get_log()
  {
    $valoracion_stock = "";
    $order_total_price = "";
    $order_price_cost = "";
    $total_benefit = "";
    $margin_over_sale = "";
    $global_rotation = "";


    $valoracion_stock = \Drupal::request()->query->get('valoracion_stock');
    $order_total_price = \Drupal::request()->query->get('order_total_price');
    $order_price_cost = \Drupal::request()->query->get('order_price_cost');
    $total_benefit = \Drupal::request()->query->get('total_benefit');
    $margin_over_sale = \Drupal::request()->query->get('margin_over_sale');
    $global_rotation = \Drupal::request()->query->get('global_rotation');

    $res = array();


      $results = \Drupal::database()->select('commerce_data_driven_organization__stock_optimization', 'st');

      $results->fields('st')
        ->range(0,1);
      $res = $results->execute()->fetchAll();
      $ret = [];
    foreach ($res as $row) {

      $ret[] = [
        'valoracion_stock' => $row->valoracion_stock,
        'order_total_price' => $row->order_total_price,
        'order_price_cost' => $row->order_price_cost,
        'total_benefit' => $row->total_benefit,
        'margin_over_sale' => $row->margin_over_sale,
        'global_rotation' => $row->global_rotation,
      ];
    }
    return $ret;
  }

}
