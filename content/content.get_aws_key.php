<?php

	require_once(EXTENSIONS . '/s3_image_upload/events/event.generate_s3_signature.php');

	class Content_LogsDevKit extends DevKit {
		protected $_view = '';
		protected $_xsl = '';
		protected $_path = '';
		protected $_files = array();

		public function __construct(){
			parent::__construct();

			$eventgenerate_s3_signature = new eventgenerate_s3_signature();
			$eventgenerate_s3_signature->load();
		}
	}