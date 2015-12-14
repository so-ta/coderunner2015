<?php

function console($message){
	$debug = debug_backtrace();
	$line = str_pad($debug[0]['line'], 4, ' ', STR_PAD_LEFT);
	echo $line.':';
	if( !is_array($message) ){
		echo $message;
	}else{
		var_dump($message);
	}

	echo PHP_EOL.PHP_EOL;
}