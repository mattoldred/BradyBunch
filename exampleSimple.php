<?php

include "BradyBunch.php";

//$bb = new MattOldred\BradyBunch\PrintJob( 'TestPrint', 'M6-9-423' ); // Change this to your size of label
$bb = new MattOldred\BradyBunch\PrintJob( 'TestPrint', new MattOldred\BradyBunch\LabelType('M6-9-423',650, 200) ); // Change this to your size of label, dimensions are in thousands of an inch
//$bb->Timeout = 5; 
//$bb->SaveSmallLabels = true;
//$bb->PostPrintOperations = MattOldred\BradyBunch\PrintJob::EndOfJob; // cut at end of job


$page = $bb->createPage();
$text = "12345";
$textcolor = imagecolorallocate($page, 0, 0, 0);
$font = __DIR__.'/verdanab.ttf';                                     // Change to path to your favourite ttf font
$font_size = 21;
$image_width = imagesx($page); 
$image_height = imagesy($page);
$text_box = imagettfbbox($font_size,0,$font,$text);
$text_width = $text_box[2]-$text_box[0];
$text_height = $text_box[7]-$text_box[1];
$x = ($image_width/2) - ($text_width/2);
$y = ($image_height/2) - ($text_height/2);
imagettftext($page, $font_size, 0, $x, $y, $textcolor, $font, $text);
$bb->addPage($page);

try
{
  $bb->print("192.168.0.1");                                         // Change to ip or hostname of your printer
}
catch (Exception $e) 
{
  die('Cannot contact printer! Check printer is on and connect to WiFi, check IP address is correct.');
}

?>