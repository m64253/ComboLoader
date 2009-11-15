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

if (!function_exists('prePrint')) { function prePrint($data, $title = null, $return = false) { $out = array(); if (!empty($title)) { $out[] = '<h3>' . $title . '</h3>'; } $out[] = '<pre>'; $out[] = print_r($data, true); $out[] = '</pre>'. "\n"; if ($return === true) { return implode("\n", $out); } else { echo implode("\n", $out); } } }

set_exception_handler('ComboLoader::exceptionHandler');

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
		'normalize_base'		=> array('components'),
		'normalize_full_length'	=> 4,
		'js_directory_name'		=> 'scripts',
		'css_directory_name'	=> 'style',
		'assets_path' 			=> 'assets',
		'cache_path' 			=> 'cache',
		'yui_compressor_path'	=> false,
		'version_pattern' 		=> '[0-9]{2}w[0-9]{2}',
		'js_content_type'		=> 'application/x-javascript',
		'css_content_type'		=> 'text/css',
		'self_path'				=> 'combo',
		'self_file'				=> 'Combo.php',
		'use_cache'				=> false,
		'default_filename_type' => 'min',
		'charset'				=> 'UTF-8',
		'minify'				=> true,
		'js_minifier'			=> 'closure',
		'closure_params'		=> array('output_format' => 'text', 'output_info' => 'compiled_code', 'compilation_level' => 'ADVANCED_OPTIMIZATIONS'),
		'closure_path'			=> 'closure-compiler.appspot.com/compile'
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
	 * undocumented variable
	 *
	 * @var string
	 */
	protected $_filename = null;
	
	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	protected $_content;
	
	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	protected $_requestParams;
	
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
	
	/**
	 * Checks if a given option name exists and is of the corrent type
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
		echo $content;
		
		//echo "Request Sent! \n" . $contentType . "\n$contentLength\n\n";
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
	
	/******************************************************************************************************************************************************************/
	
	/**
	 * Normalize path
	 *
	 * @param string $path 
	 * @return array $normalizedPath
	 * @author Mikael Abrahamsson
	 */
	public function normalize($path) {
		
		$version				= $this->getVersion($path);
		$normalizedPath 		= $this->getOption('normalize_base');
		$normalizeFullLength	= $this->getOption('normalize_full_length');
		$missingTypeDirLength 	= $normalizeFullLength - 1;
		$allFilesLength 		= $normalizeFullLength - 2;
		
		$paths = preg_split('/[\/]/', trim(preg_replace('/^[\/]+/', '', preg_replace('/[\&\?]?(' . $version . ')?/', '', $path))));
		
		// Is full path		
		if (count($paths) === $normalizeFullLength) {
			$result = array(implode(DIRECTORY_SEPARATOR, $paths));
		
		// Missing some part in the path
		} else {
									
			// With type dir
			$allDirFiles = false;
			$missingTypeDir = false;
			
			foreach ($paths as $i => $value) {
				if (!isset($normalizedPath[$i]) || $normalizedPath[$i] !== $value) {
					$normalizedPath[] = $value;
				}
			}
			
			$length 	= count($normalizedPath);
			$index 		= $length - 1;
			$filename 	= $normalizedPath[$index];
			$type 		= $this->getType($filename);
			
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
				
				$dir = 	$this->getOption('assets_path') . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . preg_replace('/\.' . $type . '$/i', '', implode(DIRECTORY_SEPARATOR, $normalizedPath)) . DIRECTORY_SEPARATOR . $this->getOption($type . '_directory_name');
				
				$files = $this->getFilesInDir($dir, $type);
				
				$result = preg_filter('/^(.*)$/', $dir . DIRECTORY_SEPARATOR . '$1', $files);
			}
		}
		
		if (!isset($result) || empty($result)) {
			throw new Combo_Exception_Normalize('Unable normalize path ("' . $path . '")', 500);
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
		
		array_unshift($output, '*/');
		array_unshift($output, 'Request Time: ' . microtime(true));
		array_unshift($output, 'Request Params: ' . $this->_requestParams);
		array_unshift($output, '/*!');
		
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
		
		$minifier 	= strtolower($this->getOption('js_minifier'));
		$type 		= $this->getType();
		
		// Minify JavaScript with Google Closure
		if ($type === 'js' && $minifier === 'closure') {
			
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
		} elseif ($this->getType() === 'css' || $minifier === 'yui') {
		
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
	 * Handle in comming request
	 *
	 * @param string $requestParams 
	 * @return void
	 * @author Mikael Abrahamsson
	 */
	public function handle($requestParams) {
		
		$this->setType($requestParams);
		
		$this->setCacheKey($requestParams);
		
		if (!$this->getCache()) {
						
			$files = $this->parseRequest($requestParams);
			
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
		
		$this->handle($requestParams);
	}
}