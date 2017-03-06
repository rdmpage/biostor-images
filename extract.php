<?php

require_once('spatial.php');

$config['convert']			= '/usr/local/bin/convert';
$config['ghostscript'] 		= '/usr/local/bin/gs';

$abbyy_filename = 'biostor-172504_abbyy.xml';
$pdf_filename = 'biostor-172504.pdf';

$abbyy_filename = 'biostor-134591_abbyy.xml';
$pdf_filename = 'biostor-134591.pdf';

$abbyy_filename = 'biostor-115612_abbyy.xml';
$pdf_filename = 'biostor-115612.pdf';
	
	
$xml = file_get_contents($abbyy_filename);

//echo $xml;

$pictures = array();

$page_counter = 0;

$dom = new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace('abbyy','http://www.abbyy.com/FineReader_xml/FineReader6-schema-v1.xml');

// page
$pages = $xpath->query ('//abbyy:page');
foreach($pages as $page)
{
	$height = 0;
	$width = 0;


	echo "------\n";
	$nodes = $xpath->query ('@height', $page);
	foreach($nodes as $node)
	{
		$height =  $node->firstChild->nodeValue;
	}
	
	$nodes = $xpath->query ('@width', $page);
	foreach($nodes as $node)
	{
		$width = $node->firstChild->nodeValue;
	}
	
	// 

	// blocks and relationships between them
	
	
	$blocks = array();
	
	$nodes = $xpath->query ('abbyy:block', $page);
	foreach($nodes as $node)
	{
		if ($node->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $node->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		
			$block = new stdclass;
			$block->left 	= $attributes['l'];
			$block->top 	= $attributes['t'];
			$block->right 	= $attributes['r'];
			$block->bottom 	= $attributes['b'];
			
			// block type
			$block->type 	= $attributes['blockType'];
			
			// block id
			
			// block text
			$block->text = array();
			$ns = $xpath->query ('abbyy:text/abbyy:par', $node);
			foreach($ns as $n)
			{
				$text = '';
				$pars = $xpath->query ('abbyy:line/abbyy:formatting/abbyy:charParams', $n);
				foreach($pars as $par)
				{				
					$text .= $par->firstChild->nodeValue;
				}
				$block->text[] = $text;
			}
			
			
			$blocks[] = $block;
			
		}
	
	
	
	}
	
	print_r($blocks);
	
	// SVG for debugging, need to work out relationships between blocks
	$svg = '<?xml version="1.0" ?>
<svg xmlns:xlink="http://www.w3.org/1999/xlink" 
		xmlns="http://www.w3.org/2000/svg"
		width="2000px" 
		height="2000px" >';	

	$svg .= '<g transform="scale(0.1)">';

	$rects = array();	


	foreach ($blocks as $block)
	{
		$x = $block->left;
		$y = $block->top;
		$w = $block->right - $block->left;
		$h = $block->bottom - $block->top;

		$rects[] = new Rectangle($x,$y,$w,$h);
	}

	foreach ($rects as $r)
	{	
		$svg .= $r->toSvg();
	}	

	// relationships
	$n = count($rects);
	for ($i = 0; $i < $n-1; $i++)
	{
		for ($j = 1; $j < $n; $j++)
		{	
			// to do: ignore some blocks, such as headers, footers, and text blocks
	
			$go = true;
				
			if ($go)
			{
				$line = new Line();
				$line->fromPoints($rects[$i]->getCentre(), $rects[$j]->getCentre());
		
				// does this line hit any other rects?
				$hits = 0;
				for ($k = 0; $k < $n; $k++)
				{
					if ($k != $i && $k != $j)
					{
						if ($rects[$k]->intersectsLine($line))
						{
							$hits++;
						}
					}
				}
				if ($hits == 0)
				{				
					$svg .= $line->toSvg();
				}
			}
		}	
	}



		$svg .= '</g>';
	$svg .= '</svg>';

	file_put_contents('tmp/' . ($page_counter + 1) . '.svg', $svg);	
	

	
	// ABBYY may classify figures as either Picture or Table so let's grab both
	$nodes = $xpath->query ('abbyy:block[@blockType="Picture" or @blockType="Table"]', $page);
	foreach($nodes as $node)
	{
		if ($node->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $node->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		
			$picture = new stdclass;
			$picture->left 		= $attributes['l'];
			$picture->top 		= $attributes['t'];
			$picture->right 	= $attributes['r'];
			$picture->bottom 	= $attributes['b'];
		
			$picture->height = $picture->bottom - $picture->top;
			$picture->width = $picture->right - $picture->left;
			
			
			$picture->rect = new Rectangle($picture->left, $picture->top, $picture->width, $picture->height);
		
			if (!isset($pictures[$page_counter]))
			{
				$pictures[$page_counter] = array();
			}
			
			// ABBY XML can duplicate picture blocks (sigh) 
			// so we need to check for overlap with existing pictures
			
			$add = true;
			foreach ($pictures[$page_counter] as $p)
			{
				if ($picture->rect->intersectsRect($p->rect))
				{
					$add = false;
				}
			}
			
			if ($add)
			{
				$pictures[$page_counter][] = $picture;
			}
		}
		
	}
	
	
	// if we have pictures we need to extract them
	if (isset($pictures[$page_counter]))
	{
		// Get image from PDF
		
		$image_filename = 'tmp/' . ($page_counter + 1) . '.jpg';
	
		// http://stackoverflow.com/questions/977540/convert-a-pdf-to-a-transparent-png-with-ghostscript
		// Make images bigger to start with then resize to get better text quality
		$dpi = 72;
		$dpi = 288;

		$command = $config['ghostscript']
			. ' -dNOPAUSE '
			. ' -sDEVICE=jpeg '
			. ' -sOutputFile=' . $image_filename
			. ' -r' . $dpi
			. ' -dFirstPage=' . ($page_counter + 1)
			. ' -dLastPage=' . ($page_counter + 1)
			. ' -q ' . $pdf_filename
			. ' -c quit';	
	
		echo $command . "\n";
		system($command);
		
		// get image dimensions
		list($image_width, $image_height) = getimagesize($image_filename);
		
		$scale = $image_width / $width;
		
		// extract images from page
		$count = 0;
		foreach ($pictures[$page_counter] as $picture)
		{
			$geometry = ($picture->width * $scale)
				. 'x' .  ($picture->height * $scale)
				. '+' . ($picture->left * $scale)
				. '+' . ($picture->top * $scale);
								
			$figure_filename = 'tmp/' . ($page_counter + 1) . '-' . $count . '.jpg';
				
			$command = $config['convert']
				. ' ' . $image_filename
				. ' -crop ' . $geometry
				. ' ' . $figure_filename;
			echo $command . "\n";
			system($command);
			
			$count++;
		}
		
		// cleanup
		unlink($image_filename);
	
	}
	
	
	$page_counter++;			
	
}

print_r($pictures);

?>