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
use Drupal\Core\Password\PasswordInterface;


/**
 * Provides a Custom Resource
 *
 * @RestResource(
 *   id = "rest_change_password",
 *   label = @Translation("Customized REST Resource for changing logged in user password"),
 *   uri_paths = {
 *     	"https://www.drupal.org/link-relations/create" = "/custom/change_password"
 *   }
 * )
 */

class ChangePassword extends ResourceBase {

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

	/**
   * The password service class.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  	protected $passwordHasher;

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
	* @param \Drupal\Core\Password\PasswordInterface $password_hasher
   	*   The password service.
	*/
	public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Request $current_request, PasswordInterface $password_hasher) {
		parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
		$this->currentUser = $current_user;
		$this->currentRequest = $current_request;
		$this->passwordHasher = $password_hasher;		
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
		  $container->get('request_stack')->getCurrentRequest(),
		  $container->get('password')
		);
	}


	/**
	* Responds to POST requests.
	* @return \Drupal\rest\ResourceResponse	
	*/

	public function post() {

		if( 0 === strpos( $this->currentRequest->headers->get( 'Content-Type' ), 'application/json' ) ) {
	      	$data = json_decode( $this->currentRequest->getContent(), TRUE );	      	
	    }

	    if(empty($data["old_password"]) || empty($data["new_password"])){
	    	throw new BadRequestHttpException('The non-empty parameters \'old_password\' & \'new_password\' are required');
	    }

	    $user = \Drupal::currentUser();

	    if($user->isAnonymous()) {
		    throw new BadRequestHttpException('Request allowed only for logged in users');
		}

		$id = $user->id();
		$email = $user->getEmail();
		//$username = $user->getUsername();
		
		$userdata = \Drupal\user\Entity\User::load($id);
		$ret = $this->passwordHasher->check($data["old_password"], $userdata->getPassword());

		if(!$ret) {
		    throw new BadRequestHttpException('Old password mismatch');
		}

   		$userdata->setPassword($data["new_password"]);
		$result = $userdata->save();
   		
	    $response = array( "status" => 1, "message" => "success");

        $build = array(
            '#cache' => array(
                'max-age' => 0,
            ),
        );

        return (new ResourceResponse($response))->addCacheableDependency($build);

	}
	

}