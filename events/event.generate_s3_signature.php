<?php

class eventgenerate_s3_signature extends Event
{
    public $ROOTELEMENT = 'generate-s3-signature';

    public static function about()
    {
        return array(
            'name' => 'Generate S3 Signature',
            'author' => array(
                'name' => 'Jonathan Mifsud',
                'website' => 'http://maze.dev',
                'email' => 'jonathan@maze.digital'),
            'version' => 'Symphony 2.6.0',
            'release-date' => '2015-05-23T12:33:03+00:00',
            'trigger-condition' => 'action[generate-s3-signature]'
        );
    }

    public static function getSource()
    {
        return '3';
    }

    public static function allowEditorToParse()
    {
        return true;
    }

    public static function documentation()
    {
        return '
                <h3>Success and Failure XML Examples</h3>
                <p>When saved successfully, the following XML will be returned:</p>
                <pre class="XML"><code>&lt;get-in-touch result="success" type="create | edit">
    &lt;message>Entry [created | edited] successfully.&lt;/message>
&lt;/get-in-touch></code></pre>
                <p>When an error occurs during saving, due to either missing or invalid fields, the following XML will be returned.</p>
                <pre class="XML"><code>&lt;get-in-touch result="error">
    &lt;message>Entry encountered errors when saving.&lt;/message>
    &lt;field-name type="invalid | missing" />
...&lt;/get-in-touch></code></pre>
                <p>The following is an example of what is returned if any options return an error:</p>
                <pre class="XML"><code>&lt;get-in-touch result="error">
    &lt;message>Entry encountered errors when saving.&lt;/message>
    &lt;filter name="admin-only" status="failed" />
    &lt;filter name="send-email" status="failed">Recipient not found&lt;/filter>
...&lt;/get-in-touch></code></pre>
                <h3>Example Front-end Form Markup</h3>
                <p>This is an example of the form markup you can use on your frontend:</p>
                <pre class="XML"><code>&lt;form method="post" action="{$current-url}/" enctype="multipart/form-data">
    &lt;input name="MAX_FILE_SIZE" type="hidden" value="2097152" />
    &lt;label>Name
        &lt;input name="fields[name]" type="text" />
    &lt;/label>
    &lt;label>Surname
        &lt;input name="fields[surname]" type="text" />
    &lt;/label>
    &lt;label>Email
        &lt;input name="fields[email]" type="text" />
    &lt;/label>
    &lt;label>Phone
        &lt;input name="fields[phone]" type="text" />
    &lt;/label>
    &lt;label>Company
        &lt;input name="fields[company]" type="text" />
    &lt;/label>
    &lt;label>Budget
        &lt;input name="fields[budget]" type="text" />
    &lt;/label>
    &lt;label>Comments
        &lt;textarea name="fields[comments]" rows="15" cols="50">&lt;/textarea>
    &lt;/label>
    &lt;input name="action[get-in-touch]" type="submit" value="Submit" />
&lt;/form></code></pre>
                <p>To edit an existing entry, include the entry ID value of the entry in the form. This is best as a hidden field like so:</p>
                <pre class="XML"><code>&lt;input name="id" type="hidden" value="23" /></code></pre>
                <p>To redirect to a different location upon a successful save, include the redirect location in the form. This is best as a hidden field like so, where the value is the URL to redirect to:</p>
                <pre class="XML"><code>&lt;input name="redirect" type="hidden" value="http://maze.dev/success/" /></code></pre>';
    }

    function getS3Settings($filename,$contentType,$fieldID) {
        $AWSkey = Symphony::Configuration()->get('access-key-id', 's3_image_upload');
        $AWSsecret = Symphony::Configuration()->get('secret-access-key', 's3_image_upload');

        $field = FieldManager::fetch($fieldID);

        $AWSbucket = $field->get('bucket');
        $AWSregion = $field->get('region');
        $acl= $field->get('acl');
        
        // Get the file extension
        $file = pathinfo($filename);
        
        if (!$file) {
            return false;
        }
        
        if ($file['dirname'] == "."){
            $file['dirname'] = "";
        }

        // Prepare the filename
        $fileName = General::createHandle($file['filename'],50) . '-' . time();
        $key = $field->get('key_prefix') . $file['dirname'] . '/'.$fileName.'.'.$file['extension'];
        
        // Set the expiration time of the policy
        $policyExpiration = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));


        $algorithm = "AWS4-HMAC-SHA256";
        $service = "s3";
        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        $requestType = "aws4_request";
        $expires = '3600'; // 1 Hour
        $successStatus = '201';

        $scope = [
            $AWSkey,
            $shortDate,
            $AWSregion,
            $service,
            $requestType
        ];
        $credentials = implode('/', $scope);
        
        // Set the policy
        $policy = [
            'expiration' => $policyExpiration,
            'conditions' => [
                ['bucket' => $AWSbucket],
                ['acl' => $acl],
                ['starts-with', '$key', ''],
                ['starts-with', '$Content-Type', $contentType],
                ['success_action_status' => '201'],
                ['x-amz-credential' => $credentials],
                ['x-amz-algorithm' => $algorithm],
                ['x-amz-date' => $date],
                ['x-amz-expires' => $expires]
            ]
        ];
        
        // 1 - Encode the policy using UTF-8.
        // 2 - Encode those UTF-8 bytes using Base64.
        // 3 - Sign the policy with your Secret Access Key using HMAC SHA-1.
        // 4 - Encode the SHA-1 signature using Base64.
        
        // Prepare the signature
        $base64Policy = base64_encode(json_encode($policy));

        $signature = base64_encode(hash_hmac('sha1', $b64, $AWSsecret, true));

        // Signing Keys
        $dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . $AWSsecret, true);
        $dateRegionKey = hash_hmac('sha256', $AWSregion, $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

        // Signature
        $signature = hash_hmac('sha256', $base64Policy, $signingKey);
        
        // Return the post information
        return array(
            'Content-Type' => $contentType,
            'key' => $key,
            'acl' => $acl,
            'policy' => $base64Policy,
            'success_action_status' => 201,
            'X-amz-algorithm' => $algorithm,
            'X-amz-credential' => $credentials,
            'X-amz-date' => $date,
            'X-amz-expires' => $expires,
            'X-amz-signature' => $signature,
        );
    }

    public function load()
    {
        if (isset($_POST['action']['generate-s3-signature'])) {
            // return $this->getS3Settings();
            header('Content-Type: application/json');
            echo json_encode($this->getS3Settings($_POST['fields']['name'],$_POST['fields']['type'],$_POST['fields']['id']));die;
        }
    }

}
