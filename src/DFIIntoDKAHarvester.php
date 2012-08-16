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
		$this->_fileExtractors[] = new dfi\DFIImageExtractor();
		$this->_fileExtractors[] = new dfi\DFIVideoExtractor();
		
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
		$response->registerXPathNamespace('dfi', 'http://schemas.http://api.test.chaos-systems.com/Object/Gdatacontract.org/2004/07/Netmester.DFI.RestService.Items');
		$response->registerXPathNamespace('a', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');
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
		$title = $externalObject != null ? $externalObject->Title : "Unknown";
		$id = $externalObject != null ? $externalObject->ID : 0;
		return sprintf("%s [%u]", $title, $id);
	}
	
	public function getExternalClient() {
		return $this->_dfi;
	}
	
	/**
	 * Fetch and process all advailable DFI movies.
	 * This method calls fetchAllMovies on the 
	 * @param null|string $publishAccessPointGUID The AccessPointGUID to use when publishing right now.
	 * @param boolean $skipProcessing Just skip the processing of the movie, used if one only wants to publish the movie.
	 */
	/*
	public function processMovies($offset = 0, $count = null, $publish = null, $accessPointGUID = null, $skipProcessing = false) {
		printf("Fetching ids for all movies: ");
		$start = microtime(true);
		
		$movies = $this->_dfi->fetchMultipleMovies($offset, $count, 1000);
		
		$elapsed = (microtime(true) - $start) * 1000.0;
		printf("done .. took %ums\n", round($elapsed));
		
		$failures = array();
		
		$attempts = 0;
		printf("Iterating over every movie.\n");
		for($i = 0; $i < count($movies); $i++) {
			$m = $movies[$i];
			try {
				sprintf("Starting to process '%s' DFI#%u (%u/%u)\n", $m->Name, $m->ID, $i+1, count($movies));
				$start = microtime(true);
				
				$this->processMovie($m->Ref, $publish, $accessPointGUID, $skipProcessing);
				
				$elapsed = (microtime(true) - $start) * 1000.0;
				sprintf("Completed the processing .. took %ums\n", round($elapsed));
			} catch (Exception $e) {
				$attempts++;
				// Initialize Chaos if the session expired.
				if(strstr($e->getMessage(), 'Session has expired') !== false) {
					sprintf("[!] Session expired while processing the a movie: Creating a new session and trying the movie again.\n");
					// Reauthenticate!
					$this->ChaosInitialize();
				} else {
					sprintf("[!] An error occured: %s.\n", $e->getMessage());
				}
				
				if($attempts > 2) {
					$failures[] = array("movie" => $m, "exception" => $e);
					// Reset
					$attempts = 0;
				} else {http://api.test.chaos-systems.com/Object/G
					// Retry
					$i--;
				}
				continue;
			}
		}
		if(count($failures) == 0) {
			printf("Done .. no failures occurred.\n");
		} else {
			printf("Done .. %u failures occurred:\n", count($failures));
			foreach ($failures as $failure) {
				printf("\t\"%s\" (%u): %s\n", $failure["movie"]->Name, $failure["movie"]->ID, $failure["exception"]->getMessage());
			}
		}
	}
	*/
	
	/**
	 * Fetch and process a single DFI movie.
	 * @param string $reference the URL address referencing the movie through the DFI service.
	 * @param null|string $publishAccessPointGUID The AccessPointGUID to use when publishing right now.
	 * @param boolean $skipProcessing Just skip the processing of the movie, used if one only wants to publish the movie.
	 * @throws RuntimeException If it fails to set the metadata on a chaos object,
	 * this will most likely happen if the service is broken, or in lack of permissions.
	 */
	/*
	public function processMovie($reference, $publish = null, $accessPointGUID = null, $skipProcessing = false) {
		$movieItem = MovieItem::fetch($this->_dfi, $reference);
		if($movieItem === false) {
			throw new RuntimeException("The reference ($reference) does not point to valid XML.\n");
		}

		$shouldBeCensored = self::shouldBeCensored($movieItem);
		if($shouldBeCensored !== false) {
			printf("\tSkipping this movie, as it contains material that should be censored: '%s'\n", $shouldBeCensored);
			return;
		}
		
		// Check to see if this movie is known to Chaos.
		//$chaosObjects = $this->_chaos->Object()->GetByFolderID($this->_DFIFolder->ID, true, null, 0, 10);
		$object = $this->getOrCreateObject($movieItem->ID);
		
		if(!$skipProcessing) {
			// We create a list of files that have been processed and reused.
			$object->ProcessedFiles = array();
			
			$imagesProcessed = dfi\DFIImageExtractor::instance()->process($this->_chaos, $object, $this->_dfi, $movieItem);
			$videosProcessed = dfi\DFIVideoExtractor::instance()->process($this->_chaos, $object, $this->_dfi, $movieItem);
			
			$types = array();
			if(count($imagesProcessed) > 0) {
				$types[] = "Picture";
			}
			if(count($videosProcessed) > 0) {
				$types[] = "Video";
			}
			
			// Do we have any files on the object which has not been processed by the search?
			foreach($object->Files as $f) {
				if(!in_array($f, $object->ProcessedFiles)) {
					printf("\t[!] The file '%s' (%s) was a file of the object, but not processed, maybe it was deleted from the DFI service.\n", $f->Filename, $f->ID);
				}
			}
			
			$xml = $this->generateXML($movieItem, $types);
			
			$revisions = self::extractMetadataRevisions($object);
			
			foreach($xml as $schemaGUID => $metadata) {
				// This is not implemented.
				// $currentMetadata = $this->_chaos->Metadata()->Get($object->GUID, $schema->GUID, 'da');
				//var_dump($currentMetadata);
				$revision = array_key_exists($schemaGUID, $revisions) ? $revisions[$schemaGUID] : null;
				printf("\tSetting '%s' metadata on the Chaos object (overwriting revision %u): ", $schemaGUID, $revision);
				timed();
				$response = $this->_chaos->Metadata()->Set($object->GUID, $schemaGUID, 'da', $revision, $xml[$schemaGUID]->saveXML());
				timed('chaos');
				if(!$response->WasSuccess()) {
					printf("Failed.\n");
					throw new RuntimeException("Couldn't set the metadata on the Chaos object.");
				} else {
					printf("Succeeded.\n");
				}
			}
		}
		
		if($publish === true || $publish === false) {
			$start = null;
			if($publish === true) {
				$start = new DateTime();
				printf("\tChanging the publish settings for %s to startDate = %s: ", $accessPointGUID, $start->format("Y-m-d H:i:s"));
			} elseif($publish === false) {
				printf("\tChanging the publish settings for %s to unpublished: ", $accessPointGUID);
			}
			timed();
			$response = $this->_chaos->Object()->SetPublishSettings($object->GUID, $accessPointGUID, $start);
			timed('chaos');
			if(!$response->WasSuccess() || !$response->MCM()->WasSuccess()) {
				printf("Failed.\n");
				throw new RuntimeException("Couldn't set the publish settings on the Chaos object.");
			} else {
				printf("Succeeded.\n");
			}
		}
	}
	*/
	
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
	
	/**
	 * Gets or creates an object in the Chaos service, which represents a
	 * particular DFI movie.
	 * @param int $DFIId The internal id of the movie in the DFI service.
	 * @throws RuntimeException If the request or creation of the object fails.
	 * @return stdClass Representing the Chaos existing or newly created DKA program -object.
	 */
	/*
	protected function getOrCreateObject($externalObject) {
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
		$query = "(FolderTree:$folderId AND ObjectTypeID:$objectTypeId AND DKA-DFI-ID:$DFIId)";
		//printf("Solr query: %s\n", $query);
		//$response = $this->_chaos->Object()->Get($query, "DateCreated+desc", null, 0, 100, true, true);
		
		return $results[0];
	}
	*/
	
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
		
		dfi\DFIImageExtractor::instance()->_imageDestinationID = $this->_imageDestinationID;
		dfi\DFIImageExtractor::instance()->_imageFormatID = $this->_imageFormatID;
		dfi\DFIImageExtractor::instance()->_lowResImageFormatID = $this->_lowResImageFormatID;
		dfi\DFIImageExtractor::instance()->_thumbnailImageFormatID = $this->_thumbnailImageFormatID;
		dfi\DFIVideoExtractor::instance()->_videoDestinationID = $this->_videoDestinationID;
		dfi\DFIVideoExtractor::instance()->_videoFormatID = $this->_videoFormatID;
	}
}

// Call the main method of the class.
DFIIntoDKAHarvester::main($_SERVER['argv']);