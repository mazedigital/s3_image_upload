<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once( EXTENSIONS . '/s3_image_upload/vendor/autoload.php');
	require_once(EXTENSIONS . '/s3_image_upload/lib/class.resourceimage.php');

	use Aws\S3\S3Client;
	use Aws\S3\Exception\S3Exception;

	Class fieldS3_File_Upload extends Field  implements ImportableField { //ExportableField, 

		function __construct() {
			parent::__construct();
			$this->_name = __('S3 File Upload');
			$this->_required = false;
			$this->_showcolumn = true;
			$this->_multiple = false;

			// Instantiate an S3 client
			$this->s3Client = S3Client::factory(array(
			    'key'    => Symphony::Configuration()->get('access-key-id', 's3_image_upload'),
			    'secret' => Symphony::Configuration()->get('secret-access-key', 's3_image_upload'),
			));
		}

		function canFilter(){
			return false;
		}

		function isSortable(){
			return false;
		}

		protected static function __cleanFilterString($string){
			$string = trim($string);

			return $string;
		}

		public function checkPostFieldData($data, &$message, $entry_id = null){

			/**
			 * For information about PHPs upload error constants see:
			 * @link http://php.net/manual/en/features.file-upload.errors.php
			 */
			$message = null;

			return self::__OK__;
		}


		private function getOriginalFileName($filename){
			$key_prefix = $this->get('key_prefix');
			if ( !empty($key_prefix) ){
				return $key_prefix . '/' . $filename;
			} else {
				return $filename;
			}
		}


		private function filenamePrefix($filename,$prefix){
			$key_prefix = $this->get('key_prefix');
			if ( !empty($key_prefix) ){
				return $key_prefix . '/' . $prefix . '/' . $filename;
			} else {
				return $prefix . '/' . $filename;
			}
		}

		private function filenamePostfix($filename,$postfix){
			$extension_pos = strrpos($filename, '.'); // find position of the last dot, so where the extension starts
			//the name of the image is sanitized through the handle up to 50 chars long
			$name = General::createHandle(substr($filename, 0, $extension_pos),50);
			return $name . $postfix . substr($filename, $extension_pos);
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null){
			$status = self::__OK__;
			// all uploads to be done via Javascript makes life simple :)

			return $data;
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL) {
			parent::displaySettingsPanel($wrapper, $errors);

			// get current section id
			$section_id = Administration::instance()->Page->_context[1];

			// bucket details
			$bucket_details = new XMLElement('div', NULL, array('class' => 'two columns'));
			$label = Widget::Label(__('Bucket Name'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][bucket]', $this->get('bucket')?$this->get('bucket'):''));
			if(isset($errors['bucket'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['bucket']));
			} else {
				$bucket_details->appendChild($label);
			};
			$label = Widget::Label(__('Key Prefix'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][key_prefix]', $this->get('key_prefix')?$this->get('key_prefix'):''));
			if(isset($errors['key_prefix'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['key_prefix']));
			} else {
				$bucket_details->appendChild($label);
			};
			$wrapper->appendChild($bucket_details);

			$bucket_details = new XMLElement('div', NULL, array('class' => 'two columns'));
			$label = Widget::Label(__('Region'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][region]', $this->get('region')?$this->get('region'):''));
			if(isset($errors['region'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['region']));
			} else {
				$bucket_details->appendChild($label);
			};
			$label = Widget::Label(__('Access Control (ACL)'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][acl]', $this->get('acl')?$this->get('acl'):''));
			if(isset($errors['acl'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['acl']));
			} else {
				$bucket_details->appendChild($label);
			};
			$wrapper->appendChild($bucket_details);
			$help = new XMLElement('p', __('Set the S3 Bucket details, and key prefix for uploaded files.'), array('class' => 'help'));
			$wrapper->appendChild($help);
		}

		function checkFields(&$errors, $checkForDuplicates=true) {

			// check if min fields are integers

			return parent::checkFields($errors, $checkForDuplicates);
		}

		function commit() {

			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();
			$fields['field_id'] = $id;
			$all_fields = array(
				'bucket',
				'key_prefix',
				'acl',
				'region',
			);
			foreach ($all_fields as $field) {
				$value = $this->get($field);
				if (!empty($value)) {
					$fields[$field] = $value;
				}
			}

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`filename` varchar(255) NOT NULL,
					`filepath` varchar(255) NOT NULL,
              		`mimetype` varchar(100) default null,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `filepath` (`filepath`),
					KEY `mimetype` (`mimetype`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL, $entry_id) {

			// append assets
			$assets_path = '/extensions/s3_image_upload/assets/';
			Administration::instance()->Page->addStylesheetToHead(URL . $assets_path . 'image_crop.css', 'screen', 120, false);
			Administration::instance()->Page->addStylesheetToHead(URL . $assets_path . 'dropzone.css', 'screen', 130, false);
			Administration::instance()->Page->addScriptToHead(URL . $assets_path . 'dropzone.js', 420, false);
			Administration::instance()->Page->addScriptToHead(URL . $assets_path . 'file_upload.js', 430, false);

			// initialize some variables
			$id = $this->get('id');
			$related_field_id = $this->get('related_field_id');
			$fieldname = 'fields' . $fieldnamePrefix . '['. $this->get('element_name') . ']' . $fieldnamePostfix;

			$wrapper->setAttribute('data-field-name',$fieldname);
			$wrapper->setAttribute('data-bucket',$this->get('bucket'));
			$wrapper->setAttribute('data-region',$this->get('region'));
			$wrapper->setAttribute('data-acl',$this->get('acl'));
			$wrapper->setAttribute('data-id',$this->get('id'));

			// main field label
			$label = new XMLElement('p', $this->get('label'), array('class' => 'label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			$wrapper->appendChild($label);

			// main upload container
			$dropzoneContainer = new XMLElement('div', "<span class='dropzone-click'>Drop files or click to upload</span>", array('class' => 'dropzone-container','data-file-upload' => 'yes'));
			$wrapper->appendChild($dropzoneContainer);

			if (isset($data['filename'])){
				if (!is_array($data['filename'])){
					$data['filename'] = array($data['filename']);
					$data['filepath'] = array($data['filepath']);
					$data['mimetype'] = array($data['mimetype']);
				}
				$wrapper->setAttribute('data-filenumber',sizeof($data['filename']));
				$previewContent = '';
				foreach ($data['filename'] as $key => $value) {
					$filesrc = $this->s3Client->getObjectUrl($this->get('bucket'),  $data['filepath'][$key]);
					$previewContent .= 	'<div class="dz-preview dz-file-preview">'.
											'<div class="dz-details">'.
												'<img data-dz-thumbnail />'.
												'<div class="dz-text-details">'.
													"<div class='dz-filename'><span data-dz-name><a href='{$filesrc}' target='_blank'>{$data['filename'][$key]}</a></span></div>".
													'<div class="dz-size" data-dz-size></div>'.
												'</div>'.
											'</div>'.
										'</div>' . 

										"<input name='{$fieldname}[filename][{$key}]' value='{$data['filename'][$key]}' type='hidden'/>" .
										"<input name='{$fieldname}[filepath][{$key}]' value='{$data['filepath'][$key]}' type='hidden'/>" .
										"<input name='{$fieldname}[mimetype][{$key}]' value='{$data['mimetype'][$key]}' type='hidden'/>" ;
				}
			}

			$filePreviewContainer = new XMLElement('div', $previewContent , array('class' => 'file-preview dropzone-previews'));
			$wrapper->appendChild($filePreviewContainer);
		}

		function prepareTableValue($data, XMLElement $link=NULL, $entry_id = NULL){

			if (isset($entry_id) && isset($data['filename'])) {
				$url = $this->s3Client->getObjectUrl($this->get('bucket'), $data['filepath']);

				$file = '<a href="' . $url . '">'.$data['filename'].'</a>>';
			} else {
				return parent::prepareTableValue(NULL);
			}

			if($link){
				$link->setValue($data['filename']);
				return $link->generate();
			} else return $file;

		}

		function prepareReadableValue($data, $entry_id){
			return $this->preparePlainTextValue($data, $entry_id);
		}

		function preparePlainTextValue($data, $entry_id){
			return $data['filename'];
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			
			$element = new XMLElement($this->get('element_name'));

			foreach ($data['filename'] as $key => $value) {
				$filesrc = $this->s3Client->getObjectUrl($this->get('bucket'),  $data['filepath'][$key]);

				$file = new XMLElement('file',null,array('source'=>$filesrc,'mimetype'=> $data['mimetype'][$key]));
				$element->appendChild($file);
			}

			$wrapper->appendChild($element);

		}

		/*-------------------------------------------------------------------------
			Import:
		-------------------------------------------------------------------------*/

			public function getImportModes() {
				return array(
					'getValue' =>		ImportableField::STRING_VALUE,
					'getPostdata' =>	ImportableField::ARRAY_VALUE
				);
			}

			public function prepareImportValue($data, $mode, $entry_id = null) {
				$message = $status = null;
				$modes = (object)$this->getImportModes();


				if($mode === $modes->getValue) {

					$type = pathinfo($path, PATHINFO_EXTENSION);
					$gateway = new Gateway;
					$gateway->init($data);
					$result = $gateway->exec();
					$info = $gateway->getInfoLast();
					if ($info['http_code'] != 200){
						return null; //image does not exist
					}
					
					$base64 = 'data:' . $info['content_type'] . ';base64,' . base64_encode($result);
					//create new data array
					$newData = array(
							'image' => $base64,
							'imagename' => basename($data),
							'left' => 0,
							'top' => 0,
							'width' => 100,
							'height' => 100,
							'crop_position' => "crop-center crop-middle",
						);
					return $newData;
				}
				else if($mode === $modes->getPostdata) {
					return $this->processRawFieldData($data, $status, $message, true, $entry_id);
				}

				return null;
			}


	}
