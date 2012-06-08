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

use dfi\model\MovieItem;

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Bootstrapping CHAOS - begin 
if(!isset($_SERVER['CHAOS_CLIENT_SRC'])) {
	die("The CHAOS_CLIENT_SRC env parameter must point to the src directory of a CHAOS PHP Client");
}
set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['CHAOS_CLIENT_SRC']);
require_once("CaseSensitiveAutoload.php"); // This will be reused by this script.
spl_autoload_extensions(".php");
spl_autoload_register("CaseSensitiveAutoload");
// Bootstrapping CHAOS - end

use CHAOS\Portal\Client\PortalClient;
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
class DFIIntoDKAHarvester {
	
	const VERSION = "0.1";
	const DKA_SCHEMA_NAME = "DKA";
	const DKA_OBJECT_TYPE_NAME = "DKA Program";
	const CHAOS_FOLDER = "DFI/public";
	const DFI_ORGANIZATION_NAME = "Dansk Film Institut";
	
	/**
	 * Main method of the harvester, call this once.
	 */
	function main() {
		printf("DFIIntoDKAHarvester %s started %s.\n", DFIIntoDKAHarvester::VERSION, date('D, d M Y H:i:s'));
		
		try {
			$h = new DFIIntoDKAHarvester();
			$h->processMovies();
		} catch (RuntimeException $e) {
			echo "\n";
			die("An unexpected runtime error occured: ".$e->getMessage());
		} catch (Exception $e) {
			echo "\n";
			die("Error occured in the harvester implementation: ".$e);
		}
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
	
	protected $_DKAMetadataSchema;
	
	protected $_DKAObjectType;
	
	protected $_CHAOSFolder;
	
	/**
	 * Constructor for the DFI Harvester
	 * @throws RuntimeException if the CHAOS services are unreachable or
	 * if the CHAOS credentials provided fails to authenticate the session.
	 */
	public function __construct() {
		$url = "";
		$this->loadConfiguration();
		
		$this->initializeCHAOS();
		$this->initializeDFI();
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
	 * An associative array describing the configuration parameters for the harvester.
	 * This should ideally not be changed.
	 * @var array[string]string
	 */
	protected $_CONFIGURATION_PARAMETERS = array(
		"DFI_URL" => "_DFIUrl",
		"CHAOS_CLIENT_GUID" => "_CHAOSClientGUID",
		"CHAOS_URL" => "_CHAOSURL",
		"CHAOS_EMAIL" => "_CHAOSEmail",
		"CHAOS_PASSWORD" => "_CHAOSPassword"
	);
	
	/**
	 * Load the configuration parameters from the string[] argument provided.
	 * @param array[string]string $config An (optional) associative array holding the array
	 * of configuration parameters, defaults to the $_SERVER array.
	 * @throws RuntimeException if an expected environment variable is not sat.
	 * @throws Exception if the CONFIGURATION_PARAMETERS holds a value which is
	 * not a member of the class. This should not be possible.
	 */
	public function loadConfiguration($config = null) {
		if($config == null) {
			$config = $_SERVER; // Default to the server array.
		}
		$this_class = get_class($this);
		foreach($this->_CONFIGURATION_PARAMETERS as $param => $fieldName) {
			if(!key_exists($param, $config)) {
				throw new RuntimeException("The environment variable $param is not sat.");
			} elseif (!property_exists($this_class, $fieldName)) {
				throw new Exception("CONFIGURATION_PARAMETERS contains a value ($fieldName) for a param ($param) which is not a property for the class ($this_class).");
			} else {
				$this->$fieldName = $config[$param];
			}
		}
	}
	
	/**
	 * Fetch and process all advailable DFI movies.
	 */
	public function processMovies() {
		printf("Fetching ids for all movies: ");
		//$movies = $this->_dfi->fetchAllMovies(1000, 100000);
		$movies = $this->_dfi->fetchMovies(0, 10);
		printf("done.\n");
		
		printf("Iterating over every movie.\n");
		for($i = 0; $i < count($movies); $i++) {
			$m = $movies[$i];
			printf("Starting to process '%s' (%u/%u)\n", $m->Name, $i+1, count($movies));
			$this->processMovie($m->Ref);
		}
	}
	
	/**
	 * Fetch and process a single DFI movie.
	 */
	public function processMovie($reference) {
		$movieItem = MovieItem::fetch($this->_dfi, $reference);
		// Check to see if this movie is known to CHAOS.
		//$chaosObjects = $this->_chaos->Object()->GetByFolderID($this->_CHAOSFolder->ID, true, null, 0, 10);
		$object = $this->getOrCreateDKAObject($movieItem->id);
		
		$dom = dom_import_simplexml($movieItem)->ownerDocument;
		$dom->formatOutput = true;
		
		printf("Input XML: %s", $dom->saveXML());
		
		$xml = $this->generateDKAObjectXML($movieItem);
		
		// Nice formatting.
		printf("Generated XML: %s", $xml);
		
		printf("\tSetting metadata on the CHAOS object: ");
		$response = $this->_chaos->Metadata()->Set($object->GUID, $this->_DKAMetadataSchema->GUID, 'da', null, $xml);
		if(!$response->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Success.\n");
		}
		
		exit;
	}
	
	// CHAOS specific
	protected function initializeCHAOS() {
		printf("Creating a session for the CHAOS service on %s using clientGUID %s: ", $this->_CHAOSURL, $this->_CHAOSClientGUID);
		
		// Create a new client, a session is automaticly created.
		$this->_chaos = new PortalClient($this->_CHAOSURL, $this->_CHAOSClientGUID);
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded: SessionGUID is %s\n", $this->_chaos->SessionGUID());
		}
		
		$this->authenticateCHAOSSession();
		$this->fetchDKAMetadataSchema();
		$this->fetchDKAObjectType();
		$this->fetchCHAOSFolder();
	}
	
	protected function authenticateCHAOSSession() {
		printf("Authenticating the session using email %s: ", $this->_CHAOSEmail);
		$result = $this->_chaos->EmailPassword()->Login($this->_CHAOSEmail, $this->_CHAOSPassword);
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters.");
		} else {
			printf("Succeeded.\n");
		}
	}
	
	protected function fetchDKAMetadataSchema() {
		printf("Looking up the DKA metadata schema GUID: ");
		$result = $this->_chaos->MetadataSchema()->Get();
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't lookup the metadata schema for the DKA specific data.");
		}
		
		// Extract the DKA metadata schema.
		$this->_DKAMetadataSchema = null;
		foreach($result->MCM()->Results() as $schema) {
			if($schema->Name === self::DKA_SCHEMA_NAME) {
				// We found the DKA metadata schema.
				$this->_DKAMetadataSchema = $schema;
				break;
			}
		}
		
		if($this->_DKAMetadataSchema == null) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't find the metadata schema for the DKA specific data.");
		} else {
			printf("Succeeded, it has GUID: %s\n", $this->_DKAMetadataSchema->GUID);
		}
	}
	
	protected function fetchDKAObjectType() {
		printf("Looking up the DKA Program type: ");
		$result = $this->_chaos->ObjectType()->Get();
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't lookup the metadata schema for the DKA specific data.");
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
	
	protected function fetchCHAOSFolder() {
		$this->_CHAOSFolder = null;
		
		printf("Looking up the CHAOS folder (%s) to place DFI items: ", self::CHAOS_FOLDER);
		$path = $this->resolveFoldersOnPath(self::CHAOS_FOLDER);
		if($path != null && is_array($path)) {
			$this->_CHAOSFolder = end($path);
		}
		
		if($this->_CHAOSFolder === null) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't find the folder to place DFI specific data.");
		} else {
			printf("Succeeded, it has ID: %s\n", $this->_CHAOSFolder->ID);
		}
	}
	
	protected function getOrCreateDKAObject($DFIId) {
		$folderId = $this->_CHAOSFolder->ID;
		// Query for a CHAOS Object that represents the DFI movie.
		$response = $this->_chaos->Object()->Get("(FolderTree:$folderId)", null, null, 0, 1);
		if(!$response->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: ". $response->Error()->Message());
		}
		
		// If it's not there, create it.
		if($response->MCM()->TotalCount() == 0) {
			$response = $this->_chaos->Object()->Create($this->_DKAObjectType->ID, $this->_CHAOSFolder->ID);
			if(!$response->WasSuccess() || $response->MCM()->TotalCount() != 1) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->Error()->Message());
			}
		}
		
		$results = $response->MCM()->Results();
		return $results[0];
	}
	
	protected function generateDKAObjectXML($movieItem) {
		$result = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DKA></DKA>");
		
		/*
		 * <?xml version="1.0" encoding="UTF-8"?>
		 * <DKA xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="DKA.xsd">
		 *   <Title>Title</Title>
		 *   <Abstract>Abstract</Abstract>
		 *   <Description>Description</Description>
		 *   <Organization>Organization</Organization>
		 *   <Type>Test</Type>
		 *   <CreatedDate>2001-01-01</CreatedDate>
		 *   <FirstPublishedDate>2001-01-01</FirstPublishedDate>
		 *   <Identifier>Identifier</Identifier>
		 *   <Contributor/>
		 *   <Creator/>
		 *   <TechnicalComment>TechnicalComment</TechnicalComment>
		 *   <Location>Location</Location>
		 *   <RightsDescription>RightsDescription</RightsDescription>
		 *   <GeoData>
		 *     <Latitude>0.0</Latitude>
		 *     <Longitude>0.0</Longitude>
		 *   </GeoData>
		 *   <Categories>
		 *     <Category>Category</Category>
		 *   </Categories>
		 *   <Tags>
		 *     <Tag>Tag</Tag>
		 *   </Tags>
		 *   <ProductionID>ProductionID</ProductionID>
		 *   <StreamDuration>StreamDuration</StreamDuration>
		 * </DKA>
		 */
		
		$result->addChild("Title", $movieItem->Title);
		// TODO: Consider if this is the correct mapping.
		$result->addChild("Abstract", $movieItem->Comment);
		$result->addChild("Description", $movieItem->Description);
		$result->addChild("Organization", self::DFI_ORGANIZATION_NAME);
		// TODO: Look into which types are needed for what.
		$result->addChild("Type", "Test");
		// TODO: Determine if this is when the import happened or when the movie was created?
		if(strlen($movieItem->ProductionYear) > 0) {
			$result->addChild("CreatedDate", self::yearToXMLDate((string)$movieItem->ProductionYear));
		}
		if(strlen($movieItem->ReleaseYear) > 0) {
			$result->addChild("FirstPublishedDate", self::yearToXMLDate((string)$movieItem->ReleaseYear));
		}
		// TODO: Make sure that this can infact be the DFI ID.
		$result->addChild("Identifier", $movieItem->ID);
		$contributors = $result->addChild("Contributor");
		foreach($movieItem->Credits->children() as $creditListItem) {
			$person = $contributors->addChild("Person");
			$person->addAttribute("Name", $creditListItem->Name);
			$person->addAttribute("Role", self::translateRole($creditListItem->Type));
		}
		//$result->addChild("CreatedDate", self::yearToXMLDate((string)$movieItem->ProductionYear));
		//$result->addChild("FirstPublishedDate", self::yearToXMLDate((string)$movieItem->ReleaseYear));
		
		//
		
		/*
		$imagesRef = $movieItem->Images;
		$images = $this->_dfi->load($imagesRef);
		foreach($images->PictureItem as $i) {
			//$caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			//echo "\$caption = $caption\n";
				
		}
		*/
		
		$dom = dom_import_simplexml($result)->ownerDocument;
		$dom->formatOutput = true;
		/*
		if(!$dom->schemaValidateSource($this->_DKAMetadataSchema->SchemaXML)) {
			throw new Exception("The XML generated does not validate with the schema.");
		}
		*/
		
		return $dom->saveXML();
	}
	
	public static function translateRole($role) {
		switch($role) {
			default:
				return $role;
		}
	}
	
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
	
	// Helpers
	
	protected function resolveFoldersOnPath($path, $parentId = null) {
		if(is_string($path)) {
			// Extract an array of non empty folder names on the path.
			$folderNames = array();
			$explodedPath = explode("/", $path);
			foreach($explodedPath as $folder) {
				if($folder !== '') { // Ignore empty foldernames.
					$folderNames[] = $folder;
				}
			}
			$path = $folderNames;
		} elseif (!is_array($path)) {
			throw new InvalidArgumentException("The argument must be a string or an array.");
		}
		
		if(count($path) == 0) {
			return array(); // Base case.
		} else {
			$currentFolderName = $path[0];
			$restOfPath = array_slice($path, 1);
			
			$response = $this->_chaos->Folder()->Get(null, $parentId);
			if(!$response->WasSuccess()) {
				return null;
			}
			foreach($response->MCM()->Results() as $folder) {
				if($folder->Name === $currentFolderName) {
					// We found it ..
					// Call recursively of all children.
					$result = $this->resolveFoldersOnPath($restOfPath, $folder->ID);
					if($result === null) {
						return null;
					}
					array_unshift($result, $folder);
					return $result;
				}
			}
			return null; // No subfolder had the correct name.
		}
	}
	
	// DFI specific
	protected function initializeDFI() {
		printf("Looking up the DFI service %s: ", $this->_DFIUrl);
		$this->_dfi = new DFIClient($this->_DFIUrl);
		if(!$this->_dfi->isServiceAdvailable()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't connect to the DFI service.");
		} else {
			printf("Succeeded.\n");
		}
	}
}

DFIIntoDKAHarvester::main();