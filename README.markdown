# S3 Image Upload

S3 Image Upload is a Symphony CMS Extension devised for heavy weight image customization. Alongside image upload this extension also handles, custom cropping functionality, as well as on upload image resizing (JIT Style) to pre-defined sizes. 

## Installation

1. Upload the 'imagecropper' folder in this archive to your Symphony 'extensions' folder.

2. Enable it by selecting the "Field: S3 Image Uload", choose Enable from the with-selected menu, then click Apply.

3. You can now add the "S3 Image Uload" field to your sections.

4. Make sure you have [JIT Image Manipulation extension](http://symphonyextensions.com/extensions/jit_image_manipulation/) installed and activated, as this extension uses JIT functionality for image cropping.

## Documentation

### Using The field.

The S3 Image Upload Field is a somewhat complicated UI structure, it offers Drag & Drop functionality through Drop Zone, and will allow you to modify/crop the image on the fly using the HTML Canvas Elements.

1. Drag an image into the rectangle at the top of the field (or click to browse). This will grab the image from your machine and render it into a grid.
2. The edges of the 3x3 grid visible allows you click & drag to resize the image from each of the four sides & corners
3. Clicking within any of the 3x3 grid squares will set the cropping position. This can be seen in action using the preview images on the right side. Once saved the image will be resized into the selected sizes using this cropping positions. Use the preview sizes as guidelines for your image cropping.
4. Saving the entry will upload the images and it's cropped & resized version into the S3 bucket for use later on.

### Frontend

The XML output is something like

	<field_name crop-position="crop-center crop-middle" original="{s3bucketURL}/{prefix}/{filename}" original-key="{prefix}/{filename}">
        <supported-dimensions>
            <image dimension="50x50">{s3bucketURL}/{prefix}/50x50/{filename}</image>
            <image dimension="250x250">{s3bucketURL}/{prefix}/250x250/{filename}</image>
            <image dimension="cropped">{s3bucketURL}/{prefix}/cropped/{filename}</image>
        </supported-dimensions>
    </field_name>

The `supported-dimensions` element helps ensure that the images that you want are provided. This is especially the case if you modify/add more crop sizes at a later stage. Images which were previously uploaded will not support these image sizes, and you might have to take note of that. Obiously one can re-save the entry to upload the newer dimensions. However if you have a lot of images this could be a time consuming process.

### Image Naming Convention

Each Image (original,cropped and resized) is uploaded into the S3 bucket using the following naming convention.

1. 'Prefix' the extension allows you to set a folder prefix. Good if you want to use the same bucket for multiple fields/sections. This will mean all your images will live within the folder you indicate within the `prefix_key` option for the field
2. The crop dimension, for simplicity a folder level structure is added whereby images are placed into folders according to their size, so all your images cropped `50x50` will reside within this virtual folder (S3 doesn't have real folders)
3. Image Name, the image name provided with the upload will be passed through the Symphony handle function, and cropped to 50 characters (excluding extension)
4. Timestamp, to ensure there are no clashes `-timestamp` is appended to each and every image prior to upload
5. The final result looks something like this - `{s3bucketURL}/{prefix}/{filename-timestamp.ext}` for the original image and `{s3bucketURL}/{prefix}/cropped/{filename-timestamp.ext}` for a cropped image

At this point the image naming is fixed. however it might at some future stage allow users to specify a naming structure. If you would like this functionality get in touch or submit a PR.

## Notes

At this point in time only a single field is supported per section, as the cropping functionality will not work properly. In future versions the Javascript might be amended to allow for multiple croppping fields, if you find a usecase and would like to have multiple croppers on as single page kindly let me know.

## Temporary JIT Modifications

At the point of release there is an open ticket on JIT in order to make the image class extendible. Until this is complete the following funcitons / properties need to be changed from private to protected, so please follow the following notes.

1. Open `extensions/jit/lib/class.image.php` in your favourite text editor
2. locate the following two properties at the top of the file `$_resource` `$_meta` and set them to protected
3. locate the following two functions `__construct` `__render` and set them as protected

Great you are now ready to roll.

## Credits

* Thanks to all extension developers for inspirations.
* Image cropper uses [Jcrop » the jQuery Image Cropping Plugin](http://deepliquid.com/content/Jcrop.html)
