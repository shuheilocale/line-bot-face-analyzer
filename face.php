<?php

require_once 'HTTP/Request2.php';

// learn more
// https://dev.projectoxford.ai/docs/services/563879b61984550e40cbbe8d/operations/563879b61984550f30395236

class FaceAnalyzer
{
	public $img_url_default;
	public $query_params;
	
	function __construct() {
		$this->img_url_default = json_decode(getenv("HOME_URL"))->{"url"}."/images/test.jpg";
		$this->query_params = array(
			"subscription-key" => json_decode(getenv("MS_FACE"))->{"subscription_key"},
			"analyzesAge" => "true",
			"analyzesGender" => "true"
		);
	}
	
	// メソッドの宣言
	public function analyze($img_url) {

		$headers = array(
			"Content-Type" => "application/json"
		);
	
		$request = new Http_Request2("https://api.projectoxford.ai/face/v0/detections");
		$request->setConfig(array(
			"ssl_verify_peer" => false,
		));
		$request->setMethod(HTTP_Request2::METHOD_POST);
		$request->setHeader($headers);
		
		$url = $request->getUrl();
		$url->setQueryVariables($this->query_params);
		if( empty($img_url)){
			$reqParam["url"] = $this->img_url_default;
		}else{
			$reqParam["url"] = $img_url;
		}
		$json = json_encode($reqParam);
		$request->setBody($json);
		try
		{
			$response = $request->send();
			return json_decode($response->getBody());
		}
		catch (HttpException $ex)
		{
			return $ex;
		}
    }
    	
	public function analyze_from_binary($img_binary) {
		
		$headers = array(
			"Content-Type" => "application/octet-stream"
		);
		
		
		$request = new Http_Request2("https://api.projectoxford.ai/face/v0/detections");
		$request->setConfig(array(
			"ssl_verify_peer" => false,
		));
		$request->setMethod(HTTP_Request2::METHOD_POST);
		$request->setHeader($headers);
		
		$url = $request->getUrl();
		$url->setQueryVariables($this->query_params);
		if( empty($img_binary)){
			return false;
		}
		$request->setBody($img_binary);
		try
		{
			$response = $request->send();
			$status = $response->getStatus();
			$body_json = json_decode($response->getBody());
			
			error_log( "response status::".$response->getStatus() );
			error_log( "response body::".$response->getBody() );
			
			if($status != 200){
				if( $status == 400 || $status == 408 || $status == 415 ){
					return array (
						"result" => false,
						"code" => $body_json->{"code"},
						"message" => $body_json->{"message"}
					) ;
				}
				else if( $status == 401 || $status == 403 || $status == 429 ){ 
					return array (
						"result" => false,
						"code" => $body_json->{"statusCode"},
						"message" => $body_json->{"message"}
					) ;						
				}else{
					return array (
						"result" => false,
						"code" => "unknown",
						"message" => "unknown error"
					) ;
				}
			}
			
			return array (
					"result" => true,
					"faces" => $body_json
			) ;
		}
		catch (HttpException $ex)
		{
			throw $ex;
		}
	}
}

// simple test
/*
$facer = new FaceAnalyzer();

$img = file_get_contents(dirname(__FILE__) ."/images/test.jpg");
var_dump($facer->analyze_from_binary($img));
*/
?>