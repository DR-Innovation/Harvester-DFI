<?php
namespace CHAOS\Harvester\DFI\Processors;
use \SimpleXMLElement;

class DKAMovieMetadataProcessor extends \CHAOS\Harvester\Processors\MetadataProcessor implements \CHAOS\Harvester\Loadable {
	
	/**
	 * This name will be used as the organisation when generating XML.
	 * @var string
	 */
	const DFI_ORGANIZATION_NAME = "Det Danske Filminstitut";
	
	/**
	 * This string will be used as RightsDescription when generating XML.
	 * @var string
	 */
	const RIGHTS_DESCIPTION = "Copyright © Det Danske Filminstitut";
	
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
		$this->setParameters($parameters);
	}
	
	public function generateMetadata($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		
		// TODO: Generate this from the shadow.
		//$fileTypes = self::extractFileTypes($extras['extractedFiles']);
		$fileTypes = array();
		$result = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><DKA xmlns="http://www.danskkulturarv.dk/DKA.xsd"></DKA>');
		
		$title = "?";
		if(strlen($externalObject->Title) > 0) {
			$title = htmlspecialchars($externalObject->Title);
		} elseif(strlen($externalObject->OriginalTitle)) {
			$title = htmlspecialchars($externalObject->OriginalTitle);
		}
		$result->addChild("Title", trim($title));
		
		// TODO: Consider if this is the correct mapping.
		$result->addChild("Abstract", '');
		
		$decription = htmlspecialchars($externalObject->Description);
		if(strlen($externalObject->Comment) > 0) {
			$decription .= "\n\n".htmlspecialchars($externalObject->Comment);
		}
		$result->addChild("Description", $decription);
		
		$result->addChild("Organization", self::DFI_ORGANIZATION_NAME);
		
		$result->addChild("Type", $shadow->extras["fileTypes"]);
		
		if(strlen($externalObject->ProductionYear) > 0) {
			$result->addChild("CreatedDate", self::yearToXMLDate((string)$externalObject->ProductionYear));
		}
		
		if(strlen($externalObject->ReleaseYear) > 0) {
			$result->addChild("FirstPublishedDate", self::yearToXMLDate((string)$externalObject->ReleaseYear));
		}
		
		// This can infact be the DFI ID, but it is not used on the DKA frontend.
		$result->addChild("Identifier", intval($externalObject->ID));
		
		$contributors = $result->addChild("Contributor");
		foreach($externalObject->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$person = $contributors->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$creators = $result->addChild("Creator");
		foreach($externalObject->Credits->children() as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$person = $creators->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$format = trim(htmlspecialchars($externalObject->Format));
		if($format !== '') {
			$result->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result->addChild("Location", htmlspecialchars($externalObject->CountryOfOrigin));
		
		$result->addChild("RightsDescription", self::RIGHTS_DESCIPTION);
		
		/* // Needs to be here if the validation should succeed.
		 $GeoData = $result->addChild("GeoData");
		$GeoData->addChild("Latitude", "0.0");
		$GeoData->addChild("Longitude", "0.0");
		*/
		
		$Categories = $result->addChild("Categories");
		$Categories->addChild("Category", htmlspecialchars($externalObject->Category));
		
		foreach($externalObject->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", htmlspecialchars($subCategory));
		}
		
		$Tags = $result->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		return $result;
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
			throw new InvalidArgumentException('The \$year argument must be null, empty or of length 4, got "'.strval($year).'"');
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
			throw new InvalidArgumentException('The \$year argument must be null, empty or of length 4, got "'.strval($year).'"');
		}
	}
}