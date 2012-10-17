<?php
namespace CHAOS\Harvester\DFI\Processors;
use \SimpleXMLElement;

class DFIMovieMetadataProcessor extends \CHAOS\Harvester\Processors\MetadataProcessor implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
		$this->setParameters($parameters);
	}
	
	public function generateMetadata($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		
		$result = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DFI xmlns:dfi='http://www.example.org/DKA.DFI'></DFI>");
		$result->addChild("ID", intval($externalObject->ID));
		return $result;
	}
}