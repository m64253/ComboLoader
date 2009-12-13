<?php

class ComboLoader_Exception extends Exception { }
class ComboLoader_Exception_Option extends ComboLoader_Exception { }
class ComboLoader_Exception_Type extends ComboLoader_Exception { }
class ComboLoader_Exception_Normalize extends ComboLoader_Exception { }
class ComboLoader_Exception_Version extends ComboLoader_Exception { }
class ComboLoader_Exception_DirectoryFiles extends ComboLoader_Exception { }
class ComboLoader_Exception_Cache extends ComboLoader_Exception { }
class ComboLoader_Exception_Filename extends ComboLoader_Exception { }
class ComboLoader_Exception_Build extends ComboLoader_Exception { }
class ComboLoader_Exception_Content extends ComboLoader_Exception { }

set_exception_handler('ComboLoader::exceptionHandler');

define("MINIFIER_YUI", "YUI");
define("MINIFIER_CLOSURE", "Closure");

/**
 * Combo Loader
 * 	A simple class for combining, serving, minify/compressing static files such as javascript and stylesheets.
 *
 * @package default
 * @author Mikael Abrahamsson
 */
class ComboLoader {
	/** 
	 * Options
	 *
	 * @var array
	 */
	protected $_options = array(
		'normalize_base'			=> array('components'),
		'normalize_full_length'		=> 6,
		'js_directory_name'			=> 'Scripts',
		'css_directory_name'		=> 'Styles',
		'assets_path'	 			=> 'Assets',
		'cache_path' 				=> '/tmp',
		'yui_compressor_path'		=> false,
		'version_pattern' 			=> '[0-9]+',
		'js_content_type'			=> 'application/x-javascript',
		'css_content_type'			=> 'text/css',
		'self_path'					=> 'combo',
		'self_file'					=> 'Combo.php',
		'use_cache'					=> false,
		'default_filename_type' 	=> 'min',
		'charset'					=> 'UTF-8',
		'minify'					=> true,
		'js_minifier'				=> MINIFIER_YUI,
		'closure_params'			=> array('output_format' => 'text', 'output_info' => 'compiled_code', 'compilation_level' => 'ADVANCED_OPTIMIZATIONS'),
		'closure_path'				=> 'closure-compiler.appspot.com/compile',
		'debug_header'				=> true
	);
	
	/**
	 * Used for storing the version
	 *
	 * @var string
	 **/
	protected $_version = null;
	
	/**
	 * Used for storing the type, can be either js or css
	 *
	 * @var string
	 */
	protected $_type = null;
	
	/**
	 * @var string
	 */
	protected $_filename = null;
	
	/**
	 * @var string
	 */
	protected $_content;
	
	/**
	 * @var string
	 */
	protected $_requestParams;
	
	/**
	 * @var integer
	 */
	protected $_fullLength;
	
	/**
	 * @var integer
	 */
	protected $_timer;
	
	/**
	 * Header
	 *	User for setting headers on Exceptions by the @exceptionHandler static method
	 *
	 * @var array
	 */
	static protected $_headers = array(
		404 => 'HTTP/1.0 404 Not found',
		500 => 'HTTP/1.0 500 Internal Error'
	);
	
	/**
	 * Set to true only return the calculated file paths
	 *
	 * @var boolean
	 */
	static public $dryRun = false;
		
	/**
	 * Class constructor
	 *
	 * @param string|array $requestParams If is array marged with $options, if string then passed to the handle method
	 * @param array $options Used for setting options at instantiation time
	 * @author Mikael Abrahamsson
	 */
	public function __construct($requestParams = null, array $options = array()) {
		
		if (is_array($requestParams)) {
			$options = array_merge($requestParams, $options);
		}
		
		// Set options
		foreach ($options as $name => $value) {
			$this->setOption($name, $value);
		}
		
		if (is_string($requestParams)) {
			$this->handle($requestParams);
		}
	}
	
	/**
	 * undocumented function
	 *
	 * @param Exception $e 
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	static public function exceptionHandler(Exception $e) {
		if ($e instanceof ComboLoader_Exception) {
			header(self::$_headers[$e->getCode()]);
			echo $e->getMessage();
		} else {
			echo "Uncaught exception: " , $e->getMessage(), "\n";
		}
	}
	
	/******************************************************************************************************************************************************************/
	
	protected function startTimer() {
		if (!$this->_timer) {
			$this->_timer = microtime(true);
		}
	}
	
	/**
	 * Checks if a given option name exists and is of the current type
	 *
	 * @param string $name The name of the option a thats is requested
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getOptionName($name) {
		if (empty($name)) {
			throw new ComboLoader_Exception_Option('Empty option name', 500);
		}
		
		if (!is_string($name)) {
			throw new ComboLoader_Exception_Option('Invalid option name. Expexted type "string" got "' . gettype($name) . '"', 500);
		}
		
		$optionName = strtolower(trim($name));
				
		if (isset($this->_options[$optionName])) {
			return $optionName;
		}

		throw new ComboLoader_Exception_Option('Invalid option name, "' . $name . '" ("' . $optionName . '")', 500);
	}
	
	/**
	 * Get option
	 *
	 * @param string $name 
	 * @return mixed
	 * @author Mikael Abrahamsson
	 */
	public function getOption($name) {
		$optionName = $this->getOptionName($name);
		return $this->_options[$optionName];
	}
	
	/**
	 * Set Option
	 *
	 * @param string $name 
	 * @param mixed $value 
	 * @return $this
	 * @author Mikael Abrahamsson
	 */
	public function setOption($name, $value) {
		$optionName = $this->getOptionName($name);
		$this->_options[$optionName] = $value;
		return $this;
	}
	
	/**
	 * Get version
	 *
	 * @param string $path 
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	public function getVersion($path = "") {
		if (preg_match('/(' . $this->getOption('version_pattern') . ')/', $path, $matches)) {
			return $matches[1];
		} elseif (empty($this->_version)) {
			throw new ComboLoader_Exception_Version('Missing version', 500);
		}
		
		return $this->_version;
	}
	
	/**
	 * Set version
	 *
	 * @param string $version 
	 * @return $this
	 * @author Mikael Abrahamsson
	 */
	public function setVersion($version) {
	   	if (empty($version)) {
			throw new ComboLoader_Exception_Version('Unable to set version, empty version', 500);
		}
		
		if (!is_string($version)) {
			throw new ComboLoader_Exception_Version('Unable to set version. expexted type "string" got "' . gettype($version) . '"', 500);
		}
		
		if (preg_match('/' . $this->getOption('version_pattern') . '/', $version)) {
			$this->_version = $version;
			return $this;
		}
		
		throw new ComboLoader_Exception_Version('Unable to set version from "' . gettype($version) . '"', 500);
	}
	
	/**
	 * Get type of from string also checks
	 *
	 * @param string $path 
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getType($path = "") {
		if (preg_match('/\.(js|css)$/i', $path, $matches)) {
			if (empty($this->_type)) {
				$this->_type = strtolower($matches[1]);
			} elseif ($this->_type !== strtolower($matches[1])) {
				throw new ComboLoader_Exception_Type('Multiple types requested, can only serve one type per request', 500);
			}
		}
		
		if (empty($this->_type)) {
			throw new ComboLoader_Exception_Type('Unable to get type from "' . $path . '"', 500);
		} 
		
		return $this->_type;
	}
	
	protected function setType($path) {
		if (preg_match('/\.(js|css)[\&$]?/i', $path, $matches)) {
			$this->_type = strtolower($matches[1]);
		}
		return $this;
	}
	
	/**
	 * Get Cache Key
	 *	Return the cache key
	 *
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getCacheKey() {
		if (empty($this->_cacheKey)) {
			throw new ComboLoader_Exception_Cache('Unable to get CacheKey, CacheKey empty', 500);
		}
		return $this->_cacheKey;
	}
	
	/**
	 * Set Cache Key
	 *	Save the cache key
	 *
	 * @param string $cacheKey 
	 * @return $this
	 * @author Mikael Abrahamsson
	 */
	protected function setCacheKey($cacheKey) {
		if (empty($cacheKey)) {
			throw new ComboLoader_Exception_Cache('Unable to set CacheKey, CacheKey empty', 500);
		}
		$this->_requestParams = $cacheKey;
		$this->_cacheKey = sha1($cacheKey);
		return $this;
	}
	
	/**
	 * Get Filename
	 *
	 * @param string $type OPTIONAL 
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getFilename($type = null) {
		if (empty($this->_filename)) {
			throw new ComboLoader_Exception_Filename('Unable to get Filename, Filename empty', 500);
		}
		
		$filename = $this->_filename;
		
		if (empty($type)) {
			$type = $this->getOption('default_filename_type');
		}
		
		if (!is_string($type)) {
			throw new ComboLoader_Exception_Filename('Invalid filename type, expected string, got "' . gettype($type) . '"', 500);
		}
		
		$type = strtolower($type);
		
		if ($type === 'raw') {
			return $filename .= '.' . $this->getType();
		} elseif ($type === 'min') {
			return $filename .= '-min.' . $this->getType();
		}
		
		throw new ComboLoader_Exception_Filename('Invalid filename type ("' . gettype($type) . '"). Valid values are "raw" or "min".', 500);
	}
	
	/**
	 * Set Filename
	 *
	 * @param string $filename 
	 * @return $this
	 * @author Mikael Abrahamsson
	 */
	protected function setFilename($filename) {
		if (empty($filename)) {
			throw new ComboLoader_Exception_Filename('Unable to set Filename, Filename empty', 500);
		}
		$this->_filename = $filename;
		return $this;
	}
			
	/**
	 * Set Content
	 *
	 * @param boolean $file 
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getContent($type = false) {
		if ($this->_content === null) {
			throw new ComboLoader_Exception_Content('Content is NULL', 404);
		}
		
		if ($type) {
			if ($this->getOption('use_cache')) {
				return $this->getFilename($type);
			}
			return '/tmp/' . basename($this->getFilename($type));
		}
		
		return $this->_content;
	}
	
	/**
	 * Set Content Raw
	 *
	 * @param string $output 
	 * @param string $type (raw|min) 
	 * @return $this
	 * @author Mikael Abrahamsson
	 */
	protected function setContent($content, $type) {
		
		if (!is_string($content)) {
			throw new ComboLoader_Exception_Content('Unable to set Content, expected type string got "' . gettype($content) . '"!', 500);
		}
		
		$this->_content = $content;
		
		if ($this->getOption('use_cache')) {
			if (is_writable($this->getOption('cache_path'))) {
				file_put_contents($this->getFilename($type), $this->_content);
			} else {
				throw new ComboLoader_Exception_Content('Unable to setContent, "' . $this->getOption('cache_path') . '" directory is not writable!', 500);
			}
		} else {
			file_put_contents('/tmp/' . basename($this->getFilename($type)), $this->_content);
		}
		
		return $this;
	}
		
	/**
	 * Get Content Type
	 *
	 * @return string
	 * @author Mikael Abrahamsson
	 */
	protected function getContentType() {
		return $this->getOption($this->getType() . '_content_type');
	}
	
	/**
	 * Get Cache
	 *
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	protected function getCache() {
		$this->setFilename($this->getOption('cache_path') . DIRECTORY_SEPARATOR . $this->getCacheKey());
		if ($this->getOption('use_cache') && file_exists($this->getFilename())) {
			$this->setContent(file_get_contents($this->getFilename()), 'min');
			return true;
		}
		return false;
	}
	
	/**
	 * Parse Request
	 *
	 * @param string $requestParams 
	 * @return array
	 * @author Mikael Abrahamsson
	 */
	protected function parseRequest($requestParams) {
		
		// Params
		$params = preg_replace('/^[^\?]+[\?]/', '', $requestParams);
				
		// Get Version
		$version = substr($requestParams, 0, - (strlen($params) + 1));
		if (preg_match('/(' . $this->getOption('version_pattern') . ')/', $version, $matches)) {
			$this->setVersion($matches[1]);
		}
		
		$params = preg_split('/[\&]/', $params);
		$files = array();
		foreach ($params as $param) {
			$file = $this->normalize($param);
			$files = array_merge($files, $file);
		}
				
		return $files;
	}
	
	/**
	 * Send Request
	 *
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	protected function sendResponse() {
		
		$content		= $this->getContent();
		$contentType 	= $this->getContentType();
		$contentLength 	= mb_strlen($content, $this->getOption('charset'));
		
		header('Content-Type: ' . $contentType);
		header('Content-Length: ' . $contentLength);
		
		// Debug header
		if ($this->getOption('debug_header') && !$this->getOption('use_cache')) {
			$assetsDir = $this->getOption('assets_path');
			
			$files = preg_filter('/^[\\' . DIRECTORY_SEPARATOR . ']?' . $assetsDir . '[\\' . DIRECTORY_SEPARATOR . ']?/', '', $this->_files);
						
			$rawContentLength = mb_strlen(file_get_contents($this->getContent('raw')), $this->getOption('charset'));
			
			$minifier = $this->getOption('js_minifier');
			
			$header = array('/**');
			$header[] = 'Params:         ' . $this->_requestParams;
			$header[] = 'Minifier:       ' . $minifier;
			
			if ($minifier === MINIFIER_CLOSURE) {
				$closureParams = $this->getOption('closure_params');
				$header[] = 'Closure params: ' . key($closureParams) . ' = ' . current($closureParams);
				array_shift($closureParams);
				foreach($closureParams as $k =>$v) {
					$header[] = '                ' . $k . ' = ' . $v;
				}
			}
			
			$header[] = 'Raw size:       ' . round($rawContentLength / 1024, 1) . ' KB';
			$header[] = 'Minified size:  ' . round($contentLength / 1024, 1) . ' KB';
			$header[] = 'Savings:        ' . round(($contentLength / $rawContentLength) * 100, 3) . '%';
			$header[] = 'Time:           ' . round(microtime(true) - $this->_timer, 3) . ' ms';
			$header[] = 'Files:          ' . array_shift($files);
			
			foreach($files as $file) {
				$header[] = '                ' . $file;
			}
						
			echo implode("\n * ", $header) . "\n */\n";
		}
		
		echo $content;
	}
	
	/**
	 * Get files form supplied directory by supplied type
	 *
	 * @param string $dir 
	 * @param string $type 
	 * @return array
	 * @author Mikael Abrahamsson
	 */
	protected function getFilesInDir($directory, $type) {
		if (is_dir($directory)) {
			$files = preg_grep('/\.' . $type . '$/', scandir($directory));
			return array_values($files);
		}
		
		throw new ComboLoader_Exception_DirectoryFiles('Unable to get file form directory "' . $directory . '"', 404);
	}
	
	protected function getFullLength() {
		if (!$this->_fullLength) {
			
			$this->_fullLength = count($this->getOption('normalize_base'));
			
			$assetsDir = $this->getOption('assets_path');
			
			$typeDir = $this->getOption($this->getType() . '_directory_name');
			
			// Using a assets dir
			if (!empty($assetsDir)) {
				$this->_fullLength++;
			}
			
			// Version dir
			$this->_fullLength++;
			
			// Using a type dir
			if (!empty($typeDir)) {
				$this->_fullLength++;
			}
			
			// File dir
			$this->_fullLength++;
			
			// File
			$this->_fullLength++;
		}
		
		return $this->_fullLength;
	}
	
	/******************************************************************************************************************************************************************/
	
	/**
	 * Normalize path
	 *
	 * @param string $path 
	 * @return array $normalizedPath
	 * @author Mikael Abrahamsson
	 */
	public function normalize($path) {
		
		// Get Version
		$version = $this->getVersion($path);
		
		// Complete path should be this length
		$normalizeFullLength = $this->getFullLength();
		
		// Split out the path
		$paths = preg_split('/[\/]/', trim(preg_replace('/^[\/]+/', '', preg_replace('/[\&\?]?(' . $version . ')?/', '', $path))));
		
		// Shift in the asset path and version path at the beginning
		array_unshift($paths, $version);
		
		$assetsDir = $this->getOption('assets_path');
		if (!empty($assetsDir)) {
			array_unshift($paths, $assetsDir);
		}
		
		// Is full path
		if (count($paths) === $normalizeFullLength) {
			$result = array(implode(DIRECTORY_SEPARATOR, $paths));
		
		// Missing some part in the path
		} else {
			
			// Set missing type directory length
			$missingTypeDirLength = $normalizeFullLength - 1;
			
			// Set load all files length
			$allFilesLength = $normalizeFullLength - 2;
			
			$normalizedPath = array_merge($this->getOption('normalize_base'));
			
			// Shift in the asset path and version path at the beginning
			array_unshift($normalizedPath, $version);
			
			if (!empty($assetsDir)) {
				array_unshift($normalizedPath, $assetsDir);
			}
			
			// Add path values
			foreach ($paths as $i => $value) {
				if (!isset($normalizedPath[$i]) || $normalizedPath[$i] !== $value) {
					$normalizedPath[] = $value;
				}
			}
			
			// Get length
			$length = count($normalizedPath);
			
			// Set index
			$index = $length - 1;
			
			// Get filename
			$filename = $normalizedPath[$index];
			
			// Get type
			$type = $this->getType($filename);
			
			// Missing base
			if ($length === $normalizeFullLength) {
				$result = array(implode(DIRECTORY_SEPARATOR, $normalizedPath));
			
			// Missing Type Dir
			} elseif ($length === $missingTypeDirLength) {
				$normalizedPath[$index] = $this->getOption($type . '_directory_name');
				$normalizedPath[] = $filename;
				
				$result = array(implode(DIRECTORY_SEPARATOR, $normalizedPath));
			
			// All type files
			} elseif ($length === $allFilesLength) {
				// Get dir
				$dir =  preg_replace('/\.' . $type . '$/i', '', implode(DIRECTORY_SEPARATOR, $normalizedPath)) . DIRECTORY_SEPARATOR . $this->getOption($type . '_directory_name');
				
				// Get files for directory
				$files = $this->getFilesInDir($dir, $type);
				
				$result = preg_filter('/^(.*)$/', $dir . DIRECTORY_SEPARATOR . '$1', $files);
			}
		}
		
		if (!isset($result) || empty($result)) {
			throw new ComboLoader_Exception_Normalize('Unable normalize path ("' . $path . '")', 500);
		}
		
		return $result;
	}
	
	/**
	 * Build
	 * 	Concatenate and compress files
	 *
	 * @param array $files 
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function build(array $files) {
		$this->concat($files);
		if ($this->getOption('minify')) {
			$this->minify($this->getContent('raw'));
		}
	}
	
	/**
	 * Concatenate the files
	 *
	 * @param array $files 
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function concat(array $files) {
		// Minify command
		$cmd = 'cat ' . implode(' ', $files);
		
		// Escape Command
		$cmd = escapeshellcmd($cmd);
				
		// Do the thing
		exec($cmd, $output, $code);
						
		// Did it go well?
		if (is_numeric($code) && $code !== 0) {
			throw new ComboLoader_Exception_Build('Concat: Error code "' . $code . '"', 500);
		}
		
		$this->_files = $files;
		
		// Get raw content
		$raw = implode("\n", $output);
		
		// Set Content Raw
		$this->setContent($raw, 'raw');
	}
	
	/**
	 * Minify Javascript and CSS
	 *
	 * @param string $content Path to the file containing the contents to be minified
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function minify($content) {
		
		$minifier 	= $this->getOption('js_minifier');
		$type 		= $this->getType();
		
		// Minify JavaScript with Google Closure
		if ($type === 'js' && $minifier === MINIFIER_CLOSURE) {
			
			// Get closure params
			$params = $this->getOption('closure_params');
			
			// Set code to be compressed
			$params['js_code'] = urlencode(file_get_contents($content));
						
			$postFields = array();
			foreach($params as $key => $value) {
				$postFields[] = $key . '=' . $value;
			}
			
			// Init curl
			$curl = curl_init($this->getOption('closure_path')); 
			
			// Set some curl options
			curl_setopt($curl, CURLOPT_POST, true); 
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); 
			curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $postFields));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
			
			// Get the results
			$minified = curl_exec($curl); 
			
			// Close curl
			curl_close($curl);
						
		// Minify JavaScript and CSS with The YUI Compressor
		} elseif ($this->getType() === 'css' || $minifier === MINIFIER_YUI) {
		
			// Minify command
			$cmd = 'java -jar ' . $this->getOption('yui_compressor_path') . ' --charset ' . $this->getOption('charset') . ' --type ' . $type . ' ' . $content;

			// Escape Command
			$cmd = escapeshellcmd($cmd);
			
			// Do the thing
			exec($cmd, $output, $code);
			
			// Did it go well?
			if (is_numeric($code) && $code !== 0) {
				throw new ComboLoader_Exception_Build('Minify: Error code "' . $code . '"', 500);
			}
			
			$minified = implode("\n", $output);
		
		} else {
			throw new ComboLoader_Exception_Build('Invalid minifier "' . $minifier . '" for type "' . $type . '"', 500);
		}
		
		// Set Content Min
		$this->setContent($minified, 'min');
	}
	
	/**
	 * Handle in coming request
	 *
	 * @param string $requestParams 
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function handle($requestParams) {
		
		$this->startTimer();
		
		$this->setType($requestParams);
		
		$this->setCacheKey($requestParams);
		
		if (!$this->getCache() || self::$dryRun) {
						
			$files = $this->parseRequest($requestParams);
			
			if (self::$dryRun) {
				return $files;
			}
			
			$this->build($files);
		}
		
		$this->sendResponse();
	}
	
	/**
	 * Automaticaly determine the request params
	 *
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function auto() {
		
		$this->startTimer();
		
		if (isset($_SERVER['REQUEST_URI'])) {
			$requestParams = $_SERVER['REQUEST_URI'];
		} elseif (isset($_SERVER['REDIRECT_URL'])) {
		 	if (isset($_SERVER['QUERY_STRING'])) {
				$requestParams = $_SERVER['REDIRECT_URL'] . '?' . $_SERVER['QUERY_STRING'];
			} elseif (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
				$requestParams = $_SERVER['REDIRECT_URL'] . '?' . $_SERVER['REDIRECT_QUERY_STRING'];
			} elseif (isset($_SERVER['argv'])) {
				$requestParams = $_SERVER['REDIRECT_URL'] . '?' . current($_SERVER['argv']);
			}
		}
		
		$localPath = preg_split('/[\\' . DIRECTORY_SEPARATOR . ']/', dirname(__FILE__));
		
		foreach($localPath as $i => $path) {
			$requestParams = preg_replace('/^' . $path . '[\\' . DIRECTORY_SEPARATOR . ']?/', '', $requestParams);
		}
				
		$this->handle($requestParams);
	}
}