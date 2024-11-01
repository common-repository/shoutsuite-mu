<?php
if(function_exists('imagefttext')) {
	if($_GET['count']) { $count =$_GET['count']; }
	else { $count = 'shout'; }
	$adjust = 5-strlen($count);
	$im = imagecreatefrompng('sb.png');
	$white = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);
	$blue = imagecolorallocate($im, 44, 170, 226);
	$size=12;
	$tx = 5.8;
	$ty = 73;
	if($adjust) {$ty=72.5; $tx = 6+($adjust*4);}
	imagefttext($im, $size, 0, $tx+2, $ty+2, $blue, './gbi.ttf', $count);
	imagefttext($im, $size, 0, $tx, $ty, $white, './gbi.ttf', $count);
}
else {
	$im = imagecreatefrompng('sb_shout.png');
}
header('Content-Type: image/png');
imagesavealpha($im, true);
imagepng($im, './images/rs-'.$count.'.png');
imagepng($im);
?>