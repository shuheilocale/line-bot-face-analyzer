<?php

require_once 'face.php';
require_once 'class.image.php';

error_log("callback start.");


// アカウント情報設定
$line_bot = json_decode(getenv("LINE_BOT"));
$CHANNEL_ID = $line_bot->{"channel_id"};
$CHANNEL_SECRET = $line_bot->{"channel_secret"};
$MID = $line_bot->{"mid"};
$HOME_URL = json_decode(getenv("HOME_URL"))->{"url"};

$MESSAGE_LIST = array (
  'そんなこともできないの？',
  '今日なにやってたの？',
  '鳴かぬなら、鳴かせてみせよう、鳴くやろやぁ',
  'あとたのんまーす',
  'こっぱげた？',
  'お陀仏様でした'
);

$json_string = file_get_contents('php://input');
$json_obj = json_decode($json_string);
$content = $json_obj->{"result"}[0]->{"content"};
$to = $content->{"from"};

$text = $content->{"text"};
$message_id = $content->{"id"};
$content_type = $content->{"contentType"};

// 画像
$face_analysis_fails_msg = "";
$isFaseAnalyzeMode = false;
$faceAnalyses = array();
$isAnalyzed = false;
if( $content_type == 2 ){
	$isFaseAnalyzeMode = true;
	$response = api_get_message_content_request($message_id);
	
	// リサイズするために保存しておく
	$img_name = extract_img_name($response["header"]);
	$img_fname = "{$message_id}_{$img_name}";
	file_put_contents(dirname(__FILE__) ."/images/{$img_fname}", $response["body"]);

	$face = new FaceAnalyzer;
	try
	{
		$faceAnalyzed = $face->analyze_from_binary($response["body"]);

		// 顔が1つでも認識された
		if( $faceAnalyzed["result"] ){
			$isAnalyzed = true;
			
			foreach ($faceAnalyzed["faces"] as $idx => $faceobj) {

				if( $faceobj->{"attributes"}->{"gender"} == "male" ){
					error_log("analized result::male");
					$face_msg = $faceobj->{"attributes"}->{"age"}."歳 "."男性";
				}else{
					error_log("analized result::female");
					$face_msg = $faceobj->{"attributes"}->{"age"}."歳 "."女性";
				}
				
				$top = $faceobj->{"faceRectangle"}->{"top"};
				$left = $faceobj->{"faceRectangle"}->{"left"};
				$width = $faceobj->{"faceRectangle"}->{"width"};
				$height = $faceobj->{"faceRectangle"}->{"height"};
						
				$clipping_face = new Image(dirname(__FILE__) ."/images/{$img_fname}");
				$clipping_face->width($width);
				$clipping_face->height($height);
				$clipping_face->crop($left, $top);
				
				$clipping_face->dir(dirname(__FILE__)."/images/");
				$clipping_face->name("cliped_{$idx}_{$img_fname}");
				$clipping_face->save();
				
				$faceAnalyses[] = array(
					"img" => "cliped_{$idx}_{$img_fname}",
					"msg" => $face_msg
				);
				
			}
			
		}else{
			error_log("analized result:::分析失敗");
			$face_analysis_fails_msg = "顔わかりません、すみません\r\ncode:";
			$face_analysis_fails_msg.= $faceAnalyzed["code"]."\r\nmsg:";
			$face_analysis_fails_msg.= $faceAnalyzed["message"];
		}
	}
	catch (HttpException $ex)
	{
		error_log("exception error occured!!");
		$isFaseAnalyzeMode = false;
	}
}

$post_data;
if($isFaseAnalyzeMode){
	// 顔写真→年齢性別
	$messages = array();
	if( $isAnalyzed ){
		
		foreach ($faceAnalyses as $analysis) {
			$img_url = $GLOBALS["HOME_URL"]."/images/{$analysis['img']}";
			error_log("img_url::".$img_url);
			$messages[] = ['contentType'=>2,"toType"=>1,'originalContentUrl'=>$img_url,"previewImageUrl"=>$img_url ];
			$messages[] = ['contentType'=>1,"toType"=>1,"text"=>$analysis["msg"] ];
		}
	}
	else{
		array_push( $messages, ['contentType'=>1,"toType"=>1,"text"=>$face_analysis_fails_msg ] );
	}
	
	$post_data = ["to"=>[$to],"toChannel"=>"1383378250","eventType"=>"140177271400161403","content"=>array(
		"messageNotified" => 0,
		"messages" => $messages
	)];
}
else if( randomFormat() == 0 ){
	$response_format_text = ['contentType'=>1,"toType"=>1,"text"=>randomMessage()];
	$post_data = ["to"=>[$to],"toChannel"=>"1383378250","eventType"=>"138311608800106203","content"=>$response_format_text];
}else{
	$img_url = $GLOBALS["HOME_URL"]."/images/test.jpg";
	$response_format_image = ['contentType'=>2,"toType"=>1,'originalContentUrl'=>$img_url,
						"previewImageUrl"=>$img_url];
	$post_data = ["to"=>[$to],"toChannel"=>"1383378250","eventType"=>"138311608800106203","content"=>$response_format_image];
}

$ch = curl_init("https://trialbot-api.line.me/v1/events");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json; charser=UTF-8",
    "X-Line-ChannelID: {$GLOBALS['CHANNEL_ID']}",
    "X-Line-ChannelSecret: {$GLOBALS['CHANNEL_SECRET']}",
    "X-Line-Trusted-User-With-ACL: {$GLOBALS['MID']}"
    ));
$result = curl_exec($ch);
error_log($result );

curl_close($ch);


function randomMessage() {
	$max = count( $GLOBALS["MESSAGE_LIST"] ) -1;
	$index = rand ( 0, $max );
	return $GLOBALS["MESSAGE_LIST"][$index];
}

// 0:text, 1:image
function randomFormat(){
	return rand( 0, 1);
}

function api_get_message_content_request($message_id) {
	$url = "https://trialbot-api.line.me/v1/bot/message/{$message_id}/content";
	$headers = array(
		"X-Line-ChannelID: {$GLOBALS['CHANNEL_ID']}",
		"X-Line-ChannelSecret: {$GLOBALS['CHANNEL_SECRET']}",
		"X-Line-Trusted-User-With-ACL: {$GLOBALS['MID']}"
	);
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($curl);
	
	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE); 
	$header = substr($response, 0, $header_size);

	$body = substr($response, $header_size);
	error_log("res header::".$header);
	error_log("res body::".$body);
	return array( "header"=>$header, "body"=>$body);
}

// 改行を含む文字列を配列にする
function line_to_array($text){
	$array = explode("\r\n", $text);
	$array = array_map('trim', $array);
	$array = array_filter($array, 'strlen');
	$array = array_values($array); // 連番に振り直し
	return $array;
}

function extract_img_name( $header ){
	$header_array = line_to_array($header);
	foreach ($header_array as $line ) {
		$pos_filename = strpos($line, "filename=");
		if( $pos_filename ){
			$pos_dq_st = strpos($line, "\"", $pos_filename);
			$pos_dq_ed = strpos($line, "\"", $pos_dq_st+1);
			return substr( $line, $pos_dq_st+1, $pos_dq_ed-$pos_dq_st-1 );
		}
	}
	// 適当
	return "none.jpg"; 
}