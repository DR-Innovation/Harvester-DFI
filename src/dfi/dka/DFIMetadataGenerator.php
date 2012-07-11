<?php
namespace dfi\dka;

use SimpleXMLElement;

class DFIMetadataGenerator extends \ACHAOSMetadataGenerator {
	const SCHEMA_NAME = 'DKA.DFI';
	const SCHEMA_GUID = 'd361328e-4fd2-4cb1-a2b4-37ecc7679a6e';
	const VALIDATE = true;
	
	public static $singleton;
	
	/**
	 * Generate XML from some import-specific object.
	 * @param unknown_type $externalObject
	 * @param boolean $validate Validate the generated XML agains a schema.
	 * @return DOMDocument Representing the imported item as XML in a specific schema.
	 */
	public function generateXML($externalObject, &$extras) {
		$movieItem = $externalObject;
		$result = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DFI xmlns:dfi='http://www.example.org/DKA.DFI'></DFI>");
		
		$result->addChild("ID", intval($movieItem->ID));
		
		// Generate the DOMDocument.
		$dom = dom_import_simplexml($result)->ownerDocument;
		$dom->formatOutput = true;
		if(self::VALIDATE) {
			$this->validate($dom);
		}
		return $dom;
	}
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 */
	public function fetchSchema($chaosClient) {
		return $this->fetchSchemaFromGUID($chaosClient, self::SCHEMA_GUID);
	}
}