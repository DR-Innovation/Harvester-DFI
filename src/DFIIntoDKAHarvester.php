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
ini_set('display_errors', '1');
libxml_use_internal_errors(true);

require_once("timed.php");

// Bootstrapping CHAOS - begin 
if(!isset($_SERVER['CHAOS_CLIENT_SRC'])) {
	die("The CHAOS_CLIENT_SRC env parameter must point to the src directory of a CHAOS PHP Client");
}
if(!isset($_SERVER['HARVESTER_BASE_SRC'])) {
	die("The HARVESTER_BASE_SRC env parameter must point to the src directory of the CHAOS Harvester base.");
}
set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['CHAOS_CLIENT_SRC']);
set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['HARVESTER_BASE_SRC']);
require_once("CaseSensitiveAutoload.php"); // This will be reused by this script.
spl_autoload_extensions(".php");
spl_autoload_register("CaseSensitiveAutoload");
// Bootstrapping CHAOS - end

use CHAOS\Portal\Client\PortalClient;
use dfi\model\MovieItem;
use dfi\DFIClient;
use dfi\dka\DKAXMLGenerator;
use dfi\dka\DKA2XMLGenerator;
use dfi\dka\DFIXMLGenerator;
use dfi\DFIImageExtractor;
use dfi\DFIVideoExtractor;

/**
 * Main class of the DFI Harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-DFI
 * @since      Class available since Release 0.1
 */
class DFIIntoDKAHarvester extends AHarvester {
	
	const VERSION = "0.1";
	const DKA_SCHEMA_NAME = "DKA";
	const DKA2_SCHEMA_NAME = "DKA2";
	const DFI_SCHEMA_NAME = "DKA.DFI";
	
	const DKA_OBJECT_TYPE_NAME = "DKA Program";
	
	const DFI_ORGANIZATION_NAME = "Det Danske Filminstitut";
	const RIGHTS_DESCIPTION = "Copyright © Det Danske Filminstitut"; // TODO: Is this correct?
	
	/**
	 * Main method of the harvester, call this once.
	 */
	function main($args = array()) {
		printf("DFIIntoDKAHarvester %s started %s.\n", DFIIntoDKAHarvester::VERSION, date('D, d M Y H:i:s'));
		
		try {
			$runtimeOptions = self::extractOptionsFromArguments($args);
			
			if(array_key_exists('publish', $runtimeOptions) && array_key_exists('just-publish', $runtimeOptions)) {
				throw new InvalidArgumentException("Cannot have both publish and just-publish options sat.");
			}
			
			$publishAccessPointGUID = null;
			$skipProcessing = null;
			if(array_key_exists('publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['publish'];
			}
			if(array_key_exists('just-publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['just-publish'];
				$skipProcessing = true;
			}
			$delay = array_key_exists('delay', $runtimeOptions) ? intval($runtimeOptions['delay']) : 0;
			
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
					
					$h->processMovies($start, $end-$start+1, $delay, $publishAccessPointGUID, $skipProcessing);
				} else {
					throw new InvalidArgumentException("Given a range parameter was malformed.");
				}
			} elseif(array_key_exists('single-id', $runtimeOptions)) {
				$dfiID = intval($runtimeOptions['single-id']);
				printf("Updating a single DFI record (#%u).\n", $dfiID);
				$h->processMovie('http://nationalfilmografien.service.dfi.dk/movie.svc/'.$dfiID, $publishAccessPointGUID, $skipProcessing);
				printf("Done.\n", $dfiID);
			} elseif(array_key_exists('all', $runtimeOptions) && $runtimeOptions['all'] == true) {
				$h->processMovies(0, null, 0, $publishAccessPointGUID, $skipProcessing);
			} else {
				throw new InvalidArgumentException("None of --all, --single or --range was sat.");
			}
			//
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
		$elapsed = time() - $starttime;
		timed_print();
		
		printf("DFIIntoDKAHarvester exits normally - ran %u seconds.\n", $elapsed);
	}
	
	protected static function printUsage($args) {
		printf("Usage:\n\t%s [--all|--single-id={dfi-id}|--range={start-row}-{end-row}] [--publish={access-point-guid}|--just-publish={access-point-guid}]\n", $args[0]);
	}
	
	/**
	 * The CHAOS Portal client to be used for communication with the CHAOS Service. 
	 * @var PortalClient
	 */
	public $_chaos;
	
	/**
	 * The DFI client to be used for communication with the DFI Service. 
	 * @var DFIClient
	 */
	public $_dfi;
	
	protected $_DKAObjectType;
	
	/**
	 * Constructor for the DFI Harvester
	 * @throws RuntimeException if the CHAOS services are unreachable or
	 * if the CHAOS credentials provided fails to authenticate the session.
	 */
	public function __construct() {
		$this->loadConfiguration();
		
		$this->CHAOS_initialize();
		$this->DFI_initialize();
	}
	
	/**
	 * The URL of the DFI service.
	 * @var string
	 */
	protected $_DFIUrl;
	/**
	 * The generated unique ID of the CHAOS Client.
	 * (can be generated at http://www.guidgenerator.com/)
	 * @var string
	 */
	protected $_CHAOSClientGUID;
	/**
	 * The URL of the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSURL;
	/**
	 * The email to be used to authenticate sessions from the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSEmail;
	/**
	 * The password to be used to authenticate sessions from the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSPassword;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSImageFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSLowResImageFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSThumbnailImageFormatID;
	
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSVideoFormatID;
	/**
	 * The ID of the format to be used when linking images to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSImageDestinationID;
	/**
	 * The ID of the format to be used when linking videos to a DKA Program.
	 * @var string
	 */
	protected $_CHAOSVideoDestinationID;
	
	protected $_CHAOSFolderID;
	
	/**
	 * An associative array describing the configuration parameters for the harvester.
	 * This should ideally not be changed.
	 * @var array[string]string
	 */
	protected $_CONFIGURATION_PARAMETERS = array(
		"DFI_URL" => "_DFIUrl",
		"CHAOS_CLIENT_GUID" => "_CHAOSClientGUID",
		"CHAOS_URL" => "_CHAOSURL",
		"CHAOS_EMAIL" => "_CHAOSEmail",
		"CHAOS_PASSWORD" => "_CHAOSPassword",
		"CHAOS_IMAGE_FORMAT_ID" => "_CHAOSImageFormatID",
		"CHAOS_LOWRES_IMAGE_FORMAT_ID" => "_CHAOSLowResImageFormatID",
		"CHAOS_THUMBNAIL_IMAGE_FORMAT_ID" => "_CHAOSThumbnailImageFormatID",
		"CHAOS_VIDEO_FORMAT_ID" => "_CHAOSVideoFormatID",
		"CHAOS_IMAGE_DESTINATION_ID" => "_CHAOSImageDestinationID",
		"CHAOS_VIDEO_DESTINATION_ID" => "_CHAOSVideoDestinationID",
		"CHAOS_FOLDER_ID" => "_CHAOSFolderID"
	);
	
	/**
	 * Fetch and process all advailable DFI movies.
	 * This method calls fetchAllMovies on the 
	 * @param int $delay A non-negative integer specifing the amount of micro seconds to sleep between each call to the API when fetching movies, use this to do a slow fetch.
	 * @param null|string $publishAccessPointGUID The AccessPointGUID to use when publishing right now.
	 * @param boolean $skipProcessing Just skip the processing of the movie, used if one only wants to publish the movie.
	 */
	public function processMovies($offset = 0, $count = null, $delay = 0, $publishAccessPointGUID = null, $skipProcessing = false) {
		printf("Fetching ids for all movies: ");
		$start = microtime(true);
		
		$movies = $this->_dfi->fetchMultipleMovies($offset, $count, 1000, $delay);
		
		$elapsed = (microtime(true) - $start) * 1000.0;
		printf("done .. took %ums\n", round($elapsed));
		
		$failures = array();
		
		$attempts = 0;
		printf("Iterating over every movie.\n");
		for($i = 0; $i < count($movies); $i++) {
			$m = $movies[$i];
			try {
				printf("Starting to process '%s' DFI#%u (%u/%u)\n", $m->Name, $m->ID, $i+1, count($movies));
				$start = microtime(true);
				
				$this->processMovie($m->Ref, $publishAccessPointGUID, $skipProcessing);
				
				$elapsed = (microtime(true) - $start) * 1000.0;
				printf("Completed the processing .. took %ums\n", round($elapsed));
			} catch (Exception $e) {
				$attempts++;
				// Initialize CHAOS if the session expired.
				if(strstr($e->getMessage(), 'Session has expired') !== false) {
					printf("[!] Session expired while processing the a movie: Creating a new session and trying the movie again.\n");
					// Reauthenticate!
					$this->CHAOS_initialize();
				} else {
					printf("[!] An error occured: %s.\n", $e->getMessage());
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
	public function processMovie($reference, $publishAccessPointGUID = null, $skipProcessing = false) {
		$movieItem = MovieItem::fetch($this->_dfi, $reference);
		if($movieItem === false) {
			throw new RuntimeException("The reference ($reference) does not point to valid XML.\n");
		}
		$movieItem->registerXPathNamespace('dfi', 'http://schemas.http://api.test.chaos-systems.com/Object/Gdatacontract.org/2004/07/Netmester.DFI.RestService.Items');
		$movieItem->registerXPathNamespace('a', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');

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
			
			$imagesProcessed = DFIImageExtractor::instance()->process($this->_chaos, $this->_dfi, $movieItem, $object);
			$videosProcessed = DFIVideoExtractor::instance()->process($this->_chaos, $this->_dfi, $movieItem, $object);
			
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
		
		if($publishAccessPointGUID !== null) {
			$now = new DateTime();
			printf("\tChanging the publish settings to: GUID = %s and startDate = %s: ", $publishAccessPointGUID, $now->format("Y-m-d H:i:s"));
			timed();
			$response = $this->_chaos->Object()->SetPublishSettings($object->GUID, $publishAccessPointGUID, $now);
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
	protected function getOrCreateObject($DFIId) {
		if($DFIId == null || !is_numeric(strval($DFIId))) {
			throw new RuntimeException("Cannot get or create a CHAOS object for a DFI film without an internal DFI ID (got '$DFIId').");
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
	
	/**
	 * This is the "important" method which generates the metadata XML documents from a MovieItem from the DFI service.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @param bool $validateSchema Should the document be validated against the XML schema?
	 * @throws Exception if $validateSchema is true and the validation fails.
	 * @return DOMDocument Representing the DFI movie in the DKA Program specific schema.
	 */
	protected function generateXML($movieItem, $fileTypes) {
		$result = array(
			DKAXMLGenerator::SCHEMA_GUID => DKAXMLGenerator::instance()->generateXML(array("movieItem" => $movieItem, "fileTypes" => $fileTypes), false),
			DKA2XMLGenerator::SCHEMA_GUID => DKA2XMLGenerator::instance()->generateXML(array("movieItem" => $movieItem, "fileTypes" => $fileTypes), true),
			DFIXMLGenerator::SCHEMA_GUID => DFIXMLGenerator::instance()->generateXML(array("movieItem" => $movieItem), true)
		);
		
		return $result;
	}
	
	// Helpers
	
	/**_CHAOSLowResImageFormatID
	 * Checks if this movie should be excluded from the harvest, because of censorship.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @return bool True if this movie should be excluded, false otherwise.
	 */
	public static function shouldBeCensored($movieItem) {
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			if($subCategory == 'Pornofilm' || $subCategory == 'Erotiske film') {
				return "The subcategory is $subCategory.";
			}
		}
		return false;
	}
	
	/**
	 * Extract the revisions for the metadata currently associated with the object.
	 */
	public static function extractMetadataRevisions($object) {
		$result = array();
		foreach($object->Metadatas as $metadata) {
			// The schema matches the metadata.
			$result[strtolower($metadata->MetadataSchemaGUID)] = $metadata->RevisionID;
		}
		return $result;
	}
	
	// CHAOS specific methods
	
	/**
	 * Initialize the CHAOS part of the harvester.
	 * This involves fetching a session from the service,
	 * authenticating it,
	 * fetching the metadata schema for the DKA Program content,
	 * fetching the object type (DKA Program) to identify its id on the CHAOS service,
	 * fetching the DKA image format to use for images associated with a particular DFI movie,
	 * fetching the DKA video format to use for movieclips associated with a particular DFI movie,
	 * fetching the folder on the CHAOS system to use when creating DKA Programs, based on the DFI_FOLDER const. 
	 * @throws RuntimeException If any service call fails. This might be due to an unadvailable service,
	 * or an unenticipated change in the protocol.
	 */
	protected function CHAOS_initialize() {
		printf("Creating a session for the CHAOS service on %s using clientGUID %s: ", $this->_CHAOSURL, $this->_CHAOSClientGUID);
		// Create a new client, a session is automaticly created.
		timed();
		$this->_chaos = new PortalClient($this->_CHAOSURL, $this->_CHAOSClientGUID);
		timed('chaos');
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded: SessionGUID is %s\n", $this->_chaos->SessionGUID());
		}
		timed();
		$this->CHAOS_authenticateSession();
		$this->CHAOS_fetchMetadataSchemas();
		$this->CHAOS_fetchDKAObjectType();
		timed('chaos');
		//$this->CHAOS_fetchDFIFolder();
	}
	
	/**
	 * Authenticate the CHAOS session using the environment variables for email and password.
	 * @throws RuntimeException If the authentication fails.
	 */
	protected function CHAOS_authenticateSession() {
		printf("Authenticating the session using email %s: ", $this->_CHAOSEmail);
		$result = $this->_chaos->EmailPassword()->Login($this->_CHAOSEmail, $this->_CHAOSPassword);
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters.");
		} else {
			printf("Succeeded.\n");
		}
	}
	
	/**
	 * Fetches the DKA Program metadata schema and stores it in the _DKAMetadataSchema field.
	 * @throws RuntimeException If it fails.
	 */
	protected function CHAOS_fetchMetadataSchemas() {
		printf("Looking up the DKA metadata schema GUID: ");
		
		DKAXMLGenerator::instance()->fetchSchema($this->_chaos);
		DKA2XMLGenerator::instance()->fetchSchema($this->_chaos);
		DFIXMLGenerator::instance()->fetchSchema($this->_chaos);
		
		printf("Succeeded.\n");
	}
	
	/**
	 * Fetches the DKA Program object type and stores it in the _DKAObjectType field.
	 * @throws RuntimeException If it fails.
	 */
	protected function CHAOS_fetchDKAObjectType() {
		printf("Looking up the DKA Program type: ");
		$result = $this->_chaos->ObjectType()->Get();
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't lookup the DKA object type for the DKA specific data.");
		}
		
		$this->_DKAObjectType = null;
		foreach($result->MCM()->Results() as $objectType) {
			if($objectType->Name === self::DKA_OBJECT_TYPE_NAME) {
				// We found the DKA Program type.
				$this->_DKAObjectType = $objectType;
				break;
			}
		}
		
		if($this->_DKAObjectType == null) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't find the DKA object type.");
		} else {
			printf("Succeeded, it has ID: %s\n", $this->_DKAObjectType->ID);
		}
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
		
		DFIImageExtractor::instance()->_CHAOSImageDestinationID = $this->_CHAOSImageDestinationID;
		DFIImageExtractor::instance()->_CHAOSImageFormatID = $this->_CHAOSImageFormatID;
		DFIImageExtractor::instance()->_CHAOSLowResImageFormatID = $this->_CHAOSLowResImageFormatID;
		DFIImageExtractor::instance()->_CHAOSThumbnailImageFormatID = $this->_CHAOSThumbnailImageFormatID;
		DFIVideoExtractor::instance()->_CHAOSVideoDestinationID = $this->_CHAOSVideoDestinationID;
		DFIVideoExtractor::instance()->_CHAOSVideoFormatID = $this->_CHAOSVideoFormatID;
	}
}

// Call the main method of the class.
DFIIntoDKAHarvester::main($_SERVER['argv']);