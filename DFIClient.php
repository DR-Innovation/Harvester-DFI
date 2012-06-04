<?php
class DFIClient {
	protected $_baseURL;
	
	const LIST_MOVIES = "movie.svc/list";
	
	public function __construct($baseURL) {
		$this->_baseURL = $baseURL;
	}
	
	/**
	 * Checks if the DFI service is advailable, by sending a single row request for the movie.service.
	 */
	public function isServiceAdvailable() {
		$response = simplexml_load_file($this->_baseURL.self::LIST_MOVIES.'?rows=1');
		return ($response !== false);
	}
	
	public function fetchMovies($offset = 0, $count = 1000) {
		return simplexml_load_file($this->_baseURL.self::LIST_MOVIES."?startrow=$offset&rows=$count");
	}
	
	public function fetchAllMovies($batchSize = 1000, $delay = 0) {
		// FIXME: This should probably not be an array, but some Simple XML collection instead.
		$result = array();
		$offset = 0;
		while(true) {
			$response = $this->fetchMovies($offset, $batchSize);
			if($response->count() !== 0) {
				// TODO: Insert all the elements from the response into the array.
				
				
			} else {
				return $result;
			}
			
			if($delay > 0) {
				usleep($delay);
			}
		}
	}
}