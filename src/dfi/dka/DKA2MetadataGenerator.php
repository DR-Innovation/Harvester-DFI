<?php
namespace dfi\dka;
use SimpleXMLElement;
class DKA2MetadataGenerator extends DKAMetadataGenerator {
	const SCHEMA_NAME = 'DKA2';
	const SCHEMA_GUID = '5906a41b-feae-48db-bfb7-714b3e105396';
	const VALIDATE = true;
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 */
	public function fetchSchema($chaosClient) {
		return $this->fetchSchemaFromGUID($chaosClient, self::SCHEMA_GUID);
	}
	
	/**
	 * Generate XML from some import-specific object.
	 * @param unknown_type $externalObject
	 * @param boolean $validate Validate the generated XML agains a schema.
	 * @return DOMDocument Representing the imported item as XML in a specific schema.
	 */
	public function generateXML($externalObject, &$extras) {
		$movieItem = $externalObject;
		$fileTypes = self::extractFileTypes($extras['extractedFiles']);
		$result = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DKA></DKA>");

		$result->addChild("Title", htmlspecialchars($movieItem->Title));
		
		// TODO: Consider if this is the correct mapping.
		$result->addChild("Abstract", '');
		
		$decription = htmlspecialchars($movieItem->Description);
		if(strlen($movieItem->Comment) > 0) {
			$decription .= "\n\n".htmlspecialchars($movieItem->Comment);
		}
		$result->addChild("Description", $decription);
		
		$result->addChild("Organization", \DFIIntoDKAHarvester::DFI_ORGANIZATION_NAME);
		
		if(strlen($movieItem->Url) > 0) {
			$result->addChild("ExternalURL", htmlspecialchars($movieItem->Url));
		}
		
		$result->addChild("Type", implode(',', $fileTypes));
		
		// TODO: Determine if this is when the import happened or when the movie was created?
		if(strlen($movieItem->ProductionYear) > 0) {
			$result->addChild("CreatedDate", self::yearToXMLDateTime((string)$movieItem->ProductionYear));
		}
		
		if(strlen($movieItem->ReleaseYear) > 0) {
			$result->addChild("FirstPublishedDate", self::yearToXMLDateTime((string)$movieItem->ReleaseYear));
		}
		
		$contributors = $result->addChild("Contributors");
		foreach($movieItem->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$contributor = $contributors->addChild("Contributor");
				$contributor->addAttribute("Name", trim(htmlspecialchars($creditListItem->Name)));
				$contributor->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$creators = $result->addChild("Creators");
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:Credits/dfi:CreditListItem') as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$creator = $creators->addChild("Creator");
				$creator->addAttribute("Name", trim(htmlspecialchars($creditListItem->Name)));
				$creator->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		// This goes for the new DKA Metadata.
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:ProductionCompanies/dfi:CompanyListItem') as $company) {
			$creator = $creators->addChild("Creator");
			$creator->addAttribute("Name", trim(htmlspecialchars($company->Name)));
			$creator->addAttribute("Role", 'Production');
		}
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:DistributionCompanies/dfi:CompanyListItem') as $company) {
			$creator = $creators->addChild("Creator");
			$creator->addAttribute("Name", trim(htmlspecialchars($company->Name)));
			$creator->addAttribute("Role", 'Distribution');
		}
		
		$format = trim(htmlspecialchars($movieItem->Format));
		if($format !== '') {
			$result->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result->addChild("Location", htmlspecialchars($movieItem->CountryOfOrigin));
		
		$result->addChild("RightsDescription", \DFIIntoDKAHarvester::RIGHTS_DESCIPTION);
		
		$Categories = $result->addChild("Categories");
		$Categories->addChild("Category", htmlspecialchars($movieItem->Category));
		
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", $subCategory);
		}
		
		$Tags = $result->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		// Generate the DOMDocument.
		$dom = dom_import_simplexml($result)->ownerDocument;
		$dom->formatOutput = true;
		if(self::VALIDATE) {
			$this->validate($dom);
		}
		return $dom;
	}
}