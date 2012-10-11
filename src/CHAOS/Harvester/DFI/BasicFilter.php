<?php
namespace CHAOS\Harvester\DFI;
class BasicFilter extends \CHAOS\Harvester\Filter implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function passes($externalObject) {
		$this->_harvester->debug(__CLASS__." is processing.");
	}
}