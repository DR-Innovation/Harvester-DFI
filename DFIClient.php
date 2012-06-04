<?php
class DFIClient {
	protected $_baseURL;
	
	const LIST_MOVIES = "movie.svc/list?rows=1";
	
	public function __construct($baseURL) {
		$this->_baseURL = $baseURL;
	}
	
	public function isServiceAdvailable() {
		$response = simplexml_load_file($this->_baseURL.self::LIST_MOVIES);
		return ($response !== false);
	}
	
	
}