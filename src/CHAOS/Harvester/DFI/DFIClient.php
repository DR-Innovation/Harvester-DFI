<?php
namespace CHAOS\Harvester\DFI;
class DFIClient extends \dfi\DFIClient implements \CHAOS\Harvester\IExternalClient {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $harvester;
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	protected $_parameters;
	
	public function setParameters($parameters) {
		$this->_harvester->debug("A ".__CLASS__." got new parameters.");
		$this->_parameters = $parameters;
		if(key_exists('URL', $this->_parameters)) {
			$this->_baseURL = $this->_parameters['URL'];
		}
	}
	
	public function sanityCheck() {
		$result = parent::isServiceAdvailable();
		if($result === true) {
			$this->_harvester->info("%s successfully responded.", $this->_baseURL);
		}
		return $result;
	}
	
	public function load($url, $class_name = null) {
		timed();
		$result = parent::load($url, $class_name);
		timed('dfi');
		return $result;
	}
}