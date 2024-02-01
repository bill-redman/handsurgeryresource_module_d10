<?php
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * Implements hook_views_query_alter().
 * Cache issue occurs for non-authenticated calls 
 */

function handsurgeryresource_module_views_query_alter(ViewExecutable $view, $query) {

	// Only for Search API view custom sort/filter.
	
	if ($view->id() == 'rest_api_for_search_results') {

		$options = &$query->getOptions();
  		$sorts = &$query->getSort();

		$sort_by = \Drupal::request()->query->get('sort_by');
		// relevance
		// latest
		// ascending
		// descending		

  		// clears default sort methods from current view
		$sorts = [];

		switch ($sort_by) {
			
			case 'ascending':
				$sorts = array('name' => 'ASC');
				break;

			case 'descending':
				$sorts = array('name' => 'DESC');
				break;

			case 'latest':
				$sorts = array('changed' => 'DESC');
				break;		
			
			default:
				$sorts = array('search_api_relevance' => 'DESC');
				break;

		}

	}

}
?>