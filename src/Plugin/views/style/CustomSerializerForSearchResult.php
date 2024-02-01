<?php

namespace Drupal\handsurgeryresource_module\Plugin\views\style;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats.
 *
 * Add separator in csv file so Microsoft Word knows how to open it.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "hsr_search_serializer",
 *   title = @Translation("Custom Serializer For Search API JSON"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class CustomSerializerForSearchResult extends Serializer implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    
    $render = parent::render();
    $response = [];
    $result  = json_decode($render);    

    $filters = \Drupal::request()->query->get('filters');
    
    if($filters){
      
      $filters_array = explode(',', $filters);
      
      if(!empty($filters_array)){

        $cats = array('diagnosis','exams_and_signs_list','diagnostic_studies','hand_therapy_library');
        $common_cats = array_intersect($cats, $filters_array);
        $check_cats = empty($common_cats) ? $cats : $common_cats;

        $first_term = null;
        $checkquery = \Drupal::entityQuery('taxonomy_term');
        $checkquery->condition('vid', 'anatomic_parts')
          ->condition('name', 'Tumor')
          ->condition('status', '1')
          ->accessCheck(false);
        $checkresult = $checkquery->execute();

        if(!empty($checkresult) && count($checkresult) == 1){              
            $array_reset = array_values($checkresult);
            $first_term = $array_reset[0];            
        }

        foreach($result as $key => $value) {
      
          if(isset($value->vid) && !in_array($value->vid, $check_cats)){
            unset($result[$key]);
            continue;              
          }

          // anatomy_systems filter checks atleast one value should be checked for anatomy and body systems together.
          $anatomic_parts = $body_systems = $anatomic_and_body = [];
          
          if(isset($value->field_anatomic_parts)){
            $anatomic_parts = explode(",",$value->field_anatomic_parts);
            $anatomic_parts = array_filter($anatomic_parts);
          }

          if(isset($value->field_body_systems)){
            $body_systems = explode(",",$value->field_body_systems);
            $body_systems = array_filter($body_systems);
          }

          $anatomic_and_body = array_unique(array_merge($anatomic_parts, $body_systems));

          if(in_array('anatomy_systems', $filters_array) && empty($anatomic_and_body)){
            unset($result[$key]);
            continue;
          }          

          if($first_term && in_array('tumors', $filters_array) && !in_array($first_term, $anatomic_and_body)){              
            unset($result[$key]);
            continue;
          }

          if(isset($value->field_congenital) && in_array('congenital', $filters_array) && $value->field_congenital != 'Yes'){
            unset($result[$key]);
            continue;
          }          

        }

      }      

    }

    return json_encode(array_values($result));

  }

}
