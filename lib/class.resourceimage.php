<?php

	require_once(EXTENSIONS . '/jit_image_manipulation/lib/class.image.php');

	Class ResourceImage extends Image{

		public function __clone(){
			$copy = imagecreatetruecolor($this->Meta()->width, $this->Meta()->height);
			
			if($this->Meta()->type==IMAGETYPE_PNG){
				//must be a png
				imagealphablending($copy, false);
				imagesavealpha($copy, true);
			}

			imagecopy($copy, $this->_resource, 0, 0, 0, 0, $this->Meta()->width, $this->Meta()->height);

			$this->_resource = $copy;

			$colors = imagecolorsforindex($copy, $rgb);
			$meta = array();
			$meta['width'] = imagesx($copy);
			$meta['height'] = imagesy($copy);
			$meta['channels'] = count($colors);
			$meta['type'] = $this->Meta()->type;
			$this->_meta = (object)$meta;
			// $this->_meta = clone $this->_meta;
		}

		public static function loadExternal($uri){
			// create the Gateway object
			$gateway = new Gateway();
			// set our url
			$gateway->init($uri);
			// set some options
			$gateway->setopt(CURLOPT_HEADER, false);
			$gateway->setopt(CURLOPT_RETURNTRANSFER, true);
			$gateway->setopt(CURLOPT_FOLLOWLOCATION, true);
			$gateway->setopt(CURLOPT_MAXREDIRS, Image::CURL_MAXREDIRS);
			//increase timeout in case of large images
			$gateway->setopt('TIMEOUT', 60);
			// get the raw body response, ignore errors
			$response = @$gateway->exec();

			if($response === false){
				var_dump($gateway->getInfoLast());die;
				throw new Exception(sprintf('Error reading external image <code>%s</code>. Please check the URI.', $uri));
			}

			//get type from extension
			$extension_pos = strrpos($uri, '.'); // find position of the last dot, so where the extension starts
			$extension =  strtolower(substr($uri, $extension_pos+1));
			$type = "";
			switch ($extension) {
				case 'gif':
					$type = "data:image/gif";
					break;
				case 'png':
					$type = "data:image/png";
					break;
				case 'jpg':
				case 'jpeg':
					$type = "data:image/jpeg";
					break;
				
				default:
					# code...
					break;
			}

			$image = self::load(imagecreatefromstring($response),$type);

			// clean up
			$gateway->flush();

			return $image;
		}

		/**
		 * Given a path to an image, `$image`, this function will verify it's
		 * existence, and generate a resource for use with PHP's image functions
		 * based off the file's type (.gif, .jpg, .png).
		 * Images must be RGB, CMYK jpg's are not supported due to GD limitations.
		 *
		 * @param string $image
		 *  The path to the file
		 * @return Image
		 */
		public static function loadFile($image){
			if(!is_file($image) || !is_readable($image)){
				throw new Exception(sprintf('Error loading image <code>%s</code>. Check it exists and is readable.', str_replace(DOCROOT, '', $image)));
			}

			$meta = self::getMetaInformation($image);

			switch($meta->type) {
				// GIF
				case IMAGETYPE_GIF:
					$resource = imagecreatefromgif($image);
					break;

				// JPEG
				case IMAGETYPE_JPEG:
					if($meta->channels <= 3){
						$resource = imagecreatefromjpeg($image);
					}
					// Can't handle CMYK JPEG files
					else{
						throw new Exception('Cannot load CMYK JPG images');
					}
					break;

				// PNG
				case IMAGETYPE_PNG:
					$resource = imagecreatefrompng($image);
					break;

				default:
					throw new Exception('Unsupported image type. Supported types: GIF, JPEG and PNG');
					break;
			}

			if(!is_resource($resource)){
				throw new Exception(sprintf('Error creating image <code>%s</code>. Check it exists and is readable.', str_replace(DOCROOT, '', $image)));
			}

			$obj = new self($resource, $meta);

			return $obj;
		}

		public static function load($imageResource,$type){
			$rgb = imagecolorat($imageResource, 1, 1);
			$colors = imagecolorsforindex($imageResource, $rgb);

			$meta = array();
			$meta['width'] = imagesx($imageResource);
			$meta['height'] = imagesy($imageResource);
			$meta['channels'] = count($colors);
			// header("Content-type:".$type);

			switch($type) {
				// GIF
				case 'data:image/gif':
					// imagegif($imageResource);
					$meta['type'] = IMAGETYPE_GIF;
					break;

				// JPEG
				case 'data:image/jpeg':
					if($meta['channels'] <= 3 || ($meta['channels'] == 4 && $colors['alpha'] == null )){
						// imagejpeg($imageResource);
						$meta['type'] = IMAGETYPE_JPEG;
					}
					// Can't handle CMYK JPEG files
					else{
						throw new Exception('Cannot load CMYK JPG images');
					}
					break;

				// PNG
				case 'data:image/png':
					$meta['type'] = IMAGETYPE_PNG;
					imagealphablending($imageResource, false);
					imagesavealpha($imageResource, true);
					// imagepng($imageResource);
					break;

				default:
					throw new Exception('Unsupported image type. Supported types: GIF, JPEG and PNG');
					break;
			}

			$obj = new self($imageResource, (object)$meta);

			return $obj;
		}

		//to be used to upload things into an S3 bucket
		public function getStream($quality = Image::DEFAULT_QUALITY, $interlacing = Image::DEFAULT_INTERLACE) {
			ob_start();
			self::__render(null, $quality, $interlacing, $this->Meta()->type);
			$imageFileContents = ob_get_contents();
			ob_end_clean();
			return $imageFileContents;
		}

		//to be used to upload things into an S3 bucket
		public function cropToDimensions($left,$top,$width,$height) {
			
			$src_w = $this->Meta()->width;
			$src_h = $this->Meta()->height;

			$left = round($left *$src_w / 100);
			$width = round($width * $src_w / 100);
			$top = round($top *$src_h / 100);
			$height = round($height *$src_h / 100);

			$dest = imagecreatetruecolor($width, $height);
			if($this->Meta()->type==IMAGETYPE_PNG){
				//must be a png
				imagealphablending($dest, false);
				imagesavealpha($dest, true);
			}

			// Copy
			imagecopy($dest, $this->_resource, 0, 0, $left, $top, $width, $height);

			if(is_resource($this->_resource)) {
				imagedestroy($this->_resource);
			}

			$this->_resource = $dest;

			$meta = array();
			$meta['width'] = $width;
			$meta['height'] = $height;
			$meta['channels'] = $this->Meta()->channels;
			$meta['type'] = $this->Meta()->type;
			$this->_meta = (object)$meta;

		}

	}

