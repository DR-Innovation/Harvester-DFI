<?php
class DKAXMLGenerator extends CHAOSXMLGenerator {
	const SCHEMA_NAME = 'DKA';
	const SCHEMA_GUID = '00000000-0000-0000-0000-000063c30000';
	
	public static $singleton;
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 */
	public function fetchSchema($chaosClient) {
		return $this->fetchSchemaFromGUID($chaosClient, self::SCHEMA_GUID);
	}
	
	/**
	 * Generate XML from some import-specific object.
	 * @param unknown_type $object
	 * @param boolean $validate Validate the generated XML agains a schema.
	 * @return DOMDocument Representing the imported item as XML in a specific schema.
	 */
	public function generateXML($input, $validate = false) {
		$movieItem = $input["movieItem"];
		$fileTypes = $input["fileTypes"];
		$result = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DKA></DKA>");
		
		$result->addChild("Title", htmlspecialchars($movieItem->Title));
		
		// TODO: Consider if this is the correct mapping.
		$result->addChild("Abstract", '');
		
		$decription = htmlspecialchars($movieItem->Description);
		if(strlen($movieItem->Comment) > 0) {
			$decription .= "\n\n".htmlspecialchars($movieItem->Comment);
		}
		$result->addChild("Description", $decription);
		
		$result->addChild("Organization", DFIIntoDKAHarvester::DFI_ORGANIZATION_NAME);
		
		// TODO: Look into which types are needed for what.
		$result->addChild("Type", implode(',', $fileTypes));
		
		if(strlen($movieItem->ProductionYear) > 0) {
			$result->addChild("CreatedDate", self::yearToXMLDate((string)$movieItem->ProductionYear));
		}
		
		if(strlen($movieItem->ReleaseYear) > 0) {
			$result->addChild("FirstPublishedDate", self::yearToXMLDate((string)$movieItem->ReleaseYear));
		}
		
		// This can infact be the DFI ID, but it is not used on the DKA frontend.
		$result->addChild("Identifier", intval($movieItem->ID));
		
		$contributors = $result->addChild("Contributor");
		foreach($movieItem->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$person = $contributors->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$creators = $result->addChild("Creator");
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:Credits/dfi:CreditListItem') as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$person = $creators->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$format = trim(htmlspecialchars($movieItem->Format));
		if($format !== '') {
			$result->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result->addChild("Location", htmlspecialchars($movieItem->CountryOfOrigin));
		
		$result->addChild("RightsDescription", DFIIntoDKAHarvester::RIGHTS_DESCIPTION);
		
		/* // Needs to be here if the validation should succeed.
		 $GeoData = $result->addChild("GeoData");
		$GeoData->addChild("Latitude", "0.0");
		$GeoData->addChild("Longitude", "0.0");
		*/
		
		$Categories = $result->addChild("Categories");
		$Categories->addChild("Category", htmlspecialchars($movieItem->Category));
		
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", htmlspecialchars($subCategory));
		}
		
		$Tags = $result->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		// Generate the DOMDocument.
		$dom = dom_import_simplexml($result)->ownerDocument;
		$dom->formatOutput = true;
		if($validate) {
			$this->validate($dom);
		}
		return $dom;
	}
	
	/**
	 * Applies translation of different types of persons.
	 * @param string $type The type to be translated.
	 */
	public static function translateCreditTypeToRole($type) {
		$ROLE_TRANSLATIONS = array(); // Right now no translation is provided.
		if(key_exists($type, $ROLE_TRANSLATIONS)) {
			return $this->_roleTranslations[$type];
		} else {
			return $type;
		}
	}
	
	const CONTRIBUTOR = 0x01;
	const CREATOR = 0x02;
	/**
	 * Devides the types known by DFI into Creator or Contributor known by a DKA Program.
	 * @param string $type The type to be translated.
	 * @return int Either the value of the CONTRIBUTOR or the CREATOR class constants.
	 */
	public static function translateCreditTypeToContributorOrCreator($type) {
		switch ($type) {
			case 'Actor':
				return self::CONTRIBUTOR;
			default:
				return self::CREATOR;
		}
	}
	
	/**
	 * Checks if a type known by DFI is a Creator in the DKA Program notion.
	 * @param string $type
	 * @return boolean True if it should be treated as a creator, false otherwise.
	 */
	public static function isCreator($type) {
		return self::translateCreditTypeToContributorOrCreator($type) == self::CREATOR;
	}
	
	/**
	 * Checks if a type known by DFI is a Contributor in the DKA Program notion.
	 * @param string $type
	 * @return boolean True if it should be treated as a contributor, false otherwise.
	 */
	public static function isContributor($type) {
		return self::translateCreditTypeToContributorOrCreator($type) == self::CONTRIBUTOR;
	}
	
	/**
	 * Transforms a 4-digit year into an XML data YYYY-01-01 format.
	 * @param string $year The 4-digit representation of a year.
	 * @throws InvalidArgumentException If this is not null, an empty string or a 4-digit string.
	 * @return NULL|unknown|string The expected result, null or the empty string if this was the input argument.
	 */
	public static function yearToXMLDate($year) {
		if($year === null) {
			return null;
		} elseif($year === '') {
			return $year;
		} elseif(strlen($year) === 4) {
			return $year . '-01-01';
		} else {
			throw new InvalidArgumentException('The \$year argument must be null, empty or of length 4');
		}
	}
	
	/**
	 * Transforms a 4-digit year into an XML data YYYY-01-01T00:00:00 format.
	 * @param string $year The 4-digit representation of a year.
	 * @throws InvalidArgumentException If this is not null, an empty string or a 4-digit string.
	 * @return NULL|unknown|string The expected result, null or the empty string if this was the input argument.
	 */
	public static function yearToXMLDateTime($year) {
		if($year === null) {
			return null;
		} elseif($year === '') {
			return $year;
		} elseif(strlen($year) === 4) {
			return $year . '-01-01T00:00:00';
		} else {
			throw new InvalidArgumentException('The \$year argument must be null, empty or of length 4');
		}
	}
}