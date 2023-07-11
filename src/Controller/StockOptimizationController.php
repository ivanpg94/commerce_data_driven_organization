<?php

namespace Drupal\commerce_data_driven_organization\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Renderer;

class StockOptimizationController extends ControllerBase
{
  public function manageRotation(){
    $form['form'] = \Drupal::formBuilder()->getForm('Drupal\commerce_data_driven_organization\Form\DateForm');

    $form['form1'] = \Drupal::formBuilder()->getForm('Drupal\commerce_data_driven_organization\Form\StockOptimizationTable');

    $form['form3'] = views_embed_view('commerce_stock_data_driven', 'page_1');

    $form['#markup'] = render($view);
    return $form;
  }
}
