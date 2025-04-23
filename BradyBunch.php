<?php

/*
Brady Bunch:
MIT License

Copyright (c) 2024 Matthew Aldred

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/*
LTZ Decompression:
MIT License

Copyright (c) 2019 Stephan J. Müller

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

namespace MattOldred\BradyBunch;

use Exception;
use GD;
use ReflectionClass;

class LabelType
{
	public string $SubstratePart;
	public int $inWidth;
	public int $inHeight;
	public int $pxWidth;
	public int $pxHeight;
	
	public function __construct($SubstratePart, $inWidth=0, $inHeight=0, $pxWidth=0, $pxHeight=0)
    {
		if($inWidth == 0 && $inHeight==0 && $pxWidth==0 && $pxHeight==0 )
		{
			if( array_key_exists($SubstratePart, self::BUILTIN) )
			{
				$this->SubstratePart = $SubstratePart; 
				list($this->inWidth, $this->inHeight, $this->pxWidth, $this->pxHeight) = self::BUILTIN[$SubstratePart];
			}
			else
				throw new Exception ('Invalid LabelType "'.$SubstratePart.'"');
		}
		else
		{
			if($pxWidth==0 && $pxHeight==0 )
			{
				$pxWidth=$inWidth*0.3;
				$pxHeight=$inHeight*0.3;
			}
			$this->SubstratePart = $SubstratePart;
			$this->inWidth = $inWidth;
			$this->inHeight = $inHeight;
			$this->pxWidth = $pxWidth;
			$this->pxHeight = $pxHeight;
		}
	}
	
	const BUILTIN = array(
		'M6-9-423' => array(650, 200, 195, 60),
		'M6-11-427' => array(750, 500, 225, 150),
	);
	
	public static function getConstants()
	{
		$reflectionClass = new ReflectionClass(static::class);
		return $reflectionClass->getConstants();
	}
}

class PrintJob
{
	const MAGICSTRING = '7f42ee41a91d40909becff7a6614cc22';
	
    const HowAboutNever = 0; //
	const EndOfJob = 1;
    const EndOfLabel = 2; //not possible with smaller labels
	
	public string $JobName;
	public LabelType $LabelType;
	public string $JobCreator='BradyBunch';
	public string $JobID; //c24b90442c11435da38dfa4ee11b0c4a
	public string $JobTime; //20240423155838
	//public int $NumberOfPages = 0;
	public string $JobType="Print";
	public string $JobSource="BWS"; //Unsure if this is allowed to change
	public string $JobClientId; //f2103b89-a264-4173-957e-7b7b772edbbd seems to just be a UUID, but not the same UUID as JobID
	public string $PrintSides="Simplex";
	public int $PostPrintOperations = self::EndOfJob;
	public bool $SaveSmallLabels=true;
	
	public array $Pages = array();
	
	private function generateUUID($length) 
	{
		$random = '';
		for ($i = 0; $i < $length; $i++)
			$random .= dechex( rand(0, 15) );
		return $random;
	}
	
	public function __construct(string $jobName, $labelType)
    {
        $this->JobName = $jobName;
		
		if($labelType instanceof LabelType)
			$this->LabelType = $labelType;
		elseif( is_string($labelType) )
			$this->LabelType = new LabelType($labelType);
		else
			throw new Exception ('Invalid LabelType');
		
		//TODO check labeltype
		
		$this->JobID = $this->generateUUID(32);
		$this->JobClientId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($this->generateUUID(32), 4));
		$this->JobTime = date('YmdHis');
	
    }
	public function __destruct()
	{
		foreach($this->Pages as $page)
			imagedestroy($page); //not needed if php>=8.0.0
	}
	
	public function createPage()
	{
		$i = imagecreatetruecolor($this->LabelType->pxWidth,$this->LabelType->pxHeight);
		imagefill($i,0,0,imagecolorallocate($i, 255, 255, 255));
		return $i;
	}
	public function addPage($page)
	{
		if (!is_resource($page) && !($page instanceof \GdImage)) 
			throw new Exception('Value must be a GdImage '.(is_resource($page)?'true':'false').' '.(($page instanceof \GdImage)?'true':'false').' '.get_class($page) );  //php>=8.0.0
		
		imagetruecolortopalette($page, false, 2);
		$this->Pages[] = $page;
	}
	
	public function pageToIMG($page)
	{
		return "<img style='border: solid red; padding:2px;' src='data:image/png;base64, ".$this->pageToIMGSRC($page)."'></img>\n";
	}
	public function pageToIMGSRC($page)
	{
		ob_start();
		imagetruecolortopalette($page, false, 2);
		imagepng($page);
		$image_data = ob_get_contents();
		ob_end_clean();
		$b64 = base64_encode($image_data);
		return "data:image/png;base64, $b64";
	}
	
	public function createBMP($im)
	{
		//Strangeness about Brady's version of bmp
		//Bitmap height is negative? Ah, that means it's stored from top to bottom.
		//Has been padded with 1s instead of zeroes, this is allowed
		$w = imagesx($im);
		$h = imagesy($im);
		
		$pixelWidth = imagesx($im); //https://stackoverflow.com/questions/11167706/create-1-bit-bitmap-monochrome-in-php
		$pixelHeight = imagesy($im);

		$dwordAlignment = 32 - ($pixelWidth % 32);
		if ($dwordAlignment == 32) {
			$dwordAlignment = 0;
		}
		$dwordAlignedLength = $pixelWidth + $dwordAlignment;
		$pixelArray = '';
		for($i=0;$i<$h;$i++)
		{
			$row='';
			for($j=0;$j<$w;$j++)
				$row .= (imagecolorat($im, $j, $i)==0 ? '0' : '1');
			$dwordAlignedPixelRow = str_pad($row, $dwordAlignedLength, '1', STR_PAD_RIGHT);
			$integerPixelRow = array_map('bindec', str_split($dwordAlignedPixelRow, 8));
			$pixelArray .= implode('', array_map('chr', $integerPixelRow));
		}
		$pixelArraySize = \strlen($pixelArray);
		$colorTable = pack(
			'CCCxCCCx',
			//blue, green, red
			0,    0,     0,    // 0 color
			255,  255,   255, // 1 color
		);
		$colorTableSize = \strlen($colorTable);
		$dibHeaderSize = 40;
		$colorPlanes = 1;
		$bitPerPixel = 1;
		$compressionMethod = 0; //BI_RGB/NONE
		$horizontal_pixel_per_meter = 11811;
		$vertical_pixel_per_meter = 11811;
		$colorInPalette = 0;
		$importantColors = 0;
		$dibHeader = \pack('VVVvvVVVVVV', $dibHeaderSize, $pixelWidth, -$pixelHeight, $colorPlanes, $bitPerPixel, $compressionMethod, $pixelArraySize, $horizontal_pixel_per_meter, $vertical_pixel_per_meter, $colorInPalette, $importantColors);
		$bmpFileHeaderSize = 14;
		$pixelArrayOffset = $bmpFileHeaderSize + $dibHeaderSize + $colorTableSize;
		$fileSize = $pixelArrayOffset + $pixelArraySize;
		$bmpFileHeader = pack('CCVxxxxV', \ord('B'), \ord('M'), $fileSize, $pixelArrayOffset);
		$bmpFile = $bmpFileHeader . $dibHeader . $colorTable . $pixelArray;
		return $bmpFile;
	}
	
	
	public function encodeBMP($bmpdata)
	{
		//exec("lz4 $bmpfile -c > $lz4file");
		//$fileContent = file_get_contents($lz4file);
		
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
           2 => array("pipe", "w"),  // stderr
	    );
		$process = proc_open('lz4 -c', $descriptorspec, $pipes);
		if (!is_resource($process))
			die("proc error\n");
		fwrite($pipes[0], $bmpdata);
		fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $return_value = proc_close($process);
        if($return_value!=0)
            die($stderr);
		$fileContent = $stdout;
		
		$byteArray3 = unpack('Vmagic/CFLG', $fileContent); 
		if($byteArray3['magic'] != 0x184D2204)
			die("No magic");
		$byteArray3['magic'] = '0x'.dechex($byteArray3['magic']);
		//print_r($byteArray3);
		$flg = $byteArray3['FLG'];
		
		$headersize = 11; //could be up to 23, counting block header as part of this, assuming only one block. 
		$postheadersize = 4; //could be 8
		if($flg & 0b00001000) //C.Size
			$headersize+=8;
		if($flg & 0b00000001) //DictID
			$headersize+=4;
		if($flg & 0b00000100) //C.Checksum
			$postheadersize+=4;
		if($flg & 0b00010000) //B.Checksum
			$postheadersize+=4;
		//echo $headersize."\n";
		//echo $postheadersize."\n";
		
		$fileContent = substr($fileContent, $headersize, strlen($fileContent)-$headersize-$postheadersize); //extract block
		$b64 = base64_encode($fileContent);
		//$b64 = str_replace("/", "\/", $b64); //escape slashes
		//echo $b64;
		return $b64;
	}
	
	public function generatePRN()
	{
		$tempPages = $this->Pages;
		$post = $this->PostPrintOperations;
		
		if($this->SaveSmallLabels && $post!=self::HowAboutNever)
		{
			$totalHeight = count($tempPages)* $this->LabelType->inHeight;
			if($totalHeight<400)
				$tempPages[] = $this->createPage(); // add blank label to stop them falling into machine (on M611 in any case)
			
			if($this->LabelType->inHeight <= 500 && $post==self::EndOfLabel) //600 is same as Brady uses
				$post = self::EndOfJob;
		}
		
		$tj = (object) [
			'JobID' 		=> $this->JobID,
			"JobName" 		=> $this->JobName,
			"JobCreator" 	=> $this->JobCreator,
			"JobTime" 		=> $this->JobTime,
			"NumberOfPages" => count($tempPages),
			"SubstratePart" => $this->LabelType->SubstratePart,
			"JobType" 		=> $this->JobType,
			"JobSource" 	=> $this->JobSource,
			"PrintSides" 	=> $this->PrintSides,
			"JobClientId" 	=> $this->JobClientId
		];
		$jj = json_encode($tj);
		
		$output = pack('H32L1A*', self::MAGICSTRING, strlen($jj), $jj);
		
		$n = 0;
		foreach($tempPages as $page)
		{
			$bmpdata = $this->createBMP($page);
			$b64 = $this->encodeBMP($bmpdata);
			
			if($post==self::HowAboutNever)
				$cut = false;
			elseif($post==self::EndOfLabel)
				$cut = true;
			elseif($post==self::EndOfJob)
				$cut = ($n==count($tempPages)-1);
			else
				throw new Exception("Invalid PostPrintOperations");
			
			$tp = (object) [
				"PrintFileName" 		=> 'Page'.($n+1).'.prn',
				"JobID" 				=> $this->JobID,
				"PageNumber" 			=> $n,
				"LabelWidth" 			=> $this->LabelType->inWidth,
				"LabelHeight" 			=> $this->LabelType->inHeight,
				"Pages"         		=> (object) [	
														"Layers" => array( (object) [ "Bitmap"=> $b64,"Compression" => "lz4", "Color" => (object) ["Red"=>0,"Green"=>0,"Blue"=>0] ] ),
														"PrePrintOperations"	=> array(),
														"PostPrintOperations"	=> array( 
																								$cut
																							?
																								(object) ["Cut"=> "Shear"] // sometimes BWS adds ,{"Tear":"Tear"} but unclear when or why
																							:
																								(object) ["Separator"=> "DrawCutMarks"] 
																						)
													]
			];
			$pj = json_encode($tp);
			
			$output .= pack('H32L1A*', self::MAGICSTRING, strlen($pj), $pj);
			$n++;
		}
		//die($output);
		return $output;
	}
	
	//returns array of b64 encoded png images
	public function preview()
	{
		$srcs = array();
		foreach($this->Pages as $page)
		{
			$srcs[] = $this->pageToIMGSRC($page);
		}
		return $srcs;
	}
	public function print($ip)
	{
		$data = $this->generatePRN();
		try
		{
			$fp=pfsockopen($ip,9100);
			
			if(!$fp)
				throw new Exception("Could not contact $ip");
			
			fputs($fp,$data);
			fclose($fp);

			//echo 'Successfully Printed';
		}
		catch (Exception $e) 
		{
			//echo 'Caught exception: ',  $e->getMessage(), "\n";
			throw $e;
		}
	}

	//TODO discovery
}
class LoadAndPrint extends PrintJob
{
	
	public function __construct()
    {
    }
	public function loadPRN($fileContent)
	{
		$job = $this->parsePRN($fileContent);
		
		$this->JobName = $job['Job']->JobName;
		$this->LabelType = new LabelType($job['Job']->SubstratePart);
		$this->JobCreator=$job['Job']->JobCreator;
		$this->JobID=$job['Job']->JobID;
		$this->JobTime=$job['Job']->JobTime;
		$this->JobType=$job['Job']->JobType;
		$this->JobSource=$job['Job']->JobSource;
		$this->JobClientId=$job['Job']->JobClientId;
		$this->PrintSides=$job['Job']->PrintSides;
		
		$allCut = true;
		$allDraw = true;
		foreach($job['Pages'] as $pj)
		{
			$page = $this->createPage();
			
			$bmp = $this->decodeBMP($pj->Pages->Layers[0]->Bitmap);
			$page = $this->createImageFromBMP($bmp);
			
			//print_r( $pj->Pages->PostPrintOperations);echo "\n";
			$allDraw &= property_exists($pj->Pages->PostPrintOperations[0], "Separator") ;
			$allCut &= property_exists($pj->Pages->PostPrintOperations[0], "Cut") ;
			
			$this->addPage($page);
		}
		if($allCut)
			$this->PostPrintOperations = self::EndOfLabel;
		elseif($allDraw)
			$this->PostPrintOperations = self::HowAboutNever;
		else
			$this->PostPrintOperations = self::EndOfJob;
	}
	
	function createImageFromBMP($bmpdata) //GD has a bug opening 1bit BMPs with imagecreatefrombmp
	{
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
           2 => array("pipe", "w"),  // stderr
	    );
		$process = proc_open('convert - png:-', $descriptorspec, $pipes);
		if (!is_resource($process))
			die("proc error\n");
		fwrite($pipes[0], $bmpdata);
		fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $return_value = proc_close($process);
        if($return_value!=0)
            die($stderr);
		$pngdata = $stdout;
		return imagecreatefromstring($pngdata);		
	}
	
	protected function lz4decode($in, $offset = 0) 
	{ //http://heap.ch/blog/2019/05/18/lz4-decompression/
		$len = strlen($in);
		$out = '';
		$i = $offset;
		
		$take = function() use ($in, &$i) {
			return ord($in[$i++]);
		};
		
		$addOverflow = function(&$sum) use ($take) 
		{
			do 
			{
				$sum += $summand = $take();
			} while ($summand === 0xFF);
		};
		
		while ($i < $len) 
		{
			$token = $take();
			$nLiterals = $token >> 4;
			if ($nLiterals === 0xF) 
				$addOverflow($nLiterals);
			$out .= substr($in, $i, $nLiterals);
			$i += $nLiterals;
			if ($i === $len) 
				break;
			$offset = $take() | $take() << 8;
			$matchlength = $token & 0xF;
			if ($matchlength === 0xF) 
				$addOverflow($matchlength);
			$matchlength += 4;
			$j = strlen($out) - $offset;
			while ($matchlength--) 
				$out .= $out[$j++];
		}
		return $out;
	}
	
	function decodeBMP($base64) //returns data that can be opened in e.g. MSPaint 
	{
		$lz4block = base64_decode($base64);
		//echo $lz4block."\n";
		$bmpdata = $this->lz4decode($lz4block);
		return $bmpdata;
	}
	
	protected function parsePRN($fileContent)
	{
		if( strlen($fileContent) < 20)
			die("Too small 1");
		
		// Convert the file content to a byte array 
		//$byteArray = unpack('C*', $fileContent); 
		
		$byteArray1 = unpack('H32magic/Llength', $fileContent); 
		//print_r($byteArray1);
		
		if( $byteArray1['magic']!= self::MAGICSTRING )
			die("Main magic Error");
		$jobJSONsize = $byteArray1['length'];
		if( strlen($fileContent) < 20+$jobJSONsize)
			die("Too small 2");
		
		$jobJSON = unpack('A'.$jobJSONsize, $fileContent, 20); 
		//echo $jobJSON[1];
		$jobJSONdata = json_decode($jobJSON[1]);
		//print_r($jobJSONdata );
		
		$numPages = $jobJSONdata->NumberOfPages;
		$job = array('Job'=>$jobJSONdata, 'Pages'=>array());
		$pointer = 20+$jobJSONsize;
		
		for($i = 0; $i < $numPages ; $i++)
		{
			if( strlen($fileContent) < $pointer+20)
				die("Too small 1-$i");
			$byteArray2 = unpack('H32magic/Llength', $fileContent, $pointer); 
			if( $byteArray2['magic']!= self::MAGICSTRING )
				die("Page$i magic Error");
			$pageJSONsize = $byteArray2['length'];
			if( strlen($fileContent) < $pointer+20+$pageJSONsize)
				die("Too small Page$i");
			
			$pageJSON = unpack('A'.$pageJSONsize, $fileContent, $pointer+20); 
			//echo $jobJSON[1];
			$pageJSONdata = json_decode($pageJSON[1]);
			//print_r($pageJSONdata );
			$job['Pages'][] = $pageJSONdata;
			$pointer += 20+$pageJSONsize;
		}
		if($pointer!=strlen($fileContent))
			die("Extra content after end of file");
		return $job;
	}
}
?>