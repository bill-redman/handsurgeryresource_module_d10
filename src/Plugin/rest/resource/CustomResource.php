<?php

namespace Drupal\handsurgeryresource_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a Custom Resource
 *
 * @RestResource(
 *   id = "custom_resource",
 *   label = @Translation("Customized REST Resource for Hand Surgery Resource"),
 *   uri_paths = {
 *     	"canonical" = "/custom/api",
 *		"https://www.drupal.org/link-relations/create" = "/custom/api"
 *   }
 * )
 */

class CustomResource extends ResourceBase {

	/**
	* Responds to entity GET requests.
	* @return \Drupal\rest\ResourceResponse
	*/

	public function get() {
		$response = ['message' => 'Hello, this is a rest service'];
		return new ResourceResponse($response);
	}

	/**
	* Responds to POST requests.
	* @return \Drupal\rest\ResourceResponse
	* Returns a list of bundles for specified entity.
	*
	* @throws \Symfony\Component\HttpKernel\Exception\HttpException
	*   Throws exception expected.
	*/

	public function post(array $data = []) {

        $response = array(
            "data" => $data,
        );

        $build = array(
            '#cache' => array(
                'max-age' => 0,
            ),
        );

        return (new ResourceResponse($response))->addCacheableDependency($build);
    }


	/* *
	* Responds to POST requests.
	*
	* @return \Drupal\rest\ResourceResponse
	*   The HTTP response object.
	*
	* @throws \Symfony\Component\HttpKernel\Exception\HttpException
	*   Throws exception expected.
	* /

	public function post($data) {

		// You must to implement the logic of your REST Resource here.
		$data1 = ['message' => 'Hello, this is a rest service and parameter is: test'];
		    
		$response = new ResourceResponse($data1);
		// In order to generate fresh result every time (without clearing 
		// the cache), you need to invalidate the cache.
		$response->addCacheableDependency($data1);
		return $response;
	} */

}