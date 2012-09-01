<?php

class KayakoAPI {

    var $api_url, $api_key, $api_secret, $salt, $signature;

    function KayakoAPI($api_url, $api_key, $api_secret) {
        $this->api_url    = $api_url;
        $this->api_key    = $api_key;
        $this->api_secret = $api_secret;
        $this->api_salt   = mt_rand();
        $this->signature  = base64_encode(hash_hmac('sha256', $this->api_salt, $this->api_secret, true));
    }

    //$rest_call_type -> POST, GET, PUT, DELETE
    //$params[0] i.e. Tickets/Ticket/ListAll/$departmentid$/$ticketstatusid$/$ownerstaffid$/$userid$/
    public function __call($rest_call_type, $params) {
        $full_method_string = $params[0]['full_method_string'];
        unset($params[0]['full_method_string']);
        return $this->callServer($rest_call_type, $full_method_string, $params[0]);
    }

    function callServer($rest_call_type, $full_method_string, $params) {

       $url = sprintf("%s?e=%s", $this->api_url, $full_method_string);

        //some distributions change this to &amp; by default
        if (ini_get("arg_separator.output")!="&"){
            $sep_changed = true;
            $orig_sep = ini_get("arg_separator.output");
            ini_set("arg_separator.output", "&");
        }

        $headers = array();

        switch ($rest_call_type) {
        case 'POST':
        case 'PUT':
            $params['apikey']    = $this->api_key;
            $params['salt']      = $this->api_salt;
            $params['signature'] = $this->signature;
            break;
        case 'GET':
        case 'DELETE':
            $url .= sprintf("&apikey=%s&salt=%s&signature=%s", $this->api_key, $this->api_salt, urlencode($this->signature));
            break;
        }

        if (array_key_exists('files', $params) && is_array($params['files']) && count($params['files']) > 0) {
            $post_body = array();
            $boundary = substr(md5(rand(0,32000)), 0, 10);

            if (is_array($params) && count($params) > 0) {               
                foreach ($data as $name => $value) {
                    if($name != 'files') {
                        $post_body[] = sprintf("--%s", $boundary);
                        $post_body[] = sprintf("Content-Disposition: form-data; name=\"%s\"\n\n%s", $name, $value);
                    }
                }
            }

            $post_body[] = sprintf("--%s", $boundary);

            foreach ($params['files'] as $name => $file_data) {
                $file_name = $file_data['file_name'];
                $file_contents = $file_data['contents'];
                $content_type = 'application/octet-stream';

                $post_body[] = sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"", $name, $file_name);
                $post_body[] = sprintf("Content-Type: %s", $content_type);
                $post_body[] = "Content-Transfer-Encoding: binary\n";
                $post_body[] = $file_contents;
                $post_body[] = sprintf("--%s--\n", $boundary);
            }

            $headers[] = 'Content-Type: multipart/form-data; boundary='.$boundary;

            $request_body = implode("\n", $post_body);
        } 
        else {
            $request_body = http_build_query($params, '', '&');
        }

        $curl_options = array(CURLOPT_HEADER => false,
                              CURLOPT_RETURNTRANSFER => true,
                              CURLOPT_SSL_VERIFYPEER => false,
                              CURLOPT_SSL_VERIFYHOST => false,
                              CURLOPT_CONNECTTIMEOUT => 2,
                              CURLOPT_FORBID_REUSE => true,
                              CURLOPT_FRESH_CONNECT => true,
                              CURLOPT_HTTPHEADER => $headers,
                              CURLOPT_URL => $url);

        switch ($rest_call_type) {
        case 'POST':
            $curl_options[CURLOPT_POSTFIELDS] = $request_body;
            $curl_options[CURLOPT_POST] = true;
            break;
        case 'PUT':
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $curl_options[CURLOPT_POSTFIELDS] = $request_body;
            break;
        case 'GET':
            break;
        case 'DELETE':
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            break;
        }
 
        $curl_options[CURLOPT_HTTPHEADER] = $headers;
        $curl_handle = curl_init();
        curl_setopt_array($curl_handle, $curl_options);
        $response = curl_exec($curl_handle);
        if ($response === false) {
            return array();
        }

        $http_status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        if ($http_status != 200) {
            return array();        
        }

        curl_close($curl_handle);

        echo 'Sending REST request to Kayako:';
        echo sprintf('  %s: %s', $rest_call_type, $curl_options[CURLOPT_URL]);
        if (in_array($rest_call_type, array('POST', 'PUT'))) {
            echo sprintf('  Body: %s', $request_body);
        }
        return self::xml_to_array($response);
    }

    public static function xml_to_array($xml, $namespaces = null) {
        $iter = 0;
        $arr = array();

        if(is_string($xml))
            $xml = new SimpleXMLElement($xml);

        if(!($xml instanceof SimpleXMLElement))
            return $arr;

        if($namespaces === null)
            $namespaces = $xml->getDocNamespaces(true);

        foreach($xml->attributes() as $attributeName => $attributeValue) {
            $arr["_attributes"][$attributeName] = trim($attributeValue);
        }

        foreach($namespaces as $namespace_prefix => $namespace_name) {
            foreach($xml->attributes($namespace_prefix, true) as $attributeName => $attributeValue) {
                $arr["_attributes"][$namespace_prefix.':'.$attributeName] = trim($attributeValue);
            }
        }

        $has_children = false;

        foreach($xml->children() as $element) {

            $has_children = true;

            $elementName = $element->getName();

            if($element->children()) {
                $arr[$elementName][] = self::xml_to_array($element, $namespaces);
            } 
            else {
                $shouldCreateArray = array_key_exists($elementName, $arr) && !is_array($arr[$elementName]);

                if($shouldCreateArray) {
                    $arr[$elementName] = array($arr[$elementName]);
                }

                $shouldAddValueToArray = array_key_exists($elementName, $arr) && is_array($arr[$elementName]);

                if($shouldAddValueToArray) {
                    $arr[$elementName][] = trim($element[0]);
                }
                else {
                    $arr[$elementName] = trim($element[0]);
                }
            }

            $iter++;
        }

        if(!$has_children) {
            $arr['_contents'] = trim($xml[0]);
        }
        return $arr;
    }
}