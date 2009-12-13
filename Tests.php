<?php
require_once 'ComboLoader.php';

require_once 'PHPUnit/Framework.php';
class ComboLoaderTest extends PHPUnit_Framework_TestCase {
	
	public function setUp() {
		ComboLoader::$dryRun = true;

		$this->comboLoader = new ComboLoader(array(
			'yui_compressor_path'	=> '/Users/mikaelabrahamsson/Development/yuicompressor/build/yuicompressor-2.4.3.jar',
			'use_cache'				=> true,
			'version_pattern'		=> '[0-9]{2}w[0-9]{2}',
			'assets_path'			=> 'assets',
			'js_directory_name'		=> 'scripts',
			'css_directory_name'	=> 'style',
			'js_minifier'			=> 'yui'
		));
	}

	public function tearDown() {
		$this->comboLoader = null;
	}
		
    public function testHandle() {
        $t1 = $this->comboLoader->handle('09w47/?self.js&common.js');
		$e1 = array(
		    0 => 'assets/09w47/components/self/scripts/new.js',
		    1 => 'assets/09w47/components/self/scripts/self.js',
		    2 => 'assets/09w47/components/common/scripts/browser-fixes.js',
		    3 => 'assets/09w47/components/common/scripts/center.js',
		    4 => 'assets/09w47/components/common/scripts/eniro.js',
		    5 => 'assets/09w47/components/common/scripts/equalHeightBoxes.js',
		    6 => 'assets/09w47/components/common/scripts/jquery.xml2json.js',
		    7 => 'assets/09w47/components/common/scripts/jquery.xml2json.pack.js'
		);
		$this->assertEquals($e1, $t1, 'Handle case 1');

		$t2 = $this->comboLoader->handle('09w47/self/new.js');
		$e2 = array(
			0 => 'assets/09w47/components/self/scripts/new.js'
		);
		$this->assertEquals($e2, $t2, 'Handle case 2');

		$t3 = $this->comboLoader->handle('09w47?self/new.js');
		$e3 = array(
			0 => 'assets/09w47/components/self/scripts/new.js'
		);
		$this->assertEquals($e3, $t3, 'Handle case 3');

		$t4 = $this->comboLoader->handle('09w47/self.js&09w45/self.js');
		$e4 = array(
		    0 => 'assets/09w47/components/self/scripts/new.js',
		    1 => 'assets/09w47/components/self/scripts/self.js',
		    2 => 'assets/09w45/components/self/scripts/new.js',
		    3 => 'assets/09w45/components/self/scripts/self.js'
		);
		$this->assertEquals($e4, $t4, 'Handle case 4');

		$t5 = $this->comboLoader->handle('/self/scripts/new.js');
		$e5 = array(
			0 => 'assets/09w47/components/self/scripts/new.js'
		);
		$this->assertEquals($e5, $t5, 'Handle case 5');
    }
}
/*
$t = new ComboLoaderTest();
$t->setUp();
$t->testHandle1();
$t->testHandle2();
$t->testHandle3();
$t->testHandle4();
$t->testHandle5();
*/