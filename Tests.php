<?php
require_once 'ComboLoader.php';

ComboLoader::$dryRun = true;

$comboLoader = new ComboLoader(array(
	'yui_compressor_path'	=> '/Users/mikaelabrahamsson/Development/yuicompressor/build/yuicompressor-2.4.3.jar',
	'use_cache'				=> true,
	'version_pattern'		=> '[0-9]{2}w[0-9]{2}',
	'assets_path'			=> 'assets',
	'js_directory_name'		=> 'scripts',
	'css_directory_name'	=> 'style',
	'js_minifier'			=> 'yui'
));

$t1 = $comboLoader->handle('09w47/?self.js&common.js');
print_r($t1);

$t2 = $comboLoader->handle('09w47/self/new.js');
print_r($t2);

$t3 = $comboLoader->handle('09w47?self/new.js');
print_r($t3);

$t3 = $comboLoader->handle('09w47/self.js&09w45/self.js');
print_r($t3);

$t4 = $comboLoader->handle('/self/scripts/new.js');
print_r($t4);