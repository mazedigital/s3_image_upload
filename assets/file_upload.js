
(function($){
	$(document).ready(function(){
		
		var fieldPrefix = $('.field-s3_file_upload').data('field-name');
		var region = $('.field-s3_file_upload').data('region');
		if (region == 'us-east-1') region = 's3';
		if ( region != '')  region += '.';

		// var s3URL = 'https://'+$('.field-s3_file_upload').data('bucket')+'.'+region+'amazonaws.com'
		var s3URL = 'https://'+region+'amazonaws.com/'+$('.field-s3_file_upload').data('bucket');

		$(".dropzone-container[data-file-upload='yes']").dropzone({ 
			url: s3URL,
			maxFilesize: "100",
			method: "post",
			autoProcessQueue: true,
			maxfiles: 999,
			parallelUploads: 2,
			clickable: '.dropzone-click',
			dictDefaultMessage: "Drop files here to upload - Maximum Size : 100MB",
			previewsContainer: '.dropzone-previews',
			previewTemplate: '<div class="dz-preview dz-file-preview">'+
								'<div class="dz-details">'+
									'<img data-dz-thumbnail />'+
									'<div class="dz-text-details">'+
										'<div class="dz-filename"><span data-dz-name></span></div>'+
										'<div class="dz-size" data-dz-size></div>'+
									'</div>'+
								'</div>'+
								'<div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>'+
								'<div class="dz-success-mark"><span>✔</span></div>'+
								'<div class="dz-error-mark"><span>✘</span></div>'+
								'<div class="dz-error-message"><span data-dz-errormessage></span></div>'+
							'</div>',
			accept: function(file, done){
				file.postData = [];
				$.ajax({
					url: '/ajax/',
					data: {"action[generate-s3-signature]": 'submit', "fields[name]": file.name, "fields[type]": file.type, "fields[size]": file.size, "fields[id]":  $('.field-s3_file_upload').data('id')},
					type: 'POST',
					dataType: 'json',
					success: function(response)
					{
						file.custom_status = 'ready';
						file.postData = response;
						$(file.previewTemplate).addClass('uploading');
						done();
					},
					error: function(response)
					{
						file.custom_status = 'rejected';

						if (response.responseText) {
							response = parseJsonMsg(response.responseText);
						}
						if (response.message) {
							done(response.message);
						} else {
							done('error preparing the upload');
						}
					}
				});
			},
			sending: function(file, xhr, formData){
				// xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
				$.each(file.postData, function(k, v){
					if (k.substr(0,1) == 'X'){
            			xhr.setRequestHeader(k,v);
					} else {
						formData.append(k, v);
					}
				});
			},
			success: function(file, response){
				

				var filenumber = $('.field-s3_file_upload').data('filenumber') ? $('.field-s3_file_upload').data('filenumber') : 0;
				filenumber++;
				$('.field-s3_file_upload').data('filenumber',filenumber);

				$filename = $('<input/>',{
					name: fieldPrefix+'[filename]['+filenumber+']',
					type: 'hidden',
					value: file.name,
				});
				$filepath = $('<input/>',{
					name: fieldPrefix+'[filepath]['+filenumber+']',
					type: 'hidden',
					value: $(response).find('key').text(),
				});
				$mimetype = $('<input/>',{
					name: fieldPrefix+'[mimetype]['+filenumber+']',
					type: 'hidden',
					value: file.type,
				});

				$('.field-s3_file_upload').after($filename);
				$('.field-s3_file_upload').after($filepath);
				$('.field-s3_file_upload').after($mimetype);
			}			
		});
/*
		//without cropping
		$(".dropzone-container[data-file-upload='yes']").dropzone({ 
			url: "/",
			addRemoveLinks : true,
			clickable : true,
			autoProcessQueue : false,
			maxFiles: 2, //so while one is added one is removed
			thumbnailWidth: null,
			thumbnailHeight: null,
			addRemoveLinks: false,
			previewsContainer: '.file-preview',
			previewTemplate: "<div class='image-wrap'>"+
								  "<img id='source-img' data-dz-thumbnail>"+
								"</div>"
		});*/

		/*var myDropzone = Dropzone.forElement(".dropzone-container");

		myDropzone.on("addedfile", function(file) {
			// myDropzone.removeAllFiles(true);
		});


		myDropzone.on("thumbnail", function(file) {

		});

		myDropzone.on("success", function(file) {
			$('#params_placeholder').append('<input type="hidden" name="attachments[]" value="'+ file.name +'" />');
		});

		myDropzone.on("removedfile", function(file) {
			$('#params_placeholder input[value="'+ file.name +'"]').remove();
		});*/
	});

})(jQuery);