<?php

namespace Drupal\handsurgeryresource_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;


/**
 * Provides a Custom Resource
 *
 * @RestResource(
 *   id = "rest_page_content",
 *   label = @Translation("Customized REST Resource for page content"),
 *   uri_paths = {
 *     	"canonical" = "/custom/get_page_content"
 *   }
 * )
 */

class PageContent extends ResourceBase {

	/**
	*  A curent user instance.
	*
	* @var \Drupal\Core\Session\AccountProxyInterface
	*/
	protected $currentUser;

	/**
	*
	* @var \Symfony\Component\HttpFoundation\Request
	*/
	protected $currentRequest;

	const DIAGNOSIS_SLUG = '/diagnosis-list';
	const TEST_AND_SIGN_SLUG = '/test-list';
	const DIAGNOSTIC_STUDIES_SLUG = '/studies';
	const ANATOMY_BODY_SYSTEMS_SLUG = '/body-systems';
	const TUMORS_SLUG = '/tumors';
	const CONGENITAL_SLUG = '/congenital';
	const HAND_THERAPY_LIBRARY_SLUG = '/handtherapy';
	const BOOKMARKS_SLUG = '/bookmarks';

	const DIAGNOSIS_MACHINE_NAME = 'diagnosis';
	const TEST_AND_SIGN_MACHINE_NAME = 'test_and_sign_list';
	const DIAGNOSTIC_STUDIES_MACHINE_NAME = 'work_up_options';
	const HAND_THERAPY_LIBRARY_MACHINE_NAME = 'hand_therapy_library';
	const ANATOMY_MACHINE_NAME = 'anatomic_parts';
	const BODY_SYSTEMS_MACHINE_NAME = 'body_systems';

	const BODY_SYSTEMS_FIELD_NAME = 'field_body_systems';
	const ANATOMIC_PARTS_FIELD_NAME = 'field_anatomic_parts';
	const DO_NOT_LIST_INSIDE_ANATOMY_PAGE_FIELD_NAME = 'field_list_inside_anatomy_page';
	const CONGENITAL_DIAGNOSIS_LIST_FIELD_NAME = 'field_congenital';

	const TUMORS_TERM_NAME = 'Tumor';

	const DIAGNOSIS_GROUP_LABELS_MACHINE_NAME = 'diagnosis_group_labels';
	const DIAGNOSIS_PAGE_CUTOMIZATION_COLLECTION_FIELD_NAME = 'field_page_view_customization';

	private $bookmarks = NULL;

	/**
	* Constructs a Drupal\rest\Plugin\ResourceBase object.
	*
	* @param array $configuration
	*   A configuration array containing information about the plugin instance.
	* @param string $plugin_id
	*   The plugin_id for the plugin instance.
	* @param mixed $plugin_definition
	*   The plugin implementation definition.
	* @param array $serializer_formats
	*   The available serialization formats.
	* @param \Psr\Log\LoggerInterface $logger
	*   A logger instance.
	* @param \Drupal\Core\Session\AccountProxyInterface $current_user
	*   The current user instance.
	* @param Symfony\Component\HttpFoundation\Request $current_request
	*   The current request
	*/
	public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Request $current_request) {
		parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
		$this->currentUser = $current_user;
		$this->currentRequest = $current_request;
	}

	/**
	* {@inheritdoc}
	*/
	public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
		return new static(
		  $configuration,
		  $plugin_id,
		  $plugin_definition,
		  $container->getParameter('serializer.formats'),
		  $container->get('logger.factory')->get('example_rest'),
		  $container->get('current_user'),
		  $container->get('request_stack')->getCurrentRequest()
		);
	}


	/**
	* Responds to entity GET requests.
	* @return \Drupal\rest\ResourceResponse
	*/

	public function get() {

		$response = [];
		$page_type = !empty($this->currentRequest->query->get('page_type')) ? $this->currentRequest->query->get('page_type') : 'normal';
		$page = $this->currentRequest->query->get('page');

		if($page_type == 'normal'){

			if ($page == self::DIAGNOSIS_SLUG) {
				$response = $this->get_diagnosis_list();
		    }
		    else if ($page == self::TEST_AND_SIGN_SLUG) {
				$response = $this->get_normal_published_term_list(self::TEST_AND_SIGN_MACHINE_NAME);
		    }
		    else if ($page == self::DIAGNOSTIC_STUDIES_SLUG) {
				$response = $this->get_normal_published_term_list(self::DIAGNOSTIC_STUDIES_MACHINE_NAME);
		    }
		    else if ($page == self::ANATOMY_BODY_SYSTEMS_SLUG) {
				$response = $this->get_custom_published_anatomic_body_systems_list();
		    }
		    else if ($page == self::TUMORS_SLUG) {
				$response = $this->get_tumors_list();

				if(isset($response['error']))
					throw new UnprocessableEntityHttpException($response['error']);
		    }
		    else if ($page == self::CONGENITAL_SLUG) {
				$response = $this->get_congenital_diagnosis_list();
		    }
			else if ($page == self::HAND_THERAPY_LIBRARY_SLUG) {
				$response = $this->get_normal_published_term_list(self::HAND_THERAPY_LIBRARY_MACHINE_NAME);
		    }
		    else if ($page == self::BOOKMARKS_SLUG) {
				$response = $this->get_bookmarks();
		    }
		    else{
		    	throw new BadRequestHttpException('Invalid \'page\' parameter');
		    }	    
		}else{

			if ($page_type == 'sublist') {
				
				$term_id = $this->currentRequest->query->get('term_id');
				if(!ctype_digit($term_id)){
					throw new BadRequestHttpException('Invalid \'term_id\' parameter');
				}

				$response = $this->get_anatomic_body_systems_list_by_id($term_id);
		    }
		    else{
		    	throw new BadRequestHttpException('Invalid \'page type\'');
		    }

		}

	    $build = array(
            '#cache' => array(
                'max-age' => 0,
            ),
        );
		
		//return (new ResourceResponse($response));
		return (new ResourceResponse($response))->addCacheableDependency($build);
	}

	private function check_bookmarked_or_not($termid = null){

		$marked = false;

		if(is_null($this->bookmarks)){
			$this->bookmarks = FlagTerm::get_bookmarked_term_ids();			
		}

		if( $termid && !empty($this->bookmarks) && in_array($termid, $this->bookmarks)){
			$marked = true;
		}
		
		return $marked;
	}

	private function get_diagnosis_list(){

		$database = \Drupal::database();

	    $sql = "SELECT 
		  b.item_id AS entry_id,	
		  a.entity_id,
		  c.field_article_title_value AS title,
		  d.field_jump_to_value AS jump_to,
		  e.field_redirect_to_target_id AS redirect_to,
		  f.field_label_group_target_id AS label_id,
		  fdata.name AS label
		FROM
		  {taxonomy_term_field_data} AS terms
		  JOIN	
		  {taxonomy_term__field_page_view_customization} AS a
		  ON terms.tid = a.entity_id
		  JOIN
		  {field_collection_item} AS b
		  ON a.field_page_view_customization_target_id = b.item_id
		  JOIN
		  {field_collection_item__field_article_title} AS c
		  ON a.field_page_view_customization_target_id = c.entity_id 
		  LEFT JOIN 
		  {field_collection_item__field_jump_to} AS d
		  ON a.field_page_view_customization_target_id = d.entity_id AND d.deleted = '0' AND d.bundle = :field_pvc
		  LEFT JOIN 
		  {field_collection_item__field_redirect_to} AS e
		  ON a.field_page_view_customization_target_id = e.entity_id AND e.deleted = '0' AND e.bundle = :field_pvc
		  LEFT JOIN 
		  {field_collection_item__field_label_group} AS f
		  ON a.field_page_view_customization_target_id = f.entity_id AND f.deleted = '0' AND f.bundle = :field_pvc
		  LEFT JOIN 
		  {taxonomy_term_field_data} AS fdata
		  ON f.field_label_group_target_id = fdata.tid AND fdata.vid = :glabel AND fdata.status = '1'
		WHERE terms.status = '1'
		  AND terms.vid = :vid 
		  AND a.bundle = :vid 
		  AND a.deleted = '0' 
		  AND b.field_name = :field_pvc
		  AND c.deleted = '0'
		ORDER BY title ASC";

		$values = array(
			':vid' => self::DIAGNOSIS_MACHINE_NAME,
			':field_pvc' => self::DIAGNOSIS_PAGE_CUTOMIZATION_COLLECTION_FIELD_NAME,
			':glabel' => self::DIAGNOSIS_GROUP_LABELS_MACHINE_NAME
		);

		$query = $database->query($sql, $values);
		$terms = $query->fetchAll();	

	    $res = [];
		$groups = [];
		$sublabels = [];

		if (!empty($terms)) {

			foreach ($terms as $term) {
				
				$id = $term->entity_id;
				$name = $term->title;
				$key = strtoupper(substr($name, 0, 1));
				if( !isset($res[$key]) )
					$res[$key] = [];					

				$grplabel = $term->label_id;
				$jump_to = $term->jump_to ? $term->jump_to : "";
				$redirect_to = $term->redirect_to && ctype_digit($term->redirect_to) ? $term->redirect_to : null;

				if($grplabel && $term->label){
					
					if( !isset($groups[$grplabel]) ){
						$groups[$grplabel] = [];

						$groups[$grplabel]['label'] = $term->label;
						$groups[$grplabel]['terms'] = [];

						$sublabels[$grplabel] = $term->label;												
					}
										
					$groups[$grplabel]['terms'][] = array(						
						'id' => $id,
						'name' => $name,
						'bookmark' => $this->check_bookmarked_or_not($id),
						'jump_to' => $jump_to,
						'redirect_to' => $redirect_to
					);					
				}
				else{
					$res[$key][] = array(						
						'id' => $id,
						'name' => $name,
						'bookmark' => $this->check_bookmarked_or_not($id),
						'jump_to' => $jump_to,
						'redirect_to' => $redirect_to
					);
				}

				if(empty($res[$key])) unset($res[$key]);
				
			}
		}			

		// this part performs actions for labelled terms, adds to specific slot of $res
		if(!empty($sublabels)){

			asort($sublabels);

			foreach ($sublabels as $skey => $svalue) {
				
				$lkey = strtoupper(substr($svalue, 0, 1));
				if( !isset($res[$lkey]) ){
					$res[$lkey] = [];

					$res[$lkey][] = $groups[$skey];
				}else{
					$done = false;
					$sorted = [];
					foreach ($res[$lkey] as $resultkey => $resultvalue) {
						
						if (array_key_exists("label",$resultvalue)){
							$sorted[] = $resultvalue;
						}
						else{								
							if(strcasecmp($resultvalue['name'], $svalue) > 0 && !$done){
								$sorted[] = $groups[$skey];
								$done = true;
							}
							$sorted[] = $resultvalue;
						}							
					}

					if(!$done){
						$sorted[] = $groups[$skey];
					}

					$res[$lkey] = $sorted;
				}

			}
		}
		
		ksort($res);
		return $res;
	}

	private function get_diagnosis_list_old_structure(){

	    //this follows one term comes once based on title

	    //$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(self::DIAGNOSIS_MACHINE_NAME);
		//$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => self::DIAGNOSIS_MACHINE_NAME, 'status' => '1']);
					
		$query = \Drupal::entityQuery('taxonomy_term');
	  
		$query->condition('vid', self::DIAGNOSIS_MACHINE_NAME)
			->condition('status', '1')
			->sort('name', 'ASC')
			->accessCheck(false);

		$tids = $query->execute();
		$terms = Term::loadMultiple($tids);
		$res = [];

		$groups = [];
		$sublabels = [];

		if (!empty($terms)) {

			foreach ($terms as $term) {
				
				$id = $term->id();
				$name = $term->getName();
				$key = strtoupper(substr($name, 0, 1));
				if( !isset($res[$key]) )
					$res[$key] = [];					

				$grplabel = $term->get('field_diagnosis_page_grouping_la')->target_id;
				$lterm = null;

				if($grplabel){
					$lterm = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($grplabel);
				}

				// checking term attached to a valid vocabulary or not 
				if($grplabel && $lterm){
					
					if( !isset($groups[$grplabel]) ){
						$groups[$grplabel] = [];

						$lname = $lterm->label();
						$groups[$grplabel]['label'] = $lname;
						$groups[$grplabel]['terms'] = [];

						$sublabels[$grplabel] = $lname;												
					}
										
					$groups[$grplabel]['terms'][] = array(						
						'id' => $id,
						'name' => $name,
						'bookmark' => $this->check_bookmarked_or_not($id)
					);					
				}
				else{
					$res[$key][] = array(						
						'id' => $id,
						'name' => $name,
						'bookmark' => $this->check_bookmarked_or_not($id)
					);
				}

				if(empty($res[$key])) unset($res[$key]);
				
			}
		}			

		if(!empty($sublabels)){

			asort($sublabels);

			foreach ($sublabels as $skey => $svalue) {
				
				$lkey = strtoupper(substr($svalue, 0, 1));
				if( !isset($res[$lkey]) ){
					$res[$lkey] = [];

					$res[$lkey][] = $groups[$skey];
				}else{
					$done = false;
					$sorted = [];
					foreach ($res[$lkey] as $resultkey => $resultvalue) {
						
						if (array_key_exists("label",$resultvalue)){
							$sorted[] = $resultvalue;
						}
						else{								
							if(strcasecmp($resultvalue['name'], $svalue) > 0 && !$done){
								$sorted[] = $groups[$skey];
								$done = true;
							}
							$sorted[] = $resultvalue;
						}							
					}

					if(!$done){
						$sorted[] = $groups[$skey];
					}

					$res[$lkey] = $sorted;
				}

			}
		}
		
		//echo json_encode($res);exit;
		ksort($res);
		return $res;
	}

	private function get_normal_published_term_list($vocabulary_name = null){

		$query = \Drupal::entityQuery('taxonomy_term');
	  
		$query->condition('vid', $vocabulary_name)
			->condition('status', '1')
			->sort('name', 'ASC')
			->accessCheck(false);

		$tids = $query->execute();
		$terms = Term::loadMultiple($tids);
		$res = [];

		if (!empty($terms)) {

			foreach ($terms as $term) {
				
				$id = $term->id();
				$name = $term->getName();

				$res[] = array(						
					'id' => $id,
					'name' => $name,
					'bookmark' => $this->check_bookmarked_or_not($id)
				);
			}

		}		

		return $res;
	}

	private function get_custom_published_anatomic_body_systems_list(){

		$res = array('ap' => [], 'bs' => []);

		$apquery = \Drupal::entityQuery('taxonomy_term');
		$group = $apquery->orConditionGroup()
			->condition('vid', self::ANATOMY_MACHINE_NAME)
			->condition('vid', self::BODY_SYSTEMS_MACHINE_NAME);  
		$apquery->condition($group)
			->condition('status', '1')			
			->notExists(self::DO_NOT_LIST_INSIDE_ANATOMY_PAGE_FIELD_NAME)
			->sort('name', 'ASC')
			->accessCheck(false);

		$aptids = $apquery->execute();
		$apterms = Term::loadMultiple($aptids);
		
		if (!empty($apterms)) {
			
			foreach ($apterms as $apterm) {				
				$apid = $apterm->id();
				$apname = $apterm->getName();

				$vocabulary_id = $apterm->bundle();
				$catkey = $vocabulary_id == self::ANATOMY_MACHINE_NAME ? 'ap' : 'bs';
				$res[$catkey][] = array(						
					'id' => $apid,
					'name' => $apname
				);
			}

		}
		
		return $res;
	}

	private function get_anatomic_body_systems_list_by_id($term_id = null){

		$res = array('diagnosis' => [], 'tests' => []);

		$dtquery = \Drupal::entityQuery('taxonomy_term');		
		$dtgroup1 = $dtquery->orConditionGroup()
			->condition('vid', self::DIAGNOSIS_MACHINE_NAME)
			->condition('vid', self::TEST_AND_SIGN_MACHINE_NAME);
		$dtgroup2 = $dtquery->orConditionGroup()
			->condition(self::BODY_SYSTEMS_FIELD_NAME, $term_id)
			->condition(self::ANATOMIC_PARTS_FIELD_NAME, $term_id);
		$dtquery->condition($dtgroup1)
			->condition($dtgroup2)
			->condition('status', '1')
			->sort('name', 'ASC')
			->accessCheck(false);
		$dtresult = $dtquery->execute();
		$dtterms = Term::loadMultiple($dtresult);

		if (!empty($dtterms)) {
			
			foreach ($dtterms as $term) {				
				$id = $term->id();
				$name = $term->getName();

				$vocabulary_id = $term->bundle();
				$catkey = $vocabulary_id == self::DIAGNOSIS_MACHINE_NAME ? 'diagnosis' : 'tests';
				$res[$catkey][] = array(						
					'id' => $id,
					'name' => $name,
					'bookmark' => $this->check_bookmarked_or_not($id)
				);
			}

		}
		
		return $res;
	}

	private function get_tumors_list(){

		$tumors_term_name = self::TUMORS_TERM_NAME;

		$checkquery = \Drupal::entityQuery('taxonomy_term');
		$checkquery->condition('vid', self::ANATOMY_MACHINE_NAME)
			->condition('name', trim($tumors_term_name))
			->condition('status', '1')
			->accessCheck(false);
		$checkresult = $checkquery->execute();

		if(empty($checkresult) || count($checkresult) > 1){
			return array('error' => 'Published/Unique term with name '.$tumors_term_name.' doesn\'t exists under the vocabulary '.self::ANATOMY_MACHINE_NAME);
		}

		$array_reset = array_values($checkresult);
		$first_term = $array_reset[0];
  		
		$res = array('diagnosis' => [], 'tests' => []);

		$query = \Drupal::entityQuery('taxonomy_term');
		$group = $query->orConditionGroup()
			->condition('vid', self::DIAGNOSIS_MACHINE_NAME)
			->condition('vid', self::TEST_AND_SIGN_MACHINE_NAME);  
		$query->condition($group)
			->condition('status', '1')
			->condition(self::ANATOMIC_PARTS_FIELD_NAME, $first_term)
			->sort('name', 'ASC')
			->accessCheck(false);

		$tids = $query->execute();
		$terms = Term::loadMultiple($tids);
		
		if (!empty($terms)) {
			
			foreach ($terms as $term) {				
				$id = $term->id();
				$name = $term->getName();

				$vocabulary_id = $term->bundle();
				$catkey = $vocabulary_id == self::DIAGNOSIS_MACHINE_NAME ? 'diagnosis' : 'tests';
				$res[$catkey][] = array(						
					'id' => $id,
					'name' => $name,
					'bookmark' => $this->check_bookmarked_or_not($id)
				);
			}

		}
		
		return $res;
	}

	private function get_congenital_diagnosis_list(){

		$query = \Drupal::entityQuery('taxonomy_term');
	  
		$query->condition('vid', self::DIAGNOSIS_MACHINE_NAME)
			->condition('status', '1')
			->condition(self::CONGENITAL_DIAGNOSIS_LIST_FIELD_NAME, 'Yes')
			->sort('name', 'ASC')
			->accessCheck(false);

		$tids = $query->execute();
		$terms = Term::loadMultiple($tids);
		$res = [];

		if (!empty($terms)) {

			foreach ($terms as $term) {
				
				$id = $term->id();
				$name = $term->getName();

				$res[] = array(						
					'id' => $id,
					'name' => $name,
					'bookmark' => $this->check_bookmarked_or_not($id)
				);
			}

		}		

		return $res;
	}

	private function get_bookmarks(){

		$tids = FlagTerm::get_bookmarked_term_ids();
		
		$terms = Term::loadMultiple($tids);
		$result = array('diagnosis' => [], 'tests' => [], 'studies' => []);

		if (!empty($terms)) {
			foreach ($terms as $term) {				
				$id = $term->id();
				$name = $term->getName();
				$vocabulary_id = $term->bundle();

				$catkey = null;
				if($vocabulary_id == self::DIAGNOSIS_MACHINE_NAME){
					$catkey = "diagnosis";
				}
				else if($vocabulary_id == self::TEST_AND_SIGN_MACHINE_NAME){
					$catkey = "tests";
				}
				else if($vocabulary_id == self::DIAGNOSTIC_STUDIES_MACHINE_NAME){
					$catkey = "studies";
				}

				if($catkey){
					$result[$catkey][] = array(						
						'id' => $id,
						'name' => $name
					);
				}
				
			}
		}

		return $result;

	}

}