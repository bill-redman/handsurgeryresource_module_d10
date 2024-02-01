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
 *   id = "rest_flagged_terms",
 *   label = @Translation("Customized REST Resource for flags"),
 *   uri_paths = {  
 *		"https://www.drupal.org/link-relations/create" = "/custom/bookmarks"
 *   }
 * )
 */

class FlagTerm extends ResourceBase {

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

	const BOOKMARK_TERMS_MACHINE_NAME = 'bookmarkhsrterm';

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

	public static function get_bookmarked_term_ids() {

		$user = \Drupal::currentUser();

		if ($user->isAnonymous()) {
		    throw new BadRequestHttpException('allowed for logged in users');
		}
		else if(empty(\Drupal::hasService('flag'))){
			throw new UnprocessableEntityHttpException('please enable and configure flags module');
		}

		$flag = \Drupal::service('flag')->getFlagById(self::BOOKMARK_TERMS_MACHINE_NAME);

		if(!$flag){
			throw new UnprocessableEntityHttpException('please create bookmarks flag with machine name '.self::BOOKMARK_TERMS_MACHINE_NAME);
		}

		$database = \Drupal::database();

		$sql = "SELECT entity_id FROM {flagging} AS a INNER JOIN {taxonomy_term_field_data} AS b ON a.entity_id = b.tid 
		WHERE a.uid = :uid AND a.flag_id = :flag_id and a.entity_type = 'taxonomy_term' AND a.global='0' AND b.status='1'
		ORDER BY b.name ASC";
		$values = array(':uid' => $user->id(), ':flag_id' => $flag->id());
		$tids = $database->query($sql, $values)
				  	->fetchCol();

		return $tids;

	}

	/**
	* Responds to POST requests.
	* @return \Drupal\rest\ResourceResponse	
	*/

	public function post() {

		if ( 0 === strpos( $this->currentRequest->headers->get( 'Content-Type' ), 'application/json' ) ) {
	      	$data = json_decode( $this->currentRequest->getContent(), TRUE );	      	
	    }

	    if(!isset($data["entity_id"]) || !ctype_digit($data["entity_id"])){
	    	throw new BadRequestHttpException('\'entity_id\' parameter is required');
	    }

	    $user = \Drupal::currentUser();
		try {
			$flag_service = \Drupal::service('flag');

			$flag = $flag_service->getFlagById(self::BOOKMARK_TERMS_MACHINE_NAME);

			$entity = $flag_service->getFlaggableById($flag, $data["entity_id"]);

			if(!$entity){
				throw new BadRequestHttpException('Invalid entity_id');
			}

			$flagging = $flag_service->getFlagging($flag, $entity, $user);
			$status = "1";
			$msg = "Bookmarked";

			if (!$flagging) {
			  $flag_service->flag($flag, $entity, $user);
			}
			else {
			  $flag_service->unflag($flag, $entity, $user);
			  $status = "0";
			  $msg = "Bookmark removed";
			}			
		}
		catch (\LogicException $e) {
		    throw new BadRequestHttpException($e->getMessage());
		} 
		catch (Exception $e) {
		  	throw new BadRequestHttpException($e->getMessage());		  
		}

        $response = array( "status" => $status, "message" => $msg);

        $build = array(
            '#cache' => array(
                'max-age' => 0,
            ),
        );

        return (new ResourceResponse($response))->addCacheableDependency($build);
    }

}