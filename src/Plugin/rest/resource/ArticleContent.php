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


/**
 * Provides a Custom Resource
 *
 * @RestResource(
 *   id = "rest_article_content",
 *   label = @Translation("Customized REST Resource for getting article details"),
 *   uri_paths = {
 *     	"canonical" = "/custom/get_article/{article_id}"
 *   }
 * )
 */

class ArticleContent extends ResourceBase {

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

	
	private $bookmarks = [];

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

	public function get($article_id) {

		$response = [];
		$type = $this->currentRequest->query->get('type') == 'pdf' ? 'pdf' : 'normal';

		if($type == 'pdf'){
			if (!class_exists('\Mpdf\Mpdf')) {			   
			   throw new UnprocessableEntityHttpException('Mpdf library is not availabe');
			}

			$dir = 'sites/default/files/article_pdf/';
			$valid_dir = file_prepare_directory($dir, FILE_CREATE_DIRECTORY);

			$dir1 = 'sites/default/files/article_pdf/tmp/';
			$valid_dir1 = file_prepare_directory($dir1, FILE_CREATE_DIRECTORY);

			if (!$valid_dir) {			   
			   throw new UnprocessableEntityHttpException('Failed to create a directory \'article_pdf\' inside \'sites/default/files\'. Please create it manually with the permission 777.');
			}

			if (!$valid_dir1) {			   
			   throw new UnprocessableEntityHttpException('Failed to create a tmp directory inside \'article_pdf\'. Please create it manually with the permission 777.');
			}

			$html = '';
			$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4','tempDir' => $dir1]);
			$mpdf->SetDisplayMode('fullpage');
		}

		$term = \Drupal\taxonomy\Entity\Term::load($article_id);

		if(!$term){
			throw new BadRequestHttpException('Invalid \'article id\'');
		}

		$cat = $term->bundle();

		//$cat != PageContent::DIAGNOSIS_MACHINE_NAME && $cat != PageContent::TEST_AND_SIGN_MACHINE_NAME && $cat != PageContent::DIAGNOSTIC_STUDIES_MACHINE_NAME
		if($cat != 'diagnosis' && $cat != 'exams_and_signs_list' && $cat != 'diagnostic_studies' && $cat != 'hand_therapy_library'){
			throw new BadRequestHttpException('Article from invalid vocabulary');
		}		

		$details = $term->toArray();		

		if($details['status'][0]['value'] != '1'){
			throw new BadRequestHttpException('Article is not published');
		}		

		$response['tid'] = $details['tid'];
		$response['uuid'] = $details['uuid'];
		$response['vid'] = $details['vid'];
		$response['name'] = $details['name'];

		$bookmarks = \Drupal\handsurgeryresource_module\Plugin\rest\resource\FlagTerm::get_bookmarked_term_ids();
		$response['is_bookmarked'] = in_array($article_id, $bookmarks) ? "1" : "0";

		$fields = array(
			'diagnosis' => array(
				'field_clinical_presentation_phot',
				'field_basic_science_photos',
				'field_pathoanatomy_photos',
				'field_symptoms',
				'field_typical_history',
				'field_positive_tests_exams',
				'field_work_up_options',
				'field_images',
				'field_treatment_goals',
				'field_conservative',
				'field_operative',
				'field_treatment_photos_and_diagr',
				'field_cpt_code_agreement',
				'field_cpt_codes_for_treatment',
				'field_cpt_code_references',
				'field_hand_therapy',
				'field_hand_therapy_photos',
				'field_complications',
				'field_outcomes',
				'field_videos',
				'field_youtube_videos',
				'field_key_educational_points',
				'field_practice_and_cme',
				'field_references',
				'field_icd_10_new'
			),
			'exams_and_signs_list' => array(
				'field_additional_information',
				'field_presentation_photos',
				'field_definition_positive',
				'field_definition_negative',
				'field_comments_and_pearls',
				'field_diagnoses_associated_with',
				'field_videos',
				'field_youtube_videos',
				'field_references'
			),
			'diagnostic_studies' => array(
				'field_study_photos',
				'field_study_videos',
				'field_upload_docs',
				'field_diagnoses_where_this_study',
				'field_comments_and_pearls',
				'field_references'
			),
			'hand_therapy_library' => array(
				'field_diagnoses_where_this_inter',
				'field_comments_and_pearls',
				'field_images',
				'field_references',
				'field_videos'
			)
		);

		$field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $cat);
		
		/*
		$view_modes = \Drupal::service('entity_display.repository')->getViewModeOptionsByBundle(
		    'taxonomy_term', $cat
		);

		// Get format settings for field for all view_mode
		foreach (array_keys($view_modes) as $view_mode) { echo "fetches all view mode config"; }
		*/

		//get 'default' view mode settings only
		$default_view_all_settings = \Drupal::service('entity_type.manager')
				    ->getStorage('entity_view_display')
				    ->load('taxonomy_term.' . $cat . '.default');
				    //->getRenderer($value)->getSettings();
		$default_view_array = $default_view_all_settings->toArray();

		$field_groups = $default_view_array['third_party_settings']['field_group'];
		$active_fields = array_keys($default_view_array['content']);
		$hidden_fields = array_keys($default_view_array['hidden']);
		$all_fields = [];
		$processed_fields = [];

		foreach ($field_groups as $fgkey => $field_group) {
			
			$processed_fields = array_merge($processed_fields, $field_group['children']);	

			// display configured only for fields coming inside 'fieldset' group
			if($field_group['format_type'] !== 'fieldset'){
				continue;
			}

			//checking group is disabled or empty
			if($field_group['region'] == 'hidden' || count($field_group['children']) == 0 ){
				continue;
			}

			$group_response = [];
			$group_response['key'] = $fgkey;
			$group_response['heading'] = $field_group['label'];
			$group_response['weight'] = $field_group['weight'];
			//$group_response['format_settings'] = $field_group['format_settings'];
			$group_response['fields'] = [];
			$field_array = [];

			foreach ($field_group['children'] as $value) {
				
				if ( isset($field_definitions[$value]) && in_array($value, $active_fields) && !in_array($value, $hidden_fields) ){
					
					$field_array[$value] = [];
					$field_array[$value]['key'] = $value;
					$field_array[$value]['title'] = $field_definitions[$value]->getLabel();
					$field_array[$value]['type'] = $field_definitions[$value]->getType();
					$field_array[$value]['weight'] = $default_view_array['content'][$value]['weight'];
					$field_array[$value]['multiple'] = false;
					$field_array[$value]['values'] = [];

					$field_response = $this->process_field($value, $details);
					
					$field_array[$value]['multiple'] = $field_response['multiple'];
					$field_array[$value]['values'] = array_values($field_response['field_values']);

				}

			}
			$group_response['fields'] = array_values($field_array);
			$all_fields[$fgkey] = $group_response;			
		}

		// These lines are fetching non grouped(field group) fields
		$seperate_fields = array_diff($active_fields, $processed_fields);
		
		foreach ($seperate_fields as $svalue) {

			if ( isset($field_definitions[$svalue]) ){
				
				$single_field = [];
				$single_field['key'] = $svalue;
				$single_field['title'] = $field_definitions[$svalue]->getLabel();
				$single_field['type'] = $field_definitions[$svalue]->getType();
				$single_field['weight'] = $default_view_array['content'][$svalue]['weight'];
				$single_field['multiple'] = false;
				$single_field['values'] = [];

				$field_response = $this->process_field($svalue, $details);
				
				$single_field['multiple'] = $field_response['multiple'];
				$single_field['values'] = array_values($field_response['field_values']);
				
				$all_fields[$svalue] = $single_field;
			}
		}
		
		uasort($all_fields, 'Drupal\Component\Utility\SortArray::sortByWeightElement');		
		$response['items'] = array_values($all_fields);

		if($type == 'pdf'){
			
			$html = '
			<style>
			body{
			    margin:0;
			    color: #333;
			    font-family: "Open Sans", sans-serif;
			}
			.container{
			    width:100%;
			    height:auto;
			    border:0;
			}
			.head{
			    width: 100%;
			    border-bottom: 1px solid #eeeeee;
			    margin-bottom: 20px;
			    height: 100px;
			}
			.logo, .address{
			    float: left;
			}
			.logo{
			    width: 15%;
			    position:relative;
			}			
			.address{
			    width: 85%;			    
			}
			.address h3{
			    margin-top:20px;
			    margin-bottom:5px;
			    padding-top:5px;
			}
			.address p{
				margin-top: 5px;
			}
			img.pdf-img{
				max-width: 45%;
    			margin-bottom: 20px;
    			margin-right: 2%;
    			vertical-align: top;
			}
			.pdf-label{
				margin-bottom: 10px;
			}
			.pdf-agree input{
				display: none!important;
			}
			.pdf-code-group{
				margin-bottom: 25px;
			}
			.pdf-copyright{
				margin-top: 40px;
    			border-top: 1px solid #eeeeee;
    			padding-top: 12px;
			}
			    
			</style>';

			$html .= '<div class="container">';

			$html .= '<body>
			<div class="head" style="clear: both;">        
		        <div class="address">
		            <h3>Hand Surgery Resource, LLC</h3>
		            <p><a href="https://www.handsurgeryresource.org/">https://www.handsurgeryresource.org/</a></p>
		        </div>
		        <div class="logo">
		            <img class="img" height="60" src="/sites/default/files/images/HSRGuyInfoR.png" />		        	
		        </div>		        
		    </div>';

		    $html .= '<div class="article-title" style="padding-bottom:1px;text-decoration: underline;"><h3>'.$details['name'][0]['value'].'</h3></div>';

			foreach ($response['items'] as $rkey => $rvalue) {
				
				if(substr( $rvalue['key'], 0, 6) == 'group_'){
					
					$html .= '<div class="single-field">';
					$html .= '<h4>'.$rvalue['heading'].'</h4>'; 
					foreach ($rvalue['fields'] as $rvvalue) {
						$html = $this->process_field_pdf($rvvalue['key'], $rvvalue, $html);
					}
					$html .= '</div>';

				}
				else{

					$html .= '<div class="single-field">';
					$html .= '<h4>'.$rvalue['title'].'</h4>';
					$html = $this->process_field_pdf($rvalue['key'], $rvalue, $html);					
					$html .= '</div>';
				}				

			}
			$html .= '<div class="pdf-copyright">Copyright '.date('Y').' All rights reserved</div>';
			$html .= '</div></body>';
			
			if($valid_dir){

				global $base_url;
				$response = [];
			
				$filename = time().'-'.rand(10000, 99999).'.pdf';
				$mpdf->WriteHTML($html);
				$mpdf->Output($dir.'/'.$filename, 'F');

				if (file_exists($dir.'/'.$filename)) {
				    $response['status'] = 'success'; 
				    $response['file'] = $base_url.'/'.$dir.'/'.$filename; 
				} else {
				    throw new UnprocessableEntityHttpException('Failed to create the pdf.');				    
				}				
			}
		};

		// $response = array_merge($response, $all_fields);
		
		// var_dump(json_decode(json_encode($response)));		    			
		// header("Content-type: application/json");
		// echo json_encode($default_view_array);		
		// exit;				

		// print "<pre>";
		// print_r($response);
		// die;

		$build = array(
            '#cache' => array(
                'max-age' => 0,
            ),
        );
		
		return (new ResourceResponse($response))->addCacheableDependency($build);
		
	}

	private function process_field_pdf($value, $details, $html = ''){		
		if(	$value == 'description' ){
			$html .= $details['values'][0]['processed'];
		}
		elseif(	$value == 'field_clinical_presentation_phot' || 
			$value == 'field_basic_science_photos' ||
			$value == 'field_pathoanatomy_photos' || 
			$value == 'field_images' ||
			$value == 'field_treatment_photos_and_diagr' ||
			$value == 'field_hand_therapy_photos' ||
			$value == 'field_videos' ||
			$value == 'field_youtube_videos' ||
			$value == 'field_presentation_photos' ||
			$value == 'field_study_videos'
		){
			foreach($details['values'] as $clitem){

				foreach ($clitem as $fclkey => $fclfield){
					
					switch ($fclkey) {

						case 'field_clinical_presentation_labe':
						case 'field_basic_science_label':
						case 'field_pathoanatomy_label':
						case 'field_images_label':
						case 'field_treatment_label':
						case 'field_hand_therapy_label':
						case 'field_video_title':
						case 'field_presentation_label':

							$html .= '<div class="pdf-label">'.$fclfield.'</div>';
							break;

						case 'field_clinical_presentation_pics':
						case 'field_basic_science_pics':
						case 'field_pathoanatomy_pics':
						case 'field_images_upload':
						case 'field_treatment_photos':
						case 'field_hand_therapy_pics':
						case 'field_presentation_pics':

							foreach ($fclfield as $imkey => $imvalue) {								
								$html .= '<img class="pdf-img" src="'.$imvalue['url'].'"/>';
							}
							break;
							
						case 'field_upload_video':							
							$cl_files = [];
							foreach ($fclfield as $imvalue) {								
								$html .= '<a href="'.$imvalue['url'].'" target="_blank">'.$imvalue['name'].'</a>';
							}							
							break;
							
						case 'field_video_title':
							$html .= '<span>'.$fclfield.':</span> ';
							break;

						case 'field_youtube_video_input':
							$html .= ' <span><a href="'.$fclfield.'" target="_blank">'.$fclfield.'</a></span><br/>';										
							break;
						
						default:									
							break;

					}					
			  						            
		        }

			}
			
		}
		elseif(	$value == 'field_symptoms_1' ){
			foreach($details['values'] as $clitem){				
				$html .= '<div class="pdf-label">'.$clitem['value'].'</div>';
			}
		}				
		elseif(	$value == 'field_typical_history' ||
			$value == 'field_treatment_goals' ||
			$value == 'field_conservative' ||
			$value == 'field_operative' ||
			$value == 'field_hand_therapy' ||
			$value == 'field_complications' ||
			$value == 'field_outcomes' ||
			$value == 'field_key_educational_points' ||
			$value == 'field_practice_and_cme' ||
			$value == 'field_references' ||
			$value == 'field_definition_positive' ||
			$value == 'field_definition_negative' ||
			$value == 'field_comments_and_pearls'
		){ 
			$html .= $details['values'][0]['processed'];
		}
		elseif(	$value == 'field_positive_tests_exams' ||
			$value == 'field_work_up_options' ||
			$value == 'field_additional_information' ||
			$value == 'field_diagnoses_associated_with' ||
			$value == 'field_diagnoses_where_this_study'
		){
			foreach($details['values'] as $clitem){	
				$html .= '<div class="pdf-label">'.$clitem['name'].'</div>';
			}
		}
		elseif(	$value == 'field_cpt_code_agreement' || 
			$value == 'field_cpt_code_references'
		){
			$strng = preg_replace("/<input[^>]+\>/i", "", $details['values'][0]['processed']);
			$html .= '<div class="pdf-agree">'.$strng.'</div>';			
		}
		elseif(	$value == 'field_cpt_codes_for_treatment' ){			
			foreach($details['values'] as $clitem){	
				$html .= '<div class="pdf-code-group">';
				$html .= '<div class="pdf-label"><strong>Common Procedure Name : </strong>'.$clitem['name'].'</div>';
				$html .= '<div class="pdf-label"><strong>CPT Description : </strong>'.$clitem['description'].'</div>';
				$html .= '<div class="pdf-label"><strong>CPT Code Number : </strong>'.$clitem['code'].'</div>';
				$html .= '</div>';
			}
		}
		elseif(	$value == 'field_study_photos' ){
			foreach ($details['values'] as $clitem) {								
				$html .= '<img class="pdf-img" src="'.$clitem['url'].'"/>';
			}
		}
		elseif(	$value == 'field_upload_docs' ){
			foreach ($details['values'] as $clitem) {								
				$html .= '<a href="'.$clitem['url'].'" target="_blank">'.$clitem['name'].'</a>';
			}			
		}
		elseif(	$value == 'field_icd_10_new'){
			foreach ($details['values'] as $clitem) {								
				foreach ($clitem['fields'] as $ikey => $ivalue) {
					$html .= '<div class="pdf-label"><strong>'.$ivalue['title'].'</strong></div>';
					$html .= '<div class="pdf-label">'.$ivalue['value'].'</div>';
				}
			}			
		}

		return $html;

	}

	private function process_field($value, $details){
		
		$field_values = [];
		$multiple = false;

		if(	$value == 'description' ){
			$details['description'][0]['processed'] = check_markup($details['description'][0]['value'], $details['description'][0]['format']);
			//$term->get("description")->processed->__toString();
			$field_values = $details['description'];
		}
		elseif(	$value == 'field_clinical_presentation_phot' || 
			$value == 'field_basic_science_photos' ||
			$value == 'field_pathoanatomy_photos' || 
			$value == 'field_images' ||
			$value == 'field_treatment_photos_and_diagr' ||
			$value == 'field_hand_therapy_photos' ||
			$value == 'field_videos' ||
			$value == 'field_youtube_videos' ||
			$value == 'field_presentation_photos' ||
			$value == 'field_study_videos'
		){
			$multiple = true;
			foreach($details[$value] as $clkey => $clitem){
				//field_youtube_video':				
				$id = $clitem['target_id'];
				if ($id) {
					$cl_object = \Drupal\field_collection\Entity\FieldCollectionItem::load($id);
					//$cl_object_array = $cl_object->toArray();
					$field_values[$id] = [];							
					
					foreach ($cl_object as $fclkey => $fclfield){
						
						switch ($fclkey) {

							case 'item_id':
							case 'field_clinical_presentation_label':
							case 'field_basic_science_label':
							case 'field_pathoanatomy_label':
							case 'field_images_label':
							case 'field_treatment_label':
							case 'field_hand_therapy_label':
							case 'field_video_title':
							case 'field_presentation_label':

								$field_values[$id][$fclkey] = $fclfield->value;
								break;

							case 'field_clinical_presentation_pics':
							case 'field_basic_science_pics':
							case 'field_pathoanatomy_pics':
							case 'field_images_upload':
							case 'field_treatment_photos':
							case 'field_hand_therapy_pics':
							case 'field_presentation_pics':

								//$field_values[$id][$fclkey] = $fclfield;
								$cl_images = [];
								foreach ($fclfield as $imkey => $imvalue) {
									
									$image = \Drupal\file\Entity\File::load($imvalue->target_id);
									
									if($image){
										$cl_images[] = array(
											'target_id' => $imvalue->target_id,
											'alt' => $imvalue->alt,
											'title' => $imvalue->title,
											'width' => $imvalue->width,
											'height' => $imvalue->height,												
											'url' => $image->createFileUrl(FALSE),
											'name' => $image->getFilename(),
											'mime_type' => $image->getMimeType()											
										);
									}
									
								}
								$field_values[$id][$fclkey] = $cl_images;
								break;

							case 'field_upload_video':
								
								$cl_files = [];
								foreach ($fclfield as $imkey => $imvalue) {
									
									$file = \Drupal\file\Entity\File::load($imvalue->target_id);
									
									if($file){
										$cl_files[] = array(
											'target_id' => $imvalue->target_id,
											'url' => $file->createFileUrl(FALSE),
											'name' => $file->getFilename(),
											'mime_type' => $file->getMimeType(),
											'size' => $file->getSize()												
										);
									}
									
								}
								$field_values[$id][$fclkey] = $cl_files;
								break;

							case 'field_video_title':									
								$field_values[$id][$fclkey] = $fclfield->value;										
								break;

							case 'field_youtube_video':
								
								$field_values[$id][$fclkey.'_input'] = $fclfield->input;
								$field_values[$id][$fclkey.'_video_id'] = $fclfield->video_id;										
								break;		
							
							default:									
								break;

						}					
													
					}
				}

			}
			
		}
		elseif(	$value == 'field_symptoms' ){
			$multiple = true;
			$field_values = $details[$value];
		}				
		elseif(	$value == 'field_typical_history' ||
			$value == 'field_treatment_goals' ||
			$value == 'field_conservative' ||
			$value == 'field_operative' ||
			$value == 'field_hand_therapy' ||
			$value == 'field_complications1' ||
			$value == 'field_outcomes' ||
			$value == 'field_key_educational_points' ||
			$value == 'field_practice_and_cme' ||
			$value == 'field_references' ||
			$value == 'field_definition_positive' ||
			$value == 'field_definition_negative' ||
			$value == 'field_comments_and_pearls'
		){ 
			if(isset($details[$value][0]['value']))
				$details[$value][0]['processed'] = check_markup($details[$value][0]['value'], 'full_html');
			$field_values = $details[$value];
		}
		elseif(	$value == 'field_positive_tests_exams' ||
			$value == 'field_work_up_options' ||
			$value == 'field_additional_information' ||
			$value == 'field_diagnoses_associated_with_' ||
			$value == 'field_diagnoses_where_this_study'
		){
			$multiple = true;
			foreach($details[$value] as $clkey => $clitem){
				
				$term_id = $clitem['target_id'];
				$rel_term = \Drupal\taxonomy\Entity\Term::load($term_id);
				
				if($rel_term){
					$field_values[] = array(
						'id' => $clitem['target_id'],
						'name' => $rel_term->getName(),
						'published' => $rel_term->status[0]->value
					);							
				}

			}

		}
		elseif(	$value == 'field_cpt_code_agreement' || 
			$value == 'field_cpt_code_references'
		){
			foreach($details[$value] as $pkey => $pitem){
				
				$pfield_name = $value;
				$markup = 'code_view_only';

				if($value == 'field_cpt_code_references'){ 
					$pfield_name = 'field_cpt_code_references';
					$markup = 'full_html';
				}	
				 
				$p = \Drupal\paragraphs\Entity\Paragraph::load( $pitem['target_id'] );
				
					$text = $p->{$pfield_name}->getValue();
					$text[0]['processed'] = check_markup($text[0]['value'], $markup);
					$field_values[] = $text[0];
			}
		}
		elseif(	$value == 'field_cpt_codes_for_treatment' ){
			
			$multiple = true;			
			foreach($details[$value] as $pkey => $pitem){
				
				$pid = $pitem['target_id'];
				$pfield_name = $value;

				$p = \Drupal\paragraphs\Entity\Paragraph::load($pid);
				$field_values[$pid] = array(
					'name' => $p->field_common_procedure_name ? $p->field_common_procedure_name->getValue()[0]['value'] : '',
					'description' => $p->field_cpt_description ? $p->field_cpt_description->getValue()[0]['value'] : '',
					'code' => $p->field_cpt_code_number ? $p->field_cpt_code_number->getValue()[0]['value'] : ''
				);							
			}
		}
		elseif(	$value == 'field_study_photos' ){

			$multiple = true;
			foreach($details[$value] as $imkey => $imvalue){
				
				$imid = $imvalue['target_id'];
				$image = \Drupal\file\Entity\File::load($imid);
				
				if($image){
					$field_values[$imid] = array(
						'target_id' => $imid,
						'alt' => $imvalue['alt'],
						'title' => $imvalue['title'],
						'width' => $imvalue['width'],
						'height' => $imvalue['height'],												
						'url' => $image->createFileUrl(FALSE),
						'name' => $image->getFilename(),
						'mime_type' => $image->getMimeType()											
					);
				}
			}
		}
		elseif(	$value == 'field_upload_docs' ){

			$multiple = true;
			foreach($details[$value] as $fkey => $fvalue){
				
				$fid = $fvalue['target_id'];
				$file = \Drupal\file\Entity\File::load($fid);
								
				if($file){
					$field_values[$fid] = array(
						'target_id' => $fid,
						'url' => $file->createFileUrl(FALSE),
						'name' => $file->getFilename(),
						'mime_type' => $file->getMimeType(),
						'size' => $file->getSize()											
					);
				}
			}
		}
		elseif(	$value == 'field_icd_10_new'){
			
			$multiple = true;
			$field_values = $this->get_icd_data($details,$value);
		}
		
		return array('field_values' => $field_values, 'multiple' => $multiple);

	}

	private function get_icd_data($details = [], $value = ''){

		$field_values = [];
		$icd_term_id = 'icd_10';
		$icd_paragraph_id = 'icd_10';
		$field_icd_10_info = 'field_icd_10';

		$icd_fields = array(
			'field_diagnostic_guide_name' ,
			'field_icd_10_diagnosis',
			'field_instructions_icd_10',
			'field_icd_10_references'
		);

		$icd_field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $icd_paragraph_id);
		$icd_default_view_array = [];
		$icd_default_view_all_settings = \Drupal::service('entity_type.manager')
				->getStorage('entity_view_display')
				->load('paragraph.' . $icd_paragraph_id . '.default');

		if($icd_default_view_all_settings)  
			$icd_default_view_array = $icd_default_view_all_settings->toArray();

		$icd_field_groups = $icd_default_view_array['third_party_settings']['field_group'];
		$hidden_icd_fields = array_keys($icd_default_view_array['hidden']);

		$filled_fields = [];
		$icd_global = [];

		foreach ($icd_field_groups as $icdfg_key => $icdfg_value) {
			
			if($icdfg_value['format_type'] !== 'html_element' || $icdfg_value['region'] == 'hidden' || count($icdfg_value['children']) == 0){
				continue;
			}

			foreach ($icdfg_value['children'] as $icd_child) {
				
				if(!in_array($icd_child, $icd_fields) || in_array($icd_child, $hidden_icd_fields))
					continue;

				$icd_global[] = array(
					'key' => $icd_child,
					'title' => $icdfg_value['label'],
					'weight' => $icdfg_value['weight'],
					'value' => ''
				);
				$filled_fields[] = $icd_child;
			}	
		}
		$non_filled = array_diff($icd_fields, $filled_fields);

		foreach ($non_filled as $non_value) {
			
			if(in_array($non_value, $hidden_icd_fields))
				continue;

			$icd_global[] = array(
				'key' => $non_value,
				'title' => '',
				'weight' => $icd_default_view_array['content'][$non_value]['weight'],
				'value' => ''
			);
		}
		uasort($icd_global, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

		// available only for enabled $icd_fields
		if(!empty($icd_global)){				

			foreach($details[$value] as $clkey => $clitem){
				
				$term_id = $clitem['target_id'];
				$icd_term = \Drupal\taxonomy\Entity\Term::load($term_id);
				
				if($icd_term){
					$icd_cat = $icd_term->bundle();

					if($icd_cat != $icd_term_id || $icd_term->status[0]->value != '1') 
						continue;

					$icd_term_det = $icd_term->toArray();
					
					$field_values[$clkey]['id'] = $term_id;
					$field_values[$clkey]['name'] = $icd_term_det['name'][0]['value'];
					$field_values[$clkey]['fields'] = [];

					if(is_array($icd_term_det[$field_icd_10_info]) && isset($icd_term_det[$field_icd_10_info][0])){
						
						//not allowed for multi input
						//foreach ($icd_term_det[$field_icd_10_info] as $icdkey => $icdvalue) {
							
							$icd_p = \Drupal\paragraphs\Entity\Paragraph::load($icd_term_det[$field_icd_10_info][0]['target_id']);
							if($icd_p){
								
								$icd_p = $icd_p->toArray();

								$ret = $icd_global;
								foreach ($ret as $retkey => $retval) {										
									$icd_single = $icd_p[$retval['key']];
									if($icd_single){
										$fieldval = $icd_single[0]['value'];
										$ret[$retkey]['value'] = check_markup($fieldval, 'full_html');
									}
								}

								$field_values[$clkey]['fields'] = array_values($ret);								

							}							

						//}
						
					}			
															
				}

			}

		}

		return $field_values;
		
	}

}
