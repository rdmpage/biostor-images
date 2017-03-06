<?php

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/lib.php');


$biostor = 115612;

$identifier = 'biostor-' . $biostor;

$image_filename = 'tmp/7-0.jpg';
$image_name = $identifier . '_7-0.jpg';

// https://archive.org/download/biostor-115612/biostor-115612_7-0.jpg				
		
				// upload to IA
				$headers = array();
				
				// x-archive-queue-derive
				$headers[] = '"x-archive-queue-derive:0"';
					
				// authorisation
				$headers[] = '"authorization: LOW ' . $config['s3_access_key']. ':' . $config['s3_secret_key'] . '"';			
			
				print_r($headers);
			
				$command = 'curl --location';
				$command .= ' --header ' . join(' --header ', $headers);
				$command .= ' --upload-file ' . $image_filename;
				$command .= ' http://s3.us.archive.org/' . $identifier . '/' . $image_name;

				echo $command . "\n";

				system ($command);
				
?>				