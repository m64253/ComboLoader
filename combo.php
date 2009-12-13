<?php
require_once 'ComboLoader.php';

$comboLoader = new ComboLoader(array(
	'yui_compressor_path'	=> '/Users/mikaelabrahamsson/Development/yuicompressor/build/yuicompressor-2.4.3.jar',
	'use_cache'				=> false,
	'version_pattern'		=> '[0-9]{2}w[0-9]{2}',
	'assets_path'			=> 'assets',
	'js_directory_name'		=> 'scripts',
	'css_directory_name'	=> 'style',
	'js_minifier'			=> MINIFIER_CLOSURE
));

$comboLoader->auto();