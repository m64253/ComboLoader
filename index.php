<?php
require_once 'ComboLoader.php';

$comboLoader = new ComboLoader(array(
	'yui_compressor_path'	=> '/Users/mikaelabrahamsson/Development/yuicompressor/build/yuicompressor-2.4.3.jar',
	'use_cache'				=> true,
	'version_pattern'		=> '[0-9]{2}w[0-9]{2}',
	'assets_path'			=> 'assets',
	'js_directory_name'		=> 'scripts',
	'css_directory_name'	=> 'style',
	'js_minifier'			=> 'yui'
));

// $comboLoader->auto();
$comboLoader->handle('09w47/?self.js&common.js');