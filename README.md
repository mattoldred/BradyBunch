
# BradyBunch

PHP Wrapper for printing to Brady Label Printers

Brady make a nice range of label printers but require you to use their Windows PC software or mobile Apps. I required a linux solution that could automatically print labels from our internal systems.  

After reverse engineering the PRN files that the provided software generates I was able to mash together this PHP code to allow our linux server to generate custom PRN files and send them to the printer over WiFi.

I have only tested this with the Brady M611 but it should be compatible with the M610 and M710.
## Deployment

To deploy this project run

```bash
  git clone https://github.com/mattoldred/BradyBunch
```

You must have Yann Collet's 'lz4' installed on your system

Optionally imagemagick's 'convert' is required for some advanced features.
## Usage/Examples

```php
include "BradyBunch/BradyBunch.php";

$bb = new MattOldred\BradyBunch\PrintJob( 'TestPrint', 'M6-9-423' ); // Change this to your size of label

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
  $bb->print("192.168.0.135");                                       // Change to ip of your printer
}
catch (Exception $e) 
{
  die('Cannot contact printer! Check printer is on and connect to WiFi, check IP address is correct.');
}
```


## Roadmap

- Pure PHP lz4 encoding

- More label sizes


## Acknowledgements

 - [LZ4 Decompression in Pure PHP](http://heap.ch/blog/2019/05/18/lz4-decompression/)
 - [README Editor](https://readme.so/editor)

