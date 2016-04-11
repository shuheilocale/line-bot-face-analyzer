<?php
require_once 'face.php';
require_once 'class.image.php';
	
$facer = new FaceAnalyzer;
$img = file_get_contents(dirname(__FILE__) ."/images/test.jpg");
$analyzed = $facer->analyze_from_binary($img);


//[{"faceId":"ccf691f1-3173-4707-b2d9-27c717b43a80","faceRectangle":{"top":83,"left":200,"width":105,"height":105},"attributes":{"gender":"male","age":46}}]
if( $analyzed["result"] ){
	$faceobj = $analyzed["faces"][0];
	$top = $faceobj->{"faceRectangle"}->{"top"};
	$left = $faceobj->{"faceRectangle"}->{"left"};
	$width = $faceobj->{"faceRectangle"}->{"width"};
	$height = $faceobj->{"faceRectangle"}->{"height"};
	
	$clipping_face = new Image(dirname(__FILE__) ."/images/test.jpg");
	$clipping_face->width($width);
	$clipping_face->height($height);
	$clipping_face->crop($left, $top);
	
	$clipping_face->dir(dirname(__FILE__)."/images/");
	$clipping_face->name("resize_test.jpg");
	$clipping_face->save();
}

?>

