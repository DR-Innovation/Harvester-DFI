<?php
/**
 * This harvester connects to the open API of the Danish Film Institute and
 * copies information on movies into a Chaos service.
 * It was build to harvest the DFI metadata into the Chaos deployment used for
 * DKA (danskkulturarv.dk).
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify  
 * it under the terms of the GNU Lesser General Public License as published by  
 * the Free Software Foundation, either version 3 of the License, or  
 * (at your option) any later version.  
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    $Id:$
 * @link       https://github.com/CHAOS-Community/Harvester-DFI
 * @since      File available since Release 0.1
 */

use dfi\dka\DKAMetadataGenerator;

require "bootstrap.php";

use dfi\model\MovieItem;
use dfi\DFIClient;

/**
 * Main class of the DFI Harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-DFI
 * @since      Class available since Release 0.1
 */
class DFIIntoDKAHarvester extends AChaosImporter {
	
	/**
	 * The version information of the harvester.
	 * @var string
	 */
	const VERSION = "0.1";
	
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
	// TODO: Is this correct?
	
	/**
	 * The URL of the DFI service.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_DFIUrl;
	
	/**
	 * The object type of a chaos object, to be used later.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_objectTypeID;
	
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_imageFormatID;
	
	/**
	 * The ID of the format to be used when linking lowres-images to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_lowResImageFormatID;
	
	/**
	 * The ID of the format to be used when linking thumbnail.images to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_thumbnailImageFormatID;
	
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_imageDestinationID;
	
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_videoFormatID;
	
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * Populated when AChaosImporter::loadConfiguration is called.
	 * @var string
	 */
	protected $_videoDestinationID;
	
	/**
	 * The client to use when communicating with the DFI service.
	 * @var dfi\DFIClient
	 */
	protected $_dfi;
	
	/**
	 * Constructor for the DFI Harvester
	 * @throws RuntimeException if the Chaos services are unreachable or
	 * if the Chaos credentials provided fails to authenticate the session.
	 */
	public function __construct($args) {
		// Adding configuration parameters
		$this->_CONFIGURATION_PARAMETERS["DFI_URL"] = "_DFIUrl";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_OBJECT_TYPE_ID"] = "_objectTypeID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_IMAGE_FORMAT_ID"] = "_imageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_LOWRES_IMAGE_FORMAT_ID"] = "_lowResImageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_THUMBNAIL_IMAGE_FORMAT_ID"] = "_thumbnailImageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_VIDEO_FORMAT_ID"] = "_videoFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_IMAGE_DESTINATION_ID"] = "_imageDestinationID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_VIDEO_DESTINATION_ID"] = "_videoDestinationID";
		// Adding xml generators.
		$this->_metadataGenerators[] = new dfi\dka\DKAMetadataGenerator();
		$this->_metadataGenerators[] = new dfi\dka\DKA2MetadataGenerator();
		$this->_metadataGenerators[] = new dfi\dka\DFIMetadataGenerator();
		// Adding file extractors.
		$this->_fileExtractors['image'] = new dfi\DFIImageExtractor();
		$this->_fileExtractors['video'] = new dfi\DFIVideoExtractor();
		
		parent::__construct($args);
		
		$this->DFI_initialize();
	}
	
	/**
	 * This destructs the harvester, this also unsets/destroys the DFI client.
	 * @see AChaosImporter::__destruct()
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->_dfi);
	}
	
	/**
	 * Fetches an external DFI movies as a MovieItem by reference (i.e. the internal DFI id).
	 * @see AChaosImporter::fetchSingle()
	 * @return MovieItem The deserialized movie item, representing the movie.
	 */
	protected function fetchSingle($reference) {
		if(is_numeric($reference)) {
			// This is an integer id.
			$reference = 'http://nationalfilmografien.service.dfi.dk/movie.svc/'.$reference;
		}
		$response = MovieItem::fetch($this->_dfi, $reference);
		$response->registerXPathNamespace("dfi", "http://schemas.datacontract.org/2004/07/Netmester.DFI.RestService.Items");
		$response->registerXPathNamespace("a", "http://schemas.microsoft.com/2003/10/Serialization/Arrays");
		return $response;
	}
	
	/**
	 * This fetches a range of external DFI movies as an array of references for movies, 
	 * @see AChaosImporter::fetchRange()
	 */
	protected function fetchRange($start, $count) {
		$response = $this->_dfi->fetchMultipleMovies($start, $count, 1000);
		$result = array();
		foreach($response as $movieItem) {
			$result[] = strval($movieItem->Ref);
		}
		return $result;
	}
	
	protected function initializeExtras($externalObject, &$extras) {
		$extras['processedFiles'] = array();
		$extras['fileTypes'] = array();
	}
	
	protected function externalObjectToString($externalObject) {
		$title = "Unknown";
		if($externalObject != null) {
			if(strlen(trim($externalObject->Title)) > 0) {
				$title = trim($externalObject->Title);
			} elseif(strlen(trim($externalObject->OriginalTitle))) {
				$title = trim($externalObject->OriginalTitle);
			}
		}
		$id = $externalObject != null ? $externalObject->ID : 0;
		return sprintf("%s [%u]", $title, $id);
	}
	
	public function getExternalClient() {
		return $this->_dfi;
	}
	
	protected function generateChaosQuery($externalObject) {
		if($externalObject == null) {
			throw new RuntimeException("Cannot get or create a Chaos object from a null external object.");
		}
		$DFIId = strval($externalObject->ID);
		if(!is_numeric($DFIId)) {
			throw new RuntimeException("Cannot get or create a Chaos object from an external object with a non-nummeric ID.");
		} else {
			$DFIId = intval($DFIId);
		}
		
		$folderId = $this->_ChaosFolderID;
		$objectTypeId = $this->_objectTypeID;
		// Query for a Chaos Object that represents the DFI movie.
		return "(FolderTree:$folderId AND ObjectTypeID:$objectTypeId AND DKA-DFI-ID:$DFIId)";
	}
	
	protected function getChaosObjectTypeID() {
		return $this->_objectTypeID;
	}
	
	// Helpers
	
	/**
	 * Checks if this movie should be excluded from the harvest, because of censorship.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @return bool True if this movie should be excluded, false otherwise.
	 */
	public function shouldBeSkipped($movieItem) {
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			if($subCategory == 'Pornofilm' || $subCategory == 'Erotiske film') {
				return "The subcategory is $subCategory.";
			}
		}
		$year = null;
		if(strlen($movieItem->ProductionYear) > 0) {
			$year = intval($movieItem->ProductionYear);
		} elseif(strlen($movieItem->ReleaseYear) > 0) {
			$year = intval($movieItem->ReleaseYear);
		}
		if($year < 100) {
			$year += 1900;
		}
		// Harvest only files in this interval: 1896-1959
		if($year != null && ($year < 1896 || $year > 1959)) {
			return "The movie was produced or released at year $year which is outside of the desired interval.";
		}
		
		$mediaFiles = 0;
		// Check that it has images attached.
		$imagesURL = strval($movieItem->Images);
		if(!empty($imagesURL)) {
			$images = $this->_dfi->load($imagesURL);
			$mediaFiles += $images->count();
		}
		// Movies
		$mediaFiles += $movieItem->FlashMovies->FlashMovieItem->count();
		$mediaFiles += $movieItem->MainImage->SrcThumb->count();
		if($mediaFiles < 1) {
			return "The movie has no medias attached.";
		}
		
		return false;
	}
	
	// DFI specific methods.
	
	/**
	 * Initialized the DFI client by making a simple test to see if the service is advailable.
	 * @throws RuntimeException If the service is unadvailable.
	 */
	protected function DFI_initialize() {
		printf("Looking up the DFI service %s: ", $this->_DFIUrl);
		$this->_dfi = new DFIClient($this->_DFIUrl);
		if(!$this->_dfi->isServiceAdvailable()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't connect to the DFI service.");
		} else {
			printf("Succeeded.\n");
		}
		
		$this->_fileExtractors['image']->_imageDestinationID = $this->_imageDestinationID;
		$this->_fileExtractors['image']->_imageFormatID = $this->_imageFormatID;
		$this->_fileExtractors['image']->_lowResImageFormatID = $this->_lowResImageFormatID;
		$this->_fileExtractors['image']->_thumbnailImageFormatID = $this->_thumbnailImageFormatID;
		$this->_fileExtractors['video']->_videoDestinationID = $this->_videoDestinationID;
		$this->_fileExtractors['video']->_videoFormatID = $this->_videoFormatID;
	}
}

// Call the main method of the class.
DFIIntoDKAHarvester::main($_SERVER['argv']);