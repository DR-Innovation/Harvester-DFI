<?php
namespace CHAOS\Harvester\DFI\Processors;
use \SimpleXMLElement;

class DKA2MovieMetadataProcessor extends DKAMovieMetadataProcessor {
	
	public function generateMetadata($externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		
		$movieItem = $externalObject;
		// TODO: Generate this from the shadow.
		//$fileTypes = self::extractFileTypes($extras['extractedFiles']);
		$fileTypes = array();
		$result = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><DKA xmlns="http://www.danskkulturarv.dk/DKA2.xsd"></DKA>');
		
		$title = "?";
		if(strlen($externalObject->Title) > 0) {
			$title = trim(htmlspecialchars($externalObject->Title));
		} elseif(strlen($externalObject->OriginalTitle)) {
			$title = trim(htmlspecialchars($externalObject->OriginalTitle));
		}
		$result->addChild("Title", $title);
		
		// TODO: Consider if this is the correct mapping.
		$result->addChild("Abstract", '');
		
		$decription = htmlspecialchars($externalObject->Description);
		if(strlen($externalObject->Comment) > 0) {
			$decription .= "\n\n".htmlspecialchars($externalObject->Comment);
		}
		$result->addChild("Description", $decription);
		
		$result->addChild("Organization", self::DFI_ORGANIZATION_NAME);
		
		if(strlen($externalObject->Url) > 0) {
			$result->addChild("ExternalURL", htmlspecialchars($externalObject->Url));
		}
		
		$result->addChild("Type", $shadow->extras["fileTypes"]);
		
		// TODO: Determine if this is when the import happened or when the movie was created?
		if(strlen($externalObject->ProductionYear) > 0) {
			$result->addChild("CreatedDate", self::yearToXMLDateTime((string)$externalObject->ProductionYear));
		}
		
		/*if(strlen($externalObject->ReleaseYear) > 0) {
			$result->addChild("FirstPublishedDate", self::yearToXMLDateTime((string)$externalObject->ReleaseYear));
		}*/
		
		// Get date from 3 objects (ProductionYear, ReleaseYear, PremiereDate)
		$dates = array();
		$dates[] = strval($externalObject->Premiere->PremiereDate);
		$dates[] = strval($externalObject->ProductionYear);
		$dates[] = strval($externalObject->ReleaseYear);
		
		// Finds the best/most precise date (longest)
		$date = '';
		foreach ($dates as $d) {
			if (strlen($d) > strlen($date)) {
				$dateparse = date_parse($d);
				if ($dateparse["error_count"] === 0) {
					$date = $d;
				}
			}
		}

		// Makes sure the date is valid
		$dateparse = date_parse($date);
		if ($dateparse["error_count"] === 0) {
			if (strlen($date) === 4) {
				$date = self::yearToXMLDateTime($date);
			} else {
				$date = new \DateTime($date);
				$date = $date->format('Y-m-d\TH:i:s');
			}

			$result->addChild("FirstPublishedDate", $date);
		}
		
		$contributors = $result->addChild("Contributors");
		foreach($externalObject->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$contributor = $contributors->addChild("Contributor", trim(htmlspecialchars($creditListItem->Name)));
				$contributor->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		
		$creators = $result->addChild("Creators");
		foreach($externalObject->Credits->children() as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$creator = $creators->addChild("Creator", trim(htmlspecialchars($creditListItem->Name)));
				$creator->addAttribute("Role", self::translateCreditTypeToRole(htmlspecialchars($creditListItem->Type)));
			}
		}
		// This goes for the new DKA Metadata.
		foreach($externalObject->xpath('/dfi:MovieItem/dfi:ProductionCompanies/dfi:CompanyListItem') as $company) {
			$creator = $creators->addChild("Creator", trim(htmlspecialchars($company->Name)));
			$creator->addAttribute("Role", 'Production');
		}
		foreach($externalObject->xpath('/dfi:MovieItem/dfi:DistributionCompanies/dfi:CompanyListItem') as $company) {
			$creator = $creators->addChild("Creator", trim(htmlspecialchars($company->Name)));
			$creator->addAttribute("Role", 'Distribution');
		}
		
		$format = trim(htmlspecialchars($externalObject->Format));
		if($format !== '') {
			$result->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result->addChild("Location", htmlspecialchars($externalObject->CountryOfOrigin));
		
		$result->addChild("RightsDescription", self::RIGHTS_DESCIPTION);
		
		$Categories = $result->addChild("Categories");
		$Categories->addChild("Category", htmlspecialchars($externalObject->Category));
		
		foreach($externalObject->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", $subCategory);
		}
		
		$Tags = $result->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		return $result;
	}
}