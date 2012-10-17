<?php
namespace CHAOS\Harvester\DFI\Filters;
class BasicMovieFilter extends \CHAOS\Harvester\Filters\Filter implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function passes($externalObject) {
		$this->_harvester->debug(__CLASS__." is processing.");
		return true;
	}
}