<?php
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');


	require_once( EXTENSIONS . '/s3_image_upload/vendor/autoload.php');
	require_once(EXTENSIONS . '/s3_image_upload/lib/class.resourceimage.php');

	use Aws\S3\S3Client;
	use Aws\S3\Exception\S3Exception;

	Class fieldS3_Image_Upload extends Field  implements ImportableField { //ExportableField, 

		const CROPPED = 0;
		const WIDTH = 1;
		const HEIGHT = 2;
		const RATIO = 3;
		const ERROR = 4;

		function __construct() {
			parent::__construct();
			$this->_name = __('S3 Image Upload');
			$this->_required = false;
			$this->_showcolumn = true;


			// Instantiate an S3 client
			$this->s3Client = S3Client::factory(array(
			    'key'    => Symphony::Configuration()->get('access-key-id', 's3_image_upload'),
			    'secret' => Symphony::Configuration()->get('secret-access-key', 's3_image_upload'),
			));
		}

		function canFilter(){
			return true;
		}

		function isSortable(){
			return true;
		}

		//todo fix sorting
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`entry_id` $order");
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$parsed = array();

			foreach ($data as $string) {
				// $type = self::__parseFilter($string);

				if($type == self::ERROR) return false;

				if(!is_array($parsed[$type])) $parsed[$type] = array();

				$parsed[$type] = $string;
			}

			foreach($parsed as $type => $value){
				var_dump($parsed);die;
				$value = trim($value);

				switch($type){

					case self::CROPPED:
						$field_id = $this->get('id');
						$this->_key++;
						$joins .= "
							LEFT JOIN
								`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
								ON (e.id = t{$field_id}_{$this->_key}.entry_id)
						";
						$where .= "
							AND t{$field_id}_{$this->_key}.cropped = '{$value}'
						";
						break;

					case self::WIDTH:
						$field_id = $this->get('id');
						$this->_key++;
						$joins .= "
							LEFT JOIN
								`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
								ON (e.id = t{$field_id}_{$this->_key}.entry_id)
						";
						$where .= "
							AND t{$field_id}_{$this->_key}.width {$value}
						";
						break;

					case self::HEIGHT:
						$field_id = $this->get('id');
						$this->_key++;
						$joins .= "
							LEFT JOIN
								`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
								ON (e.id = t{$field_id}_{$this->_key}.entry_id)
						";
						$where .= "
							AND t{$field_id}_{$this->_key}.height {$value}
						";
						break;

					case self::RATIO:
						$field_id = $this->get('id');
						$this->_key++;
						$joins .= "
							LEFT JOIN
								`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
								ON (e.id = t{$field_id}_{$this->_key}.entry_id)
						";
						$where .= "
							AND t{$field_id}_{$this->_key}.ratio {$value}
						";
						break;
				}
			}

			return true;
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


			//fetch original image data
			if (!empty($data['image'])){
				$imgstr = $data['image'];
				$new_data=explode(";",$imgstr);
				$type=$new_data[0];
				$image_data=explode(",",$new_data[1]);

				$imageResource = imagecreatefromstring(base64_decode($image_data[1]));

				//get width and height and multiply by size of the crop by hte UI to get final width/height
				$width = round(imagesx($imageResource) * $data['width'] / 100);
				$height = round(imagesy($imageResource) * $data['height'] / 100);

				//TODO confirm that the min_width/min_height work properly
				if($this->get('min_width') > $width){
					$message = __('"%1$s" needs to have a width of at least %2$spx.', array($this->get('label'), $this->get('min_width')));
					return self::__INVALID_FIELDS__;
				}

				if($this->get('min_height') > $height){
					$message = __('"%1$s" needs to have a height of at least %2$spx.', array($this->get('label'), $this->get('min_height')));
					return self::__INVALID_FIELDS__;
				}
			}

			if (
				empty($data)
				|| (
					is_array($data)
					&& isset($data['error'])
					&& $data['error'] == UPLOAD_ERR_NO_FILE
				)
			) {
				if ($this->get('required') == 'yes') {
					$message = __('‘%s’ is a required field.', array($this->get('label')));

					return self::__MISSING_FIELDS__;
				}

				return self::__OK__;
			}

			// Its not an array, so just retain the current data and return
			if (is_array($data) === false) {
				$file = $this->getFilePath(basename($data));
				if (file_exists($file) === false || !is_readable($file)) {
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');

					return self::__INVALID_FIELDS__;
				}

				// Ensure that the file still matches the validator and hasn't
				// changed since it was uploaded.
				if ($this->get('validator') != null) {
					$rule = $this->get('validator');

					if (General::validateString($file, $rule) === false) {
						$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array(
							$this->get('label')
						));

						return self::__INVALID_FIELDS__;
					}
				}

				return self::__OK__;
			}

			if ($data['error'] != UPLOAD_ERR_NO_FILE && $data['error'] != UPLOAD_ERR_OK) {
				switch ($data['error']) {
					case UPLOAD_ERR_INI_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s specified by your host.', array($this->get('label'), (is_numeric(ini_get('upload_max_filesize')) ? General::formatFilesize(ini_get('upload_max_filesize')) : ini_get('upload_max_filesize'))));
						break;
					case UPLOAD_ERR_FORM_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s, specified by Symphony.', array($this->get('label'), General::formatFilesize($_POST['MAX_FILE_SIZE'])));
						break;
					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
						$message = __('File chosen in ‘%s’ was only partially uploaded due to an error.', array($this->get('label')));
						break;
					case UPLOAD_ERR_CANT_WRITE:
						$message = __('Uploading ‘%s’ failed. Could not write temporary file to disk.', array($this->get('label')));
						break;
					case UPLOAD_ERR_EXTENSION:
						$message = __('Uploading ‘%s’ failed. File upload stopped by extension.', array($this->get('label')));
						break;
				}

				return self::__ERROR_CUSTOM__;
			}

			// Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);

			if ($this->get('validator') != null) {
				$rule = $this->get('validator');

				if (!General::validateString($data['name'], $rule)) {
					$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array($this->get('label')));

					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}


		private function getOriginalImgName($filename){
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

		private function uploadResized($dst_w,$dst_h,$jit_position,$filename){
			$image = clone $this->image;

			$src_w = $image->Meta()->width;
			$src_h = $image->Meta()->height;

			$src_r = ($src_w / $src_h);

			if ($dst_h == 0){
				$dst_r = $src_r;
				$dst_h = $dst_w / $dst_r;
			} else if ($dst_w == 0){
				$dst_r = $src_r;
				$dst_w = $dst_h * $dst_r;
			} else {
				$dst_r = ($dst_w / $dst_h);
			}

			if ( $dst_w > $src_w || $dst_h > $src_h ){
				//the image is smaller than the wanted size, do not try to generate the resized image.
				return false;
			}

			//first resize
			if($src_r < $dst_r) {
				$image->applyFilter('resize', array($dst_w, NULL));
			}
			else {
				$image->applyFilter('resize', array(NULL, $dst_h));
			}

			//then crop
			$image->applyFilter('crop', array($dst_w, $dst_h, $jit_position));

			try {
			    $this->s3Client->upload($this->get('bucket'), $this->filenamePrefix($filename,$dst_w.'x'.$dst_h), $image->getStream(), 'public-read');
			    return true;
			} catch (S3Exception $e) {
    			echo 'Caught exception: ',  $e->getMessage(), "\n";die;
			    echo "There was an error uploading the file.\n";
			}
		}


		private function cropImage(&$data,$filename){
			//crop image to dimensions given by the field
			if (! ($data['width'] == $data['height'] && $data['height'] == 100)){
				$this->image->cropToDimensions($data['left'],$data['top'],$data['width'],$data['height']);
			}


			try {
				$this->s3Client->upload($this->get('bucket'), $this->filenamePrefix($filename,'cropped') , $this->image->getStream(), 'public-read');
			} catch (S3Exception $e) {
				echo "There was an error uploading the file.\n";
			}

			//fetch all dimensions from the field data
			$cropDimensions = explode(',',$this->get('crop_dimensions'));

			if (empty($data['crop_position'])){
				$jit_position = 1;
			} else {
				$cropClasses = explode(' ', $data['crop_position']);
				switch ($cropClasses[0]) {
					case 'crop-left':
						$jit_position = 1;
						break;
					case 'crop-center':
						$jit_position = 2;
						break;
					case 'crop-right':
						$jit_position = 3;
						break;
					}
				switch ($cropClasses[1]) {
					case 'crop-top':
						$jit_position += 0;
						break;
					case 'crop-middle':
						$jit_position += 3;
						break;
					case 'crop-bottom':
						$jit_position += 6;
						break;
					}
			}

			//for each listed dimension uploaded the resized images
			foreach ($cropDimensions as $key => $dimensions) {
				$dimension = explode('x', $dimensions);
				$result = $this->uploadResized($dimension[0],$dimension[1],$jit_position,$filename);
				if ($result){
					if (empty($data['crop_dimensions']))
						$data['crop_dimensions'] = $dimensions;
					else 
						$data['crop_dimensions'] .= ',' . $dimensions;
				}
			}
		}

		private function processImage($data,$filename=null){
			$imgstr = $data['image'];

			$new_data=explode(";",$imgstr);
			$type=$new_data[0];
			$image_data=explode(",",$new_data[1]);

			$imageResource = imagecreatefromstring(base64_decode($image_data[1]));

			if (!in_array($type, array('data:image/gif','data:image/png','data:image/jpeg'))){
				throw new Exception('Unsupported image type. Supported types: GIF, JPEG and PNG');
			}
			
    		// imagedestroy($imageResource);

			$this->image = ResourceImage::load($imageResource,$type);

			//filename structure = filename-timestamp.ext
			if (!isset($filename)){
				//make a 50 char handle of the original image name and append a timestamp.
				$filename =  $this->filenamePostfix($data['imagename'],'-'.time());
			}

			try {
				$key_prefix = $this->get('key_prefix');
				if (!empty($key_prefix))
					$uploadFilename = $key_prefix . '/' . $filename;
				else $uploadFilename = $filename;
				$this->s3Client->upload($this->get('bucket'), $uploadFilename, $this->image->getStream(), 'public-read');
			} catch (S3Exception $e) {
				echo "There was an error uploading the file.\n";
			}

			$this->cropImage($data,$filename);

			$toSave = array(
					'width' => $this->image->Meta()->width,
					'height' => $this->image->Meta()->height,
					'filename' => $filename,
					'crop_instructions' => implode(',', array($data['left'],$data['top'],$data['width'],$data['height'])),
					'supported_dimensions' => $data['crop_dimensions'],
					'crop_position' => $data['crop_position'],
				);
			
			return $toSave;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null){
			$status = self::__OK__;

			// No file given, save empty data:
			if ($data === null) {
				return null;
			}

			if (!empty($data['image'])){

				$filename = null;

				if (isset($entry_id)) {
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT * FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));

					if (empty($row) === false) {
						$result = $row;
						$filename = $result['filename'];
					}
				}
					
				$data = $this->processImage($data,$filename);

			} else {
				// Grab the existing entry data to preserve the MIME type and size information
				if (isset($entry_id)) {
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT * FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));

					if (empty($row) === false) {
						$result = $row;
						if ($result['crop_position'] != $data['crop_position'] || $result['crop_instructions'] != implode(',', array($data['left'],$data['top'],$data['width'],$data['height']))){
							$result['crop_position'] = $data['crop_position'];
							$result['crop_instructions'] = implode(',', array($data['left'],$data['top'],$data['width'],$data['height']));
							$this->image = ResourceImage::loadExternal( $this->s3Client->getObjectUrl($this->get('bucket'), $this->getOriginalImgName($result['filename'])) );
							$this->cropImage($data,$result['filename']);
						}
					}
				}

				return $result;				
			}

			if ($simulate && is_null($entry_id)) {
				return $data;
			}


			return $data;
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL) {
			parent::displaySettingsPanel($wrapper, $errors);

			// get current section id
			$section_id = Administration::instance()->Page->_context[1];

			// ratios
			$label = Widget::Label(__('Crop Dimensions <i>Optional</i>'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][crop_dimensions]', $this->get('crop_dimensions')));
			if(isset($errors['crop_dimensions'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['crop_dimensions']));
			} else {
				$wrapper->appendChild($label);
			};
			$ratios = array('50x50','150x50','2000x600','2000x4000','50x50,150x50,50x150');
			$filter = new XMLElement('ul', NULL, array('class' => 'tags'));
			foreach($ratios as $ratio) {
				$filter->appendChild(new XMLElement('li', $ratio));
			};
			$wrapper->appendChild($filter);
			$help = new XMLElement('p', __('Values such as the above can be comma separated, an image will be generated for each dimension.'), array('class' => 'help'));
			$wrapper->appendChild($help);

			// bucket details
			$bucket_details = new XMLElement('div', NULL, array('class' => 'two columns'));
			$label = Widget::Label(__('Bucket Name <i>Optional</i>'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][bucket]', $this->get('bucket')?$this->get('bucket'):''));
			if(isset($errors['bucket'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['bucket']));
			} else {
				$bucket_details->appendChild($label);
			};
			$label = Widget::Label(__('Key Prefix <i>Optional</i>'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][key_prefix]', $this->get('key_prefix')?$this->get('key_prefix'):''));
			if(isset($errors['key_prefix'])) {
				$bucket_details->appendChild(Widget::wrapFormElementWithError($label, $errors['key_prefix']));
			} else {
				$bucket_details->appendChild($label);
			};
			$wrapper->appendChild($bucket_details);
			$help = new XMLElement('p', __('Set the S3 Bucket details, and key prefix for uploaded images.'), array('class' => 'help'));
			$wrapper->appendChild($help);

			// minimal dimension
			$min_dimension = new XMLElement('div', NULL, array('class' => 'two columns'));
			$label = Widget::Label(__('Minimum width <i>Optional</i>'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][min_width]', $this->get('min_width')?$this->get('min_width'):''));
			if(isset($errors['min_width'])) {
				$min_dimension->appendChild(Widget::wrapFormElementWithError($label, $errors['min_width']));
			} else {
				$min_dimension->appendChild($label);
			};
			$label = Widget::Label(__('Minimum height <i>Optional</i>'));
			$label->addClass('column');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][min_height]', $this->get('min_height')?$this->get('min_height'):''));
			if(isset($errors['min_height'])) {
				$min_dimension->appendChild(Widget::wrapFormElementWithError($label, $errors['min_height']));
			} else {
				$min_dimension->appendChild($label);
			};
			$wrapper->appendChild($min_dimension);
			$help = new XMLElement('p', __('Set minimum dimensions for the cropped image.'), array('class' => 'help'));
			$wrapper->appendChild($help);

			$column = new XMLElement('div', NULL, array('class' => 'two columns'));

			$label = Widget::Label(__('Show Cropping UI'));
			$label->addClass('column');
			$column->appendChild($label);
			$input = Widget::Input('fields['.$this->get('sortorder').'][crop_ui]', 'yes', 'checkbox');
			$label->prependChild($input);

			if($this->get('crop_ui') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$this->appendShowColumnCheckbox($column);
			$wrapper->appendChild($column);
		}

		function checkFields(&$errors, $checkForDuplicates=true) {

			// check if min fields are integers
			$min_fields = array('min_width', 'min_height');
			foreach ($min_fields as $field) {
				$i = $this->get($field);
				if ($i != '' && !preg_match('/^\d+$/', $i)) {
					$errors[$field] = __('This has to be an integer.');
				}
			}

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
				'crop_dimensions',
				'min_width',
				'min_height',
				'crop_ui'
			);
			foreach ($all_fields as $field) {
				$value = $this->get($field);
				if (!empty($value)) {
					$fields[$field] = $value;
				} else {
					if ($field == 'crop_ui') $fields[$field] = 'no';
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
					`width` int(11) unsigned default NULL,
					`height` int(11) unsigned default NULL,
					`filename` varchar(255) NOT NULL,
					`crop_instructions` varchar(255) NOT NULL,
					`supported_dimensions` varchar(255) NOT NULL,
					`crop_position` varchar(255) NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL, $entry_id) {

			//TODO create error
			// if (isset($.))
			// 	var_dump($flagWithError);die;

			// append assets
			$assets_path = '/extensions/s3_image_upload/assets/';
			Administration::instance()->Page->addStylesheetToHead(URL . $assets_path . 'image_crop.css', 'screen', 120, false);
			Administration::instance()->Page->addStylesheetToHead(URL . $assets_path . 'dropzone.css', 'screen', 130, false);
			Administration::instance()->Page->addScriptToHead(URL . $assets_path . 'dropzone.js', 420, false);
			Administration::instance()->Page->addScriptToHead(URL . $assets_path . 'image_crop.js', 430, false);

			// initialize some variables
			$id = $this->get('id');
			$related_field_id = $this->get('related_field_id');
			$fieldname = 'fields' . $fieldnamePrefix . '['. $this->get('element_name') . ']' . $fieldnamePostfix;

			$wrapper->setAttribute('data-field-name',$fieldname);

			// main field label
			$label = new XMLElement('p', $this->get('label'), array('class' => 'label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			if (isset($data)){
				$cropData = explode(',',$data['crop_instructions']);
			} else {
				$cropData = array();
			}

			// var_dump($cropData);die;

			// hidden inputs
			$left = Widget::Input($fieldname.'[left]', ($cropData[0] ?: '0'), 'hidden');
			$label->appendChild($left);
			$top = Widget::Input($fieldname.'[top]', ($cropData[1] ?: '0'), 'hidden');
			$label->appendChild($top);
			$width = Widget::Input($fieldname.'[width]', ($cropData[2] ?: '100'), 'hidden');
			$label->appendChild($width);
			$height = Widget::Input($fieldname.'[height]', ($cropData[3] ?: '100'), 'hidden');
			$label->appendChild($height);
			$cropPosition = Widget::Input($fieldname.'[crop_position]', ($data['crop_position'] ?: 'crop-center crop-middle'), 'hidden');
			$label->appendChild($cropPosition);
			$image = Widget::Input($fieldname.'[image]', null, 'hidden');
			$label->appendChild($image);
			$imagename = Widget::Input($fieldname.'[imagename]', null, 'hidden');
			$label->appendChild($imagename);

			$wrapper->appendChild($label);

			// var_dump($data);die;

			// main upload and cropping container
			$dropzoneContainer = new XMLElement('div', "Drop images or click to upload", array('class' => 'dropzone-container','data-crop-ui' => $this->get('crop_ui')));
			$wrapper->appendChild($dropzoneContainer);

			if ($this->get('crop_ui') == 'yes'){

				if ($data['crop_instructions']){
					$imgsrc = $this->s3Client->getObjectUrl($this->get('bucket'),  $this->filenamePrefix($data['filename'],'cropped'));
					$originalImgName = $this->getOriginalImgName($data['filename']);
					$style = "left:{$cropData[0]}%;top:{$cropData[1]}%;width:{$cropData[2]}%;height:{$cropData[3]}%;";
					$html = "<div class='image-wrap pre-upload'>".
					  "<img id='source-img' src='". $this->s3Client->getObjectUrl($this->get('bucket'), $originalImgName) ."' crossOrigin='crossOrigin' data-dz-thumbnail='data-dz-thumbnail'/>".
					  "<div class='grid' style='". $style ."'>".
					    "<div class='row' data-row='1'>".
					      "<div class='col' data-pos='crop-left crop-top' data-jit='1'></div>".
					      "<div class='col' data-pos='crop-center crop-top' data-jit='2'></div>".
					      "<div class='col' data-pos='crop-right crop-top' data-jit='3'></div>".
					    "</div>".
					    "<div class='row' data-row='2'>".
					      "<div class='col' data-pos='crop-left crop-middle' data-jit='4'></div>".
					      "<div class='col' data-pos='crop-center crop-middle' data-jit='5'></div>".
					      "<div class='col' data-pos='crop-right crop-middle' data-jit='6'></div>".
					    "</div>".
					    "<div class='row' data-row='3'>".
					      "<div class='col' data-pos='crop-left crop-bottom' data-jit='7'></div>".
					      "<div class='col' data-pos='crop-center crop-bottom' data-jit='8'></div>".
					      "<div class='col' data-pos='crop-right crop-bottom' data-jit='9'></div>".
					    "</div>".
					  "</div>".						  
					"</div>";
				} else {
					$html = null;
					$imgsrc='';
				}
				$imageCropper = new XMLElement('div', $html, array('class' => 'image-crop-container'));
				$wrapper->appendChild($imageCropper);

				$cropCanvas = new XMLElement('canvas', NULL, array('id' => 'crop-canvas'));
				$wrapper->appendChild($cropCanvas);


				$imagePreviewContainer = new XMLElement('div', NULL, array('class' => 'image-preview-container'));

		        if ($flagWithError != null) {
		            $wrapper->appendChild(Widget::Error($imagePreviewContainer, $flagWithError));
		        } else {
		            $wrapper->appendChild($imagePreviewContainer);
		            $wrapper->appendChild(new XMLElement('div', NULL, array('style' => 'clear:both;')));
		        }

				//TEMP previews - these should have pre-set width/height attributes generated from the field settings
				$imagePreviewContainer->appendChild(new XMLElement('h4','Landscape'));
				$imagePreviewContainer->appendChild(new XMLElement('div',"<img src='{$imgsrc}' class='{$data['crop_position']}'/>",array('class'=>'landscape-preview')));
				$imagePreviewContainer->appendChild(new XMLElement('h4','Portrait'));
				$imagePreviewContainer->appendChild(new XMLElement('div',"<img src='{$imgsrc}' class='{$data['crop_position']}'/>",array('class'=>'portrait-preview')));
			} else {
				if ($data['crop_instructions']){
					$imgsrc = $this->s3Client->getObjectUrl($this->get('bucket'),  $this->filenamePrefix($data['filename'],'cropped'));
					$previewContent = "<div class='image-wrap pre-upload'><img src='{$imgsrc}' class='{$data['crop_position']}' data-dz-thumbnail='data-dz-thumbnail' /></div>";
				}	
				
				$imagePreviewContainer = new XMLElement('div', $previewContent , array('class' => 'image-preview no-crop'));
				$wrapper->appendChild($imagePreviewContainer);
			}

		}

		function prepareTableValue($data, XMLElement $link=NULL, $entry_id = NULL){


			if (isset($entry_id) && isset($data['filename'])) {
				$url = $this->s3Client->getObjectUrl($this->get('bucket'), $this->filenamePrefix($data['filename'],'50x50'));

				$image = '<img style="vertical-align: middle;" src="' . $url . '" alt="'.$this->get('label').' of Entry '.$entry_id.'"/>';
			} else {
				return parent::prepareTableValue(NULL);
			}

			if($link){
				$link->setValue($image);
				return $link->generate();
			} else return $image;

		}

		function prepareReadableValue($data, $entry_id){
			return $this->preparePlainTextValue($data, $entry_id);
		}

		function preparePlainTextValue($data, $entry_id){
			return $data['filename'];
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			
			
			$element = new XMLElement($this->get('element_name'));

			$dimensions = new XMLElement('supported-dimensions');
			foreach ( explode(',', $data['supported_dimensions']) as $key => $value) {
				$dimensions->appendChild(new XMLElement('image',$this->s3Client->getObjectUrl($this->get('bucket'),$this->filenamePrefix($data['filename'],$value)),array('dimension'=>$value)));
			}

			//add image as cropped by user
			$dimensions->appendChild(new XMLElement('image',$this->s3Client->getObjectUrl($this->get('bucket'),$this->filenamePrefix($data['filename'],'cropped')),array('dimension'=>'cropped')));

			$element->appendChild($dimensions);

			$element->setAttributeArray(array(
				// 'width' => $data['width'],
				// 'height' => $data['height'],
				'crop-position' => $data['crop_position'],
				'original' => $this->s3Client->getObjectUrl($this->get('bucket'),$this->getOriginalImgName($data['filename'])),
				'original-key' => $this->getOriginalImgName($data['filename'])
			));

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
					// var_dump(basename($data));die;
					// var_dump($info);
					// var_dump($result);die;

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
