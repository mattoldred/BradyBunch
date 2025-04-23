<?php

if( isset($_GET["text"]) )
{
	include "BradyBunch.php";
	
	$bb = new MattOldred\BradyBunch\PrintJob( 'TestPrint', 'M6-9-423' ); // Change this to your size of label

	$page = $bb->createPage();
	$text = $_GET["text"];
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
	
	
	if( isset($_GET["print"]) )
	{
		try
		{
			$bb->print($_GET["ip"]);
			$ret = (object) array('Success' => true, 'Message' => 'Printed OK');
		}
		catch (Exception $e) 
		{
			$ret = (object) array('Success' => false, 'Message' => 'Cannot contact printer! Check printer is on and connect to WiFi, check IP address is correct.');
		}
	}
	else
	{
		$ret = (object) array('Success' => true, 'Message' => '');

		$ret->Preview = $bb->pageToIMGSRC($page);
	}
	header('Content-type: application/json');
	die(json_encode($ret));
}
?>

<html>
<head>
<style>
	#previewImg {
	  height: 2em;
	  border: 1px solid black;
	  border-radius: 0.4em;
	}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
$( document ).ready(function() {
    $('#printButton').on( "click", function() {
		$('#messages').text("Printing...");
		$.ajax({
			url: window.location.href,
			type: "get",
			data: { 
				print: true, 
				text: $('#printText').val(),
				ip: $('#ipAddress').val()
			},
			success: function(response) {
				if(response.Success)
					$('#messages').text("Success");
				else if(response.Success === false)
					$('#messages').text("Failed - " + response.Message);
				else
					$('#messages').text("Failed");
			},
			error: function(xhr) {
				$('#messages').text("Failed");
			}
		});
	});
	function updatePreview()
	{
		$.ajax({
			url: window.location.href,
			type: "get",
			data: {
				text: $('#printText').val()
			},
			success: function(response) {
				if(response.Success)
				{
					$('#previewImg').attr('src',response.Preview);
					$('#previewDiv').show();
				}
				else
					$('#previewDiv').hide();
			},
			error: function(xhr) {
				$('#previewDiv').hide();
			}
		});
	}
	$('#printText').on( "input", function() {
		updatePreview();
	});
	updatePreview();
});
</script>
</head>
<body>
	<input type="text"   name="printText" id="printText" placeholder="12345" value="12345" maxlength="8"/><br/>
	<input type="text"   name="ipAddress" id="ipAddress" placeholder="e.g. 192.168.0.1" value=""/><br/>
	<input type="button" id="printButton" value="Print"><br/>
	<span id="messages">Loaded...</span>
	
	<div id="previewDiv">
	<br/>
		<span>Preview:</span><br/>
		<img id="previewImg" />
</body>
</html>