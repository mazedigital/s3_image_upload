//point to click centering idea
/*jQuery(document).ready(function(){
	jQuery('#images_preview .grid').hide();

	jQuery(".image-wrap img").click(function(e){
		var parentOffset = jQuery(this).parent().offset(); 
		//or jQuery(this).offset(); if you really just want the current element's offset

		var relX = e.pageX - parentOffset.left;
		var relY = e.pageY - parentOffset.top;

		console.log(relX * 100 / jQuery(this).width() + '%');
		console.log(relY * 100 / jQuery(this).height() + '%');
	});
})*/

CanvasRenderingContext2D.prototype.clear = 
  CanvasRenderingContext2D.prototype.clear || function (preserveTransform) {
    if (preserveTransform) {
      this.save();
      this.setTransform(1, 0, 0, 1, 0, 0);
    }

    this.clearRect(0, 0, this.canvas.width, this.canvas.height);

    if (preserveTransform) {
      this.restore();
    }           
};

(function($){
	$(document).ready(function(){
	    var resizing = false;
	    var horizontal = false;
	    var vertical = false;
	    var $frame = $(".grid");
	    var initialWidth;
	    var initialLeftOffset;
	    var initialHeight;
	    var initialTopOffset;

	    function resizeInCanvas(){
	    	var canvas = document.getElementById("crop-canvas");
		    var context = canvas.getContext("2d");
		    var img = document.getElementById("source-img"),
		        $img = $(img),
		        imgW = img.naturalWidth,
		        imgH = img.naturalHeight;
		    
		    var getX = $frame.data('left') / 100 * imgW,
		        getY = $frame.data('top') / 100 * imgH,
		        getWidth = $frame.data('width') / 100 * imgW,
		        getHeight = $frame.data('height') / 100 * imgH;
		    
		    $(canvas).attr('width',getWidth);
		    $(canvas).attr('height',getHeight);
		    context.clear();
		    context.drawImage(img,getX,getY,getWidth,getHeight,0,0,getWidth,getHeight);

		    // base64img = canvas.toDataURL(); //for png
		    base64img = canvas.toDataURL("image/jpeg"); //for jpg
		    $('.image-preview-container img').attr('src',base64img);
	    }

	    $(document).mouseup(function(e) {
	    	if ($frame.length == 0){
	    		$frame = $(".grid");
	    		if ($frame.length == 0) return;
	    	} 

	        resizing = false;
	        $frame.data( 'width',$frame.width() * 100 / $frame.parent().width() );
	        $frame.data( 'height',$frame.height() * 100 / $frame.parent().height() );
	        $frame.data( 'left',parseFloat($frame.css('left')) * 100 / $frame.parent().width() );
	        $frame.data( 'top',parseFloat($frame.css('top')) * 100 / $frame.parent().height() );

	        var fieldPrefix = $('.field-s3_image_upload').data('field-name');

	        $('input[name="'+fieldPrefix+'[top]"]').val($frame.data('top'));
	        $('input[name="'+fieldPrefix+'[left]"]').val($frame.data('left'));
	        $('input[name="'+fieldPrefix+'[width]"]').val($frame.data('width'));
	        $('input[name="'+fieldPrefix+'[height]"]').val($frame.data('height'));

	        resizeInCanvas();
	    });

	    $(document).on('mousedown',".grid",function(e) {
	        e.preventDefault();

	    	initialWidth = $frame.width();
	    	initialLeftOffset = parseFloat($frame.css('left'));
			initialHeight = $frame.height();
	    	initialTopOffset = parseFloat($frame.css('top'));

	        if(e.offsetX < 5){
	        	horizontal = 'left'
	        } else if($(e.target).width() - e.offsetX < 5){
	        	horizontal = 'right'
	        } else {
	        	horizontal = false;
	        }
	        if(e.offsetY < 5){
	        	vertical = 'top'
	        } else if($(e.target).height() - e.offsetY < 5){
	        	vertical = 'bottom'
	        } else {
	        	vertical = false;
	        }
	        if (vertical || horizontal)
		        resizing = true;
	    });

	    $(document).mousemove(function(e) {
	    	if ($frame.length == 0){
	    		$frame = $(".grid");
	    		if ($frame.length == 0) return;
	    	} 

			if(resizing && vertical == 'bottom') {
				var frameTop = $frame.offset().top;
				var newHeight = Math.min(e.pageY - frameTop, $('.grid').parent().height() - initialTopOffset);
				$frame.height(newHeight);
			}
			if(resizing && horizontal == 'right') {
				var frameLeft = $frame.offset().left;
				var newWidth = Math.min(e.pageX - frameLeft, $('.grid').parent().width() - initialLeftOffset);
				$frame.width(newWidth);
			}
			if(resizing && vertical == 'top') {
				var frameTop = $frame.parent().offset().top;
				var newOffset = Math.max(e.pageY - frameTop,0);
				$frame.css('top',newOffset);
				var newHeight = Math.min(initialHeight - (newOffset - initialTopOffset), $('.grid').parent().height());
				$frame.height(newHeight);
			}
			if(resizing && horizontal == 'left') {
				var frameLeft = $frame.parent().offset().left;
				var newOffset = Math.max(e.pageX - frameLeft,0);
				$frame.css('left',newOffset);
				var newWidth = Math.min(initialWidth - (newOffset - initialLeftOffset), $('.grid').parent().width());
				$frame.width(newWidth);
			}
	    });

		var fieldPrefix = $('.field-s3_image_upload').data('field-name');


	    if ($('#source-img').length > 0){
	    	//make sure we clean the image source and obtain it using CORS so canvas is not dirty
	    	var img = document.getElementById("source-img");
	    	img.onload = function(e) {
				//change grid style attributes to fixed values rather than percentages seems to be inconsistent
				var $imgWrap = $('.image-wrap');
				var imgHeight = $imgWrap.height();
				var imgWidth = $imgWrap.width();
				$frame.css('top',($('input[name="'+fieldPrefix+'[top]"]').val() * imgHeight / 100) + 'px');
				$frame.css('height',($('input[name="'+fieldPrefix+'[height]"]').val() * imgHeight / 100) + 'px');
				$frame.css('left',($('input[name="'+fieldPrefix+'[left]"]').val() * imgWidth / 100) + 'px');
				$frame.css('width',($('input[name="'+fieldPrefix+'[width]"]').val() * imgWidth / 100) + 'px');
	    	};
	    	var $img = $(img);
	    	var source = $img.attr('src');
			img.crossOrigin = ''; // no credentials flag. Same as img.crossOrigin='anonymous'
			img.src = source;
	    }

		var selected = $('input[name="'+fieldPrefix+'[crop_position]').val();
		$(' .col[data-pos="'+ selected +'"]').addClass('active');

		$(document).on('click','.grid .col',function(){
			// alert($(this).data('pos'));
			$('.col').removeClass('active');
			$(this).addClass('active');
			var selected = $(this).data('pos');

			$('.image-preview-container img').removeClass().addClass(selected)
			$('input[name="'+fieldPrefix+'[crop_position]').val(selected);
		});

		$(".dropzone-container").dropzone({ 
			url: "/",
			addRemoveLinks : true,
			clickable : true,
			autoProcessQueue : false,
			maxFiles: 2, //so while one is added one is removed
			thumbnailWidth: null,
			thumbnailHeight: null,
			addRemoveLinks: false,
			previewsContainer: '.image-crop-container',
			previewTemplate: "<div class='image-wrap'>"+
								  "<img id='source-img' data-dz-thumbnail>"+
								  "<div class='grid'>"+
								    "<div class='row' data-row='1'>"+
								      "<div class='col' data-pos='crop-left crop-top' data-jit='1'></div>"+
								      "<div class='col active' data-pos='crop-center crop-top' data-jit='2'></div>"+
								      "<div class='col' data-pos='crop-right crop-top' data-jit='3'></div>"+
								    "</div>"+
								    "<div class='row' data-row='2'>"+
								      "<div class='col' data-pos='crop-left crop-middle' data-jit='4'></div>"+
								      "<div class='col' data-pos='crop-center crop-middle' data-jit='5'></div>"+
								      "<div class='col' data-pos='crop-right crop-middle' data-jit='6'></div>"+
								    "</div>"+
								    "<div class='row' data-row='3'>"+
								      "<div class='col' data-pos='crop-left crop-bottom' data-jit='7'></div>"+
								      "<div class='col' data-pos='crop-center crop-bottom' data-jit='8'></div>"+
								      "<div class='col' data-pos='crop-right crop-bottom' data-jit='9'></div>"+
								    "</div>"+
								  "</div>"+						  
								"</div>"
		});

		var myDropzone = Dropzone.forElement(".dropzone-container");

		myDropzone.on("addedfile", function(file) {
			// myDropzone.removeAllFiles(true);
			var files = myDropzone.getQueuedFiles();
			if (typeof files[0] !== 'undefined'){
				myDropzone.removeFile(files[0]);
			}
		});


		myDropzone.on("thumbnail", function(file) {
			var height = $('.image-wrap img').height();
			var width = $('.image-wrap img').width();
			$('.grid').height(height);
			$('.grid').width(width);

	        var fieldPrefix = $('.field-s3_image_upload').data('field-name');
	        $('input[name="'+fieldPrefix+'[image]"]').val($('#source-img').attr('src'));
	        $('input[name="'+fieldPrefix+'[imagename]"]').val(file.name);

	        //center crop by default
	        var selected = 'crop-center crop-middle';
	        $(' .col.active').removeClass('active');
	        $(' .col[data-pos="'+ selected +'"]').addClass('active');
			$('.image-preview-container img').removeClass().addClass(selected)
			$('input[name="'+fieldPrefix+'[crop_position]').val(selected);
		});

		myDropzone.on("success", function(file) {
			$('#params_placeholder').append('<input type="hidden" name="attachments[]" value="'+ file.name +'" />');
		});

		myDropzone.on("removedfile", function(file) {
			$('#params_placeholder input[value="'+ file.name +'"]').remove();
		});
	});

})(jQuery);