<?php
namespace CHAOS\Harvester\DFI;
class BasicObjectProcessor extends \CHAOS\Harvester\ObjectProcessor implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function process($externalObject) {
		$this->_harvester->debug(__CLASS__." is processing.");
		$this->_harvester->info("\tProcessing '%s' #%d", $externalObject->Title, $externalObject->ID);
		//var_dump($externalObject);
	}
}