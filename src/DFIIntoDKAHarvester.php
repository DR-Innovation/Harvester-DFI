<?php
/**
 * This harvester connects to the open API of the Danish Film Institute and
 * copies information on movies into a CHAOS service.
 * It was build to harvest the DFI metadata into the CHAOS deployment used for
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

error_reporting(E_ALL);
ini_set('display_errors', '0');
libxml_use_internal_errors(true);

// Bootstrapping CHAOS - begin 
if(!isset($_SERVER['INCLUDE_PATH'])) {
	print("The INCLUDE_PATH env parameter must be set.\n");
	exit(1);
}
set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['INCLUDE_PATH']);
require_once("CaseSensitiveAutoload.php"); // This will be reused by this script.
spl_autoload_extensions(".php");
spl_autoload_register("CaseSensitiveAutoload");
// Bootstrapping CHAOS - end

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
class DFIIntoDKAHarvester extends ADKACHAOSHarvester {
	
	const VERSION = "0.1";
	const DKA_SCHEMA_NAME = "DKA";
	const DKA2_SCHEMA_NAME = "DKA2";
	const DFI_SCHEMA_NAME = "DKA.DFI";
	const DFI_ORGANIZATION_NAME = "Det Danske Filminstitut";
	const RIGHTS_DESCIPTION = "Copyright © Det Danske Filminstitut"; // TODO: Is this correct?
	
	/**
	 * The URL of the DFI service.
	 * @var string
	 */
	protected $_DFIUrl;
	
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_imageFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_lowResImageFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_thumbnailImageFormatID;
	
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * @var string
	 */
	protected $_videoFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_imageDestinationID;
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * @var string
	 */
	protected $_videoDestinationID;
	
	/**
	 * Main method of the harvester, call this once.
	 */
	/*
	function main($args = array()) {
		printf("DFIIntoDKAHarvester %s started %s.\n", DFIIntoDKAHarvester::VERSION, date('D, d M Y H:i:s'));
		
		try {
			// Processing runtime options.
			
			$runtimeOptions = self::extractOptionsFromArguments($args);
			
			if(array_key_exists('publish', $runtimeOptions) && array_key_exists('just-publish', $runtimeOptions)) {
				throw new InvalidArgumentException("Cannot have both publish and just-publish options sat.");
			}
			
			$publish = null;
			$publishAccessPointGUID = null;
			$skipProcessing = null;
			if(array_key_exists('publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['publish'];
				$publish = true;
			}
			if(array_key_exists('just-publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['just-publish'];
				$skipProcessing = true;
				$publish = true;
			}
			if($publish === true && array_key_exists('unpublish', $runtimeOptions)) {
				throw new InvalidArgumentException("Cannot have both publish or just-publish and unpublish options sat.");
			} elseif(array_key_exists('unpublish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['unpublish'];
				$publish = false;
			}

			// Starting on the real job at hand
			$starttime = time();
			$h = new DFIIntoDKAHarvester();
			if(array_key_exists('range', $runtimeOptions)) {
				$rangeParams = explode('-', $runtimeOptions['range']);
				if(count($rangeParams) == 2) {
					$start = intval($rangeParams[0]);
					$end = intval($rangeParams[1]);
					if($end < $start) {
						throw new InvalidArgumentException("Given a range parameter which has end < start.");
					}
					
					$h->processMovies($start, $end-$start+1, $publish, $publishAccessPointGUID, $skipProcessing);
				} else {
					throw new InvalidArgumentException("Given a range parameter was malformed.");
				}
			} elseif(array_key_exists('single-id', $runtimeOptions)) {
				$dfiID = intval($runtimeOptions['single-id']);
				printf("Updating a single DFI record (#%u).\n", $dfiID);
				$h->processMovie('http://nationalfilmografien.service.dfi.dk/movie.svc/'.$dfiID, $publish, $publishAccessPointGUID, $skipProcessing);
				printf("Done.\n", $dfiID);
			} elseif(array_key_exists('all', $runtimeOptions) && $runtimeOptions['all'] == true) {
				$h->processMovies(0, null, 0, $publish, $publishAccessPointGUID, $skipProcessing);
			} else {
				throw new InvalidArgumentException("None of --all, --single or --range was sat.");
			}
		} catch(InvalidArgumentException $e) {
			echo "\n";
			printf("Invalid arguments given: %s\n", $e->getMessage());
			self::printUsage($args);
			exit;
		} catch (RuntimeException $e) {
			echo "\n";
			printf("An unexpected runtime error occured: %s\n", $e->getMessage());
			exit;
		} catch (Exception $e) {
			echo "\n";
			printf("Error occured in the harvester implementation: %s\n", $e);
			exit;
		}
		
		// If the handle to the harvester has not been deallocated already.
		if($h !== null) {
			unset($h);
		}
		
		$elapsed = time() - $starttime;
		timed_print();
		
		printf("DFIIntoDKAHarvester exits normally - ran %u seconds.\n", $elapsed);
	}
	*/
	
	/**
	 * Constructor for the DFI Harvester
	 * @throws RuntimeException if the CHAOS services are unreachable or
	 * if the CHAOS credentials provided fails to authenticate the session.
	 */
	public function __construct($args) {
		// Adding configuration parameters
		$this->_CONFIGURATION_PARAMETERS["DFI_URL"] = "_DFIUrl";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_IMAGE_FORMAT_ID"] = "_imageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_LOWRES_IMAGE_FORMAT_ID"] = "_lowResImageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_THUMBNAIL_IMAGE_FORMAT_ID"] = "_thumbnailImageFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_VIDEO_FORMAT_ID"] = "_videoFormatID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_IMAGE_DESTINATION_ID"] = "_imageDestinationID";
		$this->_CONFIGURATION_PARAMETERS["CHAOS_DFI_VIDEO_DESTINATION_ID"] = "_videoDestinationID";
		// Adding xml generators.
		$this->_metadataGenerators[] = dfi\dka\DKAMetadataGenerator::instance();
		$this->_metadataGenerators[] = dfi\dka\DKA2MetadataGenerator::instance();
		$this->_metadataGenerators[] = dfi\dka\DFIMetadataGenerator::instance();
		// Adding file extractors.
		$this->_fileExtractors[] = dfi\DFIImageExtractor::instance();
		$this->_fileExtractors[] = dfi\DFIVideoExtractor::instance();
		
		parent::__construct($args);
		
		$this->DFI_initialize();
	}
	
	public function __destruct() {
		parent::__destruct();
		unset($this->_dfi);
	}
	
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
	
	protected function fetchRange($start, $count) {
		$response = $this->_dfi->fetchMultipleMovies($start, $count, 1000);
		$result = array();
		foreach($response as $movieItem) {
			$result[] = strval($movieItem->Ref);
		}
		return $result;
	}
	
	protected function initializeExtras(&$extras) {
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
				// Initialize CHAOS if the session expired.
				if(strstr($e->getMessage(), 'Session has expired') !== false) {
					sprintf("[!] Session expired while processing the a movie: Creating a new session and trying the movie again.\n");
					// Reauthenticate!
					$this->CHAOS_initialize();
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
	
	/**
	 * Fetch and process a single DFI movie.
	 * @param string $reference the URL address referencing the movie through the DFI service.
	 * @param null|string $publishAccessPointGUID The AccessPointGUID to use when publishing right now.
	 * @param boolean $skipProcessing Just skip the processing of the movie, used if one only wants to publish the movie.
	 * @throws RuntimeException If it fails to set the metadata on a chaos object,
	 * this will most likely happen if the service is broken, or in lack of permissions.
	 */
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
		
		// Check to see if this movie is known to CHAOS.
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
				printf("\tSetting '%s' metadata on the CHAOS object (overwriting revision %u): ", $schemaGUID, $revision);
				timed();
				$response = $this->_chaos->Metadata()->Set($object->GUID, $schemaGUID, 'da', $revision, $xml[$schemaGUID]->saveXML());
				timed('chaos');
				if(!$response->WasSuccess()) {
					printf("Failed.\n");
					throw new RuntimeException("Couldn't set the metadata on the CHAOS object.");
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
				throw new RuntimeException("Couldn't set the publish settings on the CHAOS object.");
			} else {
				printf("Succeeded.\n");
			}
		}
	}
	
	/**
	 * Gets or creates an object in the CHAOS service, which represents a
	 * particular DFI movie.
	 * @param int $DFIId The internal id of the movie in the DFI service.
	 * @throws RuntimeException If the request or creation of the object fails.
	 * @return stdClass Representing the CHAOS existing or newly created DKA program -object.
	 */
	protected function getOrCreateObject($externalObject) {
		if($externalObject == null) {
			throw new RuntimeException("Cannot get or create a CHAOS object from a null external object.");
		}
		$DFIId = strval($externalObject->ID);
		if(!is_numeric($DFIId)) {
			throw new RuntimeException("Cannot get or create a CHAOS object from an external object with a non-nummeric ID.");
		} else {
			$DFIId = intval($DFIId);
		}
		
		$folderId = $this->_CHAOSFolderID;
		$objectTypeId = $this->_DKAObjectType->ID;
		// Query for a CHAOS Object that represents the DFI movie.
		$query = "(FolderTree:$folderId AND ObjectTypeID:$objectTypeId AND DKA-DFI-ID:$DFIId)";
		//printf("Solr query: %s\n", $query);
		//$response = $this->_chaos->Object()->Get($query, "DateCreated+desc", null, 0, 100, true, true);
		timed();
		$response = $this->_chaos->Object()->Get($query, "DateCreated+desc", null, 0, 100, true, true);
		timed('chaos');
		//$response = $this->_chaos->Object()->Get("(FolderTree:$folderId AND ObjectTypeID:$objectTypeId)", "DateCreated+desc", null, 0, 100, true, true);
		
		if(!$response->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: (Request error) ". $response->Error()->Message());
		} else if(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: (MCM error) ". $response->MCM()->Error()->Message());
		}
		
		$results = $response->MCM()->Results();
		//var_dump($results);
		
		// If it's not there, create it.
		if($response->MCM()->TotalCount() == 0) {
			printf("\tFound a film in the DFI service which is not already represented by a CHAOS object.\n");
			timed();
			$response = $this->_chaos->Object()->Create($this->_DKAObjectType->ID, $this->_CHAOSFolderID);
			timed('chaos');
			if($response == null) {
				throw new RuntimeException("Couldn't create a DKA Object: response object was null.");
			} else if(!$response->WasSuccess()) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->Error()->Message());
			} else if(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->MCM()->Error()->Message());
			} else if ($response->MCM()->TotalCount() != 1) {
				throw new RuntimeException("Couldn't create a DKA Object .. No errors but no object created.");
			}
			$results = $response->MCM()->Results();
		} else {
			printf("\tReusing CHAOS object with GUID = %s.\n", $results[0]->GUID);
		}
		
		return $results[0];
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