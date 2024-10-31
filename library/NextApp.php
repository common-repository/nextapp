<?php
class NextApp
{
	public static $_singleton = null;
	
	public static function singleton()
	{
		if (is_null(self::$_singleton)) {
			self::$_singleton = new self();
		}
		return self::$_singleton;
	}
	
	public function __construct()
	{
		$queryvars = create_function('$qvars', 'return array_merge($qvars, array(\'nextapp\'));');
		add_filter('query_vars', $queryvars);
	}
	
	public function compatible()
	{
		if (!function_exists('get_current_user_id')) {
			function get_current_user_id() {
				$user = wp_get_current_user();
				return isset($user->ID) ? (int)$user->ID : 0;
			}
		}
	}
	
	public function run()
	{
		add_filter('parse_query', array($this, 'dispatch'));
	}
	
	public function dispatch(WP_Query $query)
	{
		$controller = $query->get('nextapp');
		if (empty($controller)) {
			return ;
		}
		
		$controller = ucfirst(strtolower($controller));
		$module = NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $controller . '.php';
		if (file_exists($module)) {
			require $module;
			
			$action = $_GET['action'];
			$action = empty($action) ? 'indexAction' : strtolower($action) . 'Action';
			$controllerClass = 'NextApp_' . $controller;
			
			if (class_exists($controllerClass) && method_exists($controllerClass, $action)) {
				$this->compatible();
				call_user_func_array(array(new $controllerClass($this), $action), array());
				exit;
			}
		}
	}
}


class NextApp_Controller
{
	const ERROR_INVALID_PARAMETERS = 1;
	const ERROR_NO_LOGINED = 2;
	const ERROR_PERMISSION_DENIED = 3;
	
	protected $_params = array();
	
	protected $nextApp = null;
	protected $_xml = null;
	protected $_xmlRoot = null;
	
	public function __construct(NextApp $nextApp)
	{
		$this->_params = $_GET;
		foreach ($_POST as $key => $val) {$this->_params[$key] = trim($val);}
		$this->nextApp = $nextApp;
		$this->init();
	}
	
	protected function init()
	{}
	
	protected function getGet($var, $def)
	{
		return isset($_GET[$var]) ? trim($_GET[$var]) : $def;
	}
	
	protected function getPost($var, $def = '')
	{
		return isset($_POST[$var]) ? trim($_POST[$var]) : $def;
	}
	
	protected function getParam($var, $def = '')
	{
		return isset($this->_params[$var]) ? $this->_params[$var] : $def;
	}
	
	protected function getParams()
	{
		return $this->_params;
	}
	
	protected function getXmlRoot()
	{
		if (is_null($this->_xmlRoot)) {
			is_null($this->_xml) && $this->_xml = $this->factoryXml();
			$this->_xmlRoot = $this->_xml->nextapp;
		}
		return $this->_xmlRoot;
	}
	
	protected function factoryXml($xmlStr = '')
	{
		require_once NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'XML.php';
		$this->_xml = crxml::factory($xmlStr);
		return $this->_xml;
	}
	
	protected function renderXml()
	{
		if (function_exists('ob_end_clean')) {
			ob_end_clean();
		}elseif (function_exists('ob_clean')) {
			ob_clean();
		}
		
		if ($this->_xml) {
			$content = $this->_xml->xml();
			
			if (function_exists('mb_convert_encoding')) {
				$content = mb_convert_encoding($content, 'utf-8', 'auto');
			}
			
			header('Content-Type: text/xml; charset=UTF-8');
			echo $content;
		}else {
			die('xml render error.');
		}
		exit;
	}
	
	protected function getAllByField(Array $data, $field)
	{
		$result = array();
		foreach ($data as $item) {
			if ($item instanceof stdClass) {
				$result[] = $item->$field;
			}elseif (is_array($item)) {
				$result[] = $item[$field];
			}else {
				$result[] = (string)$item;
			}
		}
		return $result;
	}
	
	protected function renderError($code = 0, $message = '')
	{
		$result = $this->getXmlRoot()->result;
		switch($code) {
			case self::ERROR_INVALID_PARAMETERS:
				$result->errorCode = -1;
				empty($message) && $message = 'Invalid parameters.';
				break;
			case self::ERROR_NO_LOGINED:
				$result->errorCode = 0;
				empty($message) && $message = 'Users not logged in.';
				break;
			case self::ERROR_PERMISSION_DENIED:
				$result->errorCode = -2;
				empty($message) && $message = 'The user has no right to operate.';
				break;
			default:
				$result->errorCode = -1;
				empty($message) && $message = 'Unknown error.';
				break;
		}
		include_once NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Translate.php';
		$result->errorMessage = nextapp__($message);
		return $this->renderXml();
	}
	
	protected function renderSuccess($code = 1, $message = '')
	{
		if (!empty($message)) {
			include_once NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Translate.php';
			$message = nextapp__($message);
		}
		
		$result = $this->getXmlRoot()->result;
		$result->errorCode = is_null($code) ? 1 : $code;
		$result->errorMessage = $message;
		return $this->renderXml();
	}
}