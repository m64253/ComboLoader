<?php

if (!function_exists('prePrint')) { function prePrint($data, $title = null, $return = false) { $out = array(); if (!empty($title)) { $out[] = '<h3>' . $title . '</h3>'; } $out[] = '<pre>'; $out[] = print_r($data, true); $out[] = '</pre>'. "\n"; if ($return === true) { return implode("\n", $out); } else { echo implode("\n", $out); } } }

require_once 'ComboLoader.php';

$comboLoader = new ComboLoader(array(
	'yui_compressor_path'	=> '/Users/mikaelabrahamsson/Development/yuicompressor/build/yuicompressor-2.4.3.jar',
	'use_cache'				=> true,
	'js_minifier'			=> 'yui'
));

$comboLoader->auto();
// $comboLoader->handle('09w47/?self.js&common.js');