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
class DFIIntoDKAHarvester {
	
	const VERSION = "0.1";
	const DKA_SCHEMA_NAME = "DKA";
	const DKA2_SCHEMA_NAME = "DKA2";
	const DFI_SCHEMA_NAME = "DKA.DFI";
	const DKA_OBJECT_TYPE_NAME = "DKA Program";
	//const DKA_XML_REVISION = 1; // Used when overwriting older versions of Metadata XML on a CHAOS object.
	const DFI_FOLDER = "DFI/public";
	const DFI_ORGANIZATION_NAME = "Dansk Film Institut";
	const RIGHTS_DESCIPTION = "Copyright © Dansk Film Institut"; // TODO: Is this correct?
	const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';
	const DFI_VIDEO_BASE = 'http://video.dfi.dk/';
	
	/**
	 * Main method of the harvester, call this once.
	 */
	function main() {
		printf("DFIIntoDKAHarvester %s started %s.\n", DFIIntoDKAHarvester::VERSION, date('D, d M Y H:i:s'));
		
		try {
			$h = new DFIIntoDKAHarvester();
			//$h->processMovies();
			//$h->processMovie("http://nationalfilmografien.service.dfi.dk/movie.svc/17");
			$h->processMovie("http://nationalfilmografien.service.dfi.dk/movie.svc/7286");
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
	
	protected $_MetadataSchemas;
	
	protected $_DKAObjectType;
	
// 	protected $_DKAImageFormat;
	
// 	protected $_DKAVideoFormat;
	
	protected $_DFIFolder;
	
	/**
	 * Constructor for the DFI Harvester
	 * @throws RuntimeException if the CHAOS services are unreachable or
	 * if the CHAOS credentials provided fails to authenticate the session.
	 */
	public function __construct() {
		$url = "";
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
		"CHAOS_THUMBNAIL_IMAGE_FORMAT_ID" => "_CHAOSThumbnailImageFormatID",
		"CHAOS_VIDEO_FORMAT_ID" => "_CHAOSVideoFormatID",
		"CHAOS_IMAGE_DESTINATION_ID" => "_CHAOSImageDestinationID",
		"CHAOS_VIDEO_DESTINATION_ID" => "_CHAOSVideoDestinationID"
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
	 * This method calls fetchAllMovies on the 
	 * @param int $delay A non-negative integer specifing the amount of micro seconds to sleep between each call to the API when fetching movies, use this to do a slow fetch.
	 */
	public function processMovies($delay = 0) {
		printf("Fetching ids for all movies: ");
		//$movies = $this->_dfi->fetchAllMovies(1000, $delay);
		$movies = $this->_dfi->fetchMovies(0, 10); // Used when debugging.
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
	 * @param string $reference the URL address referencing the movie through the DFI service.
	 * @throws RuntimeException If it fails to set the metadata on a chaos object,
	 * this will most likely happen if the service is broken, or in lack of permissions.
	 */
	public function processMovie($reference) {
		$movieItem = MovieItem::fetch($this->_dfi, $reference);
		$movieItem->registerXPathNamespace('dfi', 'http://schemas.datacontract.org/2004/07/Netmester.DFI.RestService.Items');
		$movieItem->registerXPathNamespace('a', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');
		
		// Check to see if this movie is known to CHAOS.
		//$chaosObjects = $this->_chaos->Object()->GetByFolderID($this->_DFIFolder->ID, true, null, 0, 10);
		$object = $this->getOrCreateObject($movieItem->id);
		
		// We create a list of files that have been processed and reused.
		$object->ProcessedFiles = array();
		
		$imagesProcessed = $this->processMovieImages($object, $movieItem);
		$videosProcessed = $this->processMovieVideos($object, $movieItem);
		$types = array();
		if($imagesProcessed > 0) {
			$types[] = "Picture";
		}
		if($videosProcessed > 0) {
			$types[] = "Video";
		}
		
		// Do we have any files on the object which has not been processed by the search?
		foreach($object->Files as $f) {
			if(!in_array($f, $object->ProcessedFiles)) {
				printf("\t[!] The file '%s' (%s) was a file of the object, but not processed, maybe it was deleted from the DFI service.", $f->Filename, $f->ID);
			}
		}
		
		/* // For debugging only
		$dom = dom_import_simplexml($movieItem)->ownerDocument;
		$dom->formatOutput = true;
		printf("Input XML: %s", $dom->saveXML());
		*/
		
		$xml = $this->generateXML($movieItem, $types);
		
		/* // For debugging only - nice formatting.
		printf("Generated XML: %s", $xml);
		*/
		
		$revisions = self::extractMetadataRevisions($this->_MetadataSchemas, $object);
		
		foreach($this->_MetadataSchemas as $name => $schema) {
			printf("\tSetting '$name' metadata on the CHAOS object: ");
			// This is not implemented.
			// $currentMetadata = $this->_chaos->Metadata()->Get($object->GUID, $schema->GUID, 'da');
			//var_dump($currentMetadata);
			$response = $this->_chaos->Metadata()->Set($object->GUID, $schema->GUID, 'da', $revisions[$name], $xml[$name]->saveXML());
			if(!$response->WasSuccess()) {
				printf("Failed.\n");
				throw new RuntimeException("Couldn't set the metadata on the CHAOS object.");
			} else {
				printf("Succeeded.\n");
			}
		}
		
		exit;
	}
	
	/**
	 * Process all the images associated with a movie from the DFI service.
	 * @param stdClass $object Representing the DKA program in the CHAOS service, of which the images should be added to.
	 * @param \dfi\model\MovieItem $movieItem The DFI MovieItem from which the images should be extracted.
	 */
	public function processMovieImages($object, $movieItem) {
		$imagesProcessed = 0;
		$urlBase = self::DFI_IMAGE_SCANPIX_BASE_PATH;
		
		$imagesRef = $movieItem->Images;
		$images = $this->_dfi->load($imagesRef);
		
		printf("\tUpdating files for %u images:\t", count($images->PictureItem));
		//$this->resetProgress(count($images->PictureItem));
		//$progress = 0;
		echo self::PROGRESS_END_CHAR;
		
		foreach($images->PictureItem as $i) {
			//$this->updateProgress($progress++);
			// The following line is needed as they forget to set their encoding.
			//$i->Caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			//echo "\$caption = $caption\n";
			//printf("\tFound an image with the caption '%s'.\n", $i->Caption);
			// TODO: Make sure the _DKAImagesFormatID is fetched correctly and use this to determine the $formatID.
			$imageURLS = array(
					(string)$i->SrcMini => (integer)$this->_CHAOSImageFormatID,
					(string)$i->SrcThumb => (integer)$this->_CHAOSThumbnailImageFormatID);
			
			foreach($imageURLS as $url => $formatId) {
				$filenameMatches = array();
				if(preg_match("#$urlBase(.*)#", $url, $filenameMatches) === 1) {
					$pathinfo = pathinfo($filenameMatches[1]);
					$response = $this->getOrCreateFile($object, null, $formatId, $this->_CHAOSImageDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
					
					if($response == null) {
						throw new RuntimeException("Failed to create an image file.");
					} else {
						$object->ProcessedFiles[] = $response;
						$imagesProcessed++;
					}
				} else {
					printf("\tWarning: Found an images which was didn't have a scanpix/mini URL. This was not imported.\n");
				}
			}
		}
		echo self::PROGRESS_END_CHAR;
		
		printf(" Done\n");
		return $imagesProcessed;
	}
	
	/**
	 * Process all the movieclips associated with a movie from the DFI service.
	 * @param stdClass $object Representing the DKA program in the CHAOS service, of which the movies should be added to.
	 * @param \dfi\model\MovieItem $movieItem The DFI MovieItem from which the movies should be extracted.
	 */
	public function processMovieVideos($object, $movieItem) {
		$videosProcessed = 0;
		$urlBase = self::DFI_VIDEO_BASE;
		
		$movies = $movieItem->xpath("/dfi:MovieItem/dfi:FlashMovies/dfi:FlashMovieItem");
		
		printf("\tUpdating files for %u videos:\t", count($movies));
		//$this->resetProgress(count($movies));
		//$progress = 0;
		
		echo self::PROGRESS_END_CHAR;
		foreach($movies as $m) {
			//$this->updateProgress($progress++);
			// TODO: Implement the creation of files in CHAOS.
			// The following line is needed as they forget to set their encoding.
			//$i->Caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			//echo "\$caption = $caption\n";
			//printf("\tFound an image with the caption '%s'.\n", $i->Caption);
			// TODO: Make sure the _DKAImagesFormatID is fetched correctly and use this to determine the $formatID.
			$miniFilenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $m->FilmUrl, $miniFilenameMatches) === 1) {
				$pathinfo = pathinfo($miniFilenameMatches[1]);
				$response = $this->getOrCreateFile($object, null, $this->_CHAOSVideoFormatID, $this->_CHAOSVideoDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
				if($response == null) {
					throw new RuntimeException("Failed to create a video file.");
				} else {
					$object->ProcessedFiles[] = $response;
					$videosProcessed++;
				}
			} else {
				printf("\tWarning: Found an images which was didn't have a scanpix/mini URL. This was not imported.\n");
			}
		}
		echo self::PROGRESS_END_CHAR;
		printf(" Done\n");
		
		return $videosProcessed;
	}
	
	/**
	 * Gets or creates an object in the CHAOS service, which represents a
	 * particular DFI movie.
	 * @param int $DFIId The internal id of the movie in the DFI service.
	 * @throws RuntimeException If the request or creation of the object fails.
	 * @return stdClass Representing the CHAOS existing or newly created DKA program -object.
	 */
	protected function getOrCreateObject($DFIId) {
		$folderId = $this->_DFIFolder->ID;
		$objectTypeId = $this->_DKAObjectType->ID;
		// Query for a CHAOS Object that represents the DFI movie.
		// TODO: Add the internal DKA ID the a DKA specific schema and include this in the query.
		$response = $this->_chaos->Object()->Get("(FolderTree:$folderId AND ObjectTypeID:$objectTypeId)", "DateCreated+desc", null, 0, 100, true, true);
		
		if(!$response->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: ". $response->Error()->Message());
		} else if(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: ". $response->MCM()->Error()->Message());
		}
		
		$results = $response->MCM()->Results();
		//var_dump($results);
		
		// If it's not there, create it.
		if($response->MCM()->TotalCount() == 0) {
			printf("\tFound a film in the DFI service which is not already represented by a CHAOS object.\n");
			$response = $this->_chaos->Object()->Create($this->_DKAObjectType->ID, $this->_DFIFolder->ID);
			$results = $response->MCM()->Results();
			if(!$response->WasSuccess() || $response->MCM()->TotalCount() != 1) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->Error()->Message());
			} else if(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->MCM()->Error()->Message());
			}
		} else {
			printf("\tReusing CHAOS object with GUID = %s.\n", $results[0]->GUID);
		}
		
		return $results[0];
	}
	
	/**
	 * Gets or creates a new file reference.
	 * If the file is already present on the system it simply returns the file.
	 * NB: This is not correctly implemented yet, it will simply create a new file no matter what.
	 * This is due to the state of the CHAOS PHP clients implementation state.
	 * @param stdClass $objectGUID
	 * @param int|null $parentFileID The FileID of an original file this file was created from, otherwise null.
	 * @param int $formatID
	 * @param int $destinationID
	 * @param string $filename
	 * @param string $originalFilename
	 * @param string $folderPath
	 * @return \CHAOS\Portal\Client\Data\ServiceResult
	 */
	protected function getOrCreateFile($object, $parentFileID, $formatID, $destinationID, $filename, $originalFilename, $folderPath, $printProgress = true) {
		$formatID = (int) $formatID;
		// Check if it is on the $object's list of Files.
		
		// FIXME: Get is not implemented, so we cannot lookup the file.
		// But we can iterate over the objects files.
		foreach($object->Files as $f) {
			// TODO: Check on the $f->URL instead ...
			$fileEquals =
				$f->ParentID === $parentFileID &&
				$f->FormatID === $formatID &&
				$f->Filename === $filename &&
				$f->OriginalFilename === $originalFilename;
			if($fileEquals) {
				// A file has already been created.
				if($printProgress) {
					echo ".";
				}
				return $f;
			}
		}
		
		// File is not known to the CHAOS system, creating it.
		$response = $this->_chaos->File()->Create($object->GUID, $parentFileID, $formatID, $destinationID, $filename, $originalFilename, $folderPath);
		if(!$response->WasSuccess()) {
			if($printProgress) {
				echo "!";
			}
			throw new RuntimeException("Failed to create the video file in the CHAOS service: ". $response->Error()->Message());
		} elseif (!$response->MCM()->WasSuccess()) {
			if($printProgress) {
				echo "!";
			}
			throw new RuntimeException("Failed to create the video file in the CHAOS service: ". $response->MCM()->Error()->Message());
		} else {
			if($printProgress) {
				echo "+";
			}
			$results = $response->MCM()->Results();
			return $results[0];
		}
	}
	
	/**
	 * This is the "important" method which generates the metadata XML documents from a MovieItem from the DFI service.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @param bool $validateSchema Should the document be validated against the XML schema?
	 * @throws Exception if $validateSchema is true and the validation fails.
	 * @return DOMDocument Representing the DFI movie in the DKA Program specific schema.
	 */
	protected function generateXML($movieItem, $fileTypes, $validateSchema = false) {
		
		$result = array(
			self::DKA_SCHEMA_NAME => new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DKA></DKA>"),
			self::DKA2_SCHEMA_NAME => new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DKA></DKA>"),
			self::DFI_SCHEMA_NAME => new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><DFI></DFI>")
		);
		
		
		// DFI Schema
		
		$result[self::DKA_SCHEMA_NAME]->addChild("Title", $movieItem->Title);
		
		// TODO: Consider if this is the correct mapping.
		$result[self::DKA_SCHEMA_NAME]->addChild("Abstract", $movieItem->Comment);
		
		$result[self::DKA_SCHEMA_NAME]->addChild("Description", $movieItem->Description);
		
		$result[self::DKA_SCHEMA_NAME]->addChild("Organization", self::DFI_ORGANIZATION_NAME);
		
		// TODO: Look into which types are needed for what.
		$result[self::DKA2_SCHEMA_NAME]->addChild("Type", implode(',', $fileTypes));
		
		// TODO: Determine if this is when the import happened or when the movie was created?
		if(strlen($movieItem->ProductionYear) > 0) {
			$result[self::DKA_SCHEMA_NAME]->addChild("CreatedDate", self::yearToXMLDate((string)$movieItem->ProductionYear));
		}
		
		if(strlen($movieItem->ReleaseYear) > 0) {
			$result[self::DKA_SCHEMA_NAME]->addChild("FirstPublishedDate", self::yearToXMLDate((string)$movieItem->ReleaseYear));
		}
		
		// TODO: Make sure that this can infact be the DFI ID.
		$result[self::DKA_SCHEMA_NAME]->addChild("Identifier", $movieItem->ID);
		
		$contributors = $result[self::DKA_SCHEMA_NAME]->addChild("Contributor");
		foreach($movieItem->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$person = $contributors->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(strval($creditListItem->Type)));
			}
		}
		
		$creators = $result[self::DKA_SCHEMA_NAME]->addChild("Creator");
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:Credits/dfi:CreditListItem') as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$person = $creators->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(strval($creditListItem->Type)));
			}
		}
		
		$format = trim($movieItem->Format);
		if($format !== '') {
			$result[self::DKA_SCHEMA_NAME]->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result[self::DKA_SCHEMA_NAME]->addChild("Location", $movieItem->CountryOfOrigin);
		
		$result[self::DKA_SCHEMA_NAME]->addChild("RightsDescription", self::RIGHTS_DESCIPTION);
		
		/* // Needs to be here if the validation should succeed.
		$GeoData = $result->addChild("GeoData");
		$GeoData->addChild("Latitude", "0.0");
		$GeoData->addChild("Longitude", "0.0");
		*/
		
		$Categories = $result[self::DKA_SCHEMA_NAME]->addChild("Categories");
		$Categories->addChild("Category", $movieItem->Category);
		
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", $subCategory);
		}
		
		$Tags = $result[self::DKA_SCHEMA_NAME]->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		// DFI2 Schema
		
		$result[self::DKA2_SCHEMA_NAME]->addChild("Title", $movieItem->Title);
		
		// TODO: Consider if this is the correct mapping.
		$result[self::DKA2_SCHEMA_NAME]->addChild("Abstract", $movieItem->Comment);
		
		$result[self::DKA2_SCHEMA_NAME]->addChild("Description", $movieItem->Description);
		
		$result[self::DKA2_SCHEMA_NAME]->addChild("Organization", self::DFI_ORGANIZATION_NAME);
		
		// TODO: Look into which types are needed for what.
		$result[self::DKA2_SCHEMA_NAME]->addChild("Type", implode(',', $fileTypes));
		
		// TODO: Determine if this is when the import happened or when the movie was created?
		if(strlen($movieItem->ProductionYear) > 0) {
			$result[self::DKA2_SCHEMA_NAME]->addChild("CreatedDate", self::yearToXMLDate((string)$movieItem->ProductionYear));
		}
		
		if(strlen($movieItem->ReleaseYear) > 0) {
			$result[self::DKA2_SCHEMA_NAME]->addChild("FirstPublishedDate", self::yearToXMLDate((string)$movieItem->ReleaseYear));
		}
		
		$contributors = $result[self::DKA2_SCHEMA_NAME]->addChild("Contributor");
		foreach($movieItem->Credits->children() as $creditListItem) {
			if($this->isContributor($creditListItem->Type)) {
				$person = $contributors->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(strval($creditListItem->Type)));
			}
		}
		
		$creators = $result[self::DKA2_SCHEMA_NAME]->addChild("Creator");
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:Credits/dfi:CreditListItem') as $creditListItem) {
			if($this->isCreator($creditListItem->Type)) {
				$person = $creators->addChild("Person");
				$person->addAttribute("Name", $creditListItem->Name);
				$person->addAttribute("Role", self::translateCreditTypeToRole(strval($creditListItem->Type)));
			}
		}
		
		$format = trim($movieItem->Format);
		if($format !== '') {
			$result[self::DKA2_SCHEMA_NAME]->addChild("TechnicalComment", "Format: ". $format);
		}
		
		// TODO: Consider if the location is the shooting location or the production location.
		$result[self::DKA2_SCHEMA_NAME]->addChild("Location", $movieItem->CountryOfOrigin);
		
		$result[self::DKA2_SCHEMA_NAME]->addChild("RightsDescription", self::RIGHTS_DESCIPTION);
		
		$Categories = $result[self::DKA2_SCHEMA_NAME]->addChild("Categories");
		$Categories->addChild("Category", $movieItem->Category);
		
		foreach($movieItem->xpath('/dfi:MovieItem/dfi:SubCategories/a:string') as $subCategory) {
			$Categories->addChild("Category", $subCategory);
		}
		
		$Tags = $result[self::DKA2_SCHEMA_NAME]->addChild("Tags");
		$Tags->addChild("Tag", "DFI");
		
		// DFI Schema
		
		$result[self::DFI_SCHEMA_NAME]->addChild("ID", $movieItem->ID);
		
		/* // Needs to be here if the validation should succeed.
		$result->addChild("ProductionID");
		
		$result->addChild("StreamDuration");
		*/
		
		$dom = array();
		foreach($result as $name => $xml) {
			$dom[$name] = dom_import_simplexml($xml)->ownerDocument;
			$dom[$name]->formatOutput = true;
		
			if($validateSchema && !$dom->schemaValidateSource($this->_DKAMetadataSchema->SchemaXML)) {
				throw new Exception("The XML generated does not validate with the schema.");
			}
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
			case 'Director':
				return self::CREATOR;
			default:
				return self::CONTRIBUTOR;
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
	
	// Helpers
	
	/**
	 * Transforms a 4-digit year into an XML data YYYY-MM-DD format.
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
	 * Resolves the CHAOS folders on a path, by recursively calling and storing each foldername along the path.
	 * @param string|array $path The path.
	 * @param unknown_type $parentId The parent ID of the folder from which to start the search, null if the path is absolute.
	 * @throws InvalidArgumentException If the argument is neither a string nor an array.
	 * @return multitype:stdClass An array of CHAOS folders, as returned from the service.
	 */
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
	
	public static function extractMetadataRevisions($metadataSchemas, $object) {
		$result = array();
		foreach($metadataSchemas as $schemaName => $schema) {
			$result[$schemaName] = null;
			foreach($object->Metadatas as $metadata) {
				if($schema->GUID == $metadata->MetadataSchemaGUID) {
					// The schema matches the metadata.
					$result[$schemaName] = $metadata->RevisionID;
				}
			}
		}
		return $result;
	}
	
	public static function extractFileTypes($movieItem) {
		$result = array();
		if(count($movieItem->FlashMovies) > 0) {
			$result[] = "Video";
		}
		if(count($movieItem->Images) > 0) {
			$result[] = "Video";
		}
		exit;
	}
	
	protected $progressTotal;
	protected $progressWidth;
	protected $progressDotsPrinted;
	const PROGRESS_DOT_CHAR = '-';
	const PROGRESS_END_CHAR = '|';
	
	public function resetProgress($total, $width = 30) {
		if($total > 0) {
			$this->progressTotal = $total;
			$this->progressWidth = $width;
			$this->progressDotsPrinted = 0;
			echo self::PROGRESS_END_CHAR;
		} else {
			// Reset ...
			$this->progressTotal = 0;
			updateProgress(0);
		}
	}
	
	public function updateProgress($value) {
		if($this->progressTotal <= 1 && $value == 0) {
			$ratioDone = 1;
		} else {
			$ratioDone = $value / ($this->progressTotal - 1);
		}
		$dots = (int) round( $ratioDone * $this->progressWidth);
		//printf("updateProgress(\$value = %s) ~ \$dots = %u\n", $value, $dots);
		while($this->progressDotsPrinted < $dots) {
			echo self::PROGRESS_DOT_CHAR;
			$this->progressDotsPrinted++;
		}
		if($dots >= $this->progressWidth) {
			echo self::PROGRESS_END_CHAR;
		}
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
		$this->_chaos = new PortalClient($this->_CHAOSURL, $this->_CHAOSClientGUID);
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded: SessionGUID is %s\n", $this->_chaos->SessionGUID());
		}
		
		$this->CHAOS_authenticateSession();
		$this->CHAOS_fetchMetadataSchemas();
		$this->CHAOS_fetchDKAObjectType();
// 		$this->CHAOS_fetchDKAImageFormat();
// 		$this->CHAOS_fetchDKAVideoFormat();
		$this->CHAOS_fetchDFIFolder();
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
		$result = $this->_chaos->MetadataSchema()->Get();
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't lookup the metadata schema for the DKA specific data: ".$result->Error()->Message());
		}
		
		$schemaNames = array (self::DKA_SCHEMA_NAME, self::DKA2_SCHEMA_NAME, self::DFI_SCHEMA_NAME);
		
		// Extract the DKA metadata schema.
		$this->_MetadataSchemas = array();
		foreach($result->MCM()->Results() as $schema) {
			if(in_array($schema->Name, $schemaNames)) {
				// We found the DKA metadata schema.
				$this->_MetadataSchemas[$schema->Name] = $schema;
			}
		}
		
		foreach($schemaNames as $n) {
			if(!key_exists($n, $this->_MetadataSchemas)) {
				printf("Failed.\n");
				throw new RuntimeException("Couldn't find the '$n' metadata schema.");
			}
		}
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
	
// 	/**
// 	 * Fetches the DKA Image format and stores it in the _DKAImageFormat field.
// 	 * @throws RuntimeException If it fails.
// 	 */
// 	protected function CHAOS_fetchDKAImageFormat() {
// 		// TODO: Implement to change the value of $this->_DKAImageFormat
// 		// This is not possible atm as the CHAOS PHP-SDK does not support Get queries for formats.
// 		$this->_DKAImageFormat = array('ID' => $this->_CHAOSImageFormatID);
// 	}
	
// 	/**
// 	 * Fetches the DKA Video format and stores it in the _DKAVideoFormat field.
// 	 * @throws RuntimeException If it fails.
// 	 */
// 	protected function CHAOS_fetchDKAVideoFormat() {
// 		// TODO: Implement to change the value of $this->_DKAVideoFormat
// 		// This is not possible atm as the CHAOS PHP-SDK does not support Get queries for formats.
// 		$this->_DKAVideoFormat = array('ID' => $this->_CHAOSVideoFormatID);
// 	}
	
	/**
	 * Fetches the folder on the CHAOS system to use when creating DKA Programs,
	 * based on the DFI_FOLDER const. This is stores in the _DFIFolder field.
	 * @throws RuntimeException If it fails.
	 */
	protected function CHAOS_fetchDFIFolder() {
		$this->_DFIFolder = null;
		
		printf("Looking up the CHAOS folder (%s) to place DFI items: ", self::DFI_FOLDER);
		$path = $this->resolveFoldersOnPath(self::DFI_FOLDER);
		if($path != null && is_array($path)) {
			$this->_DFIFolder = end($path);
		}
		
		if($this->_DFIFolder === null) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't find the folder to place DFI specific data.");
		} else {
			printf("Succeeded, it has ID: %s\n", $this->_DFIFolder->ID);
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
	}
}

// Call the main method of the class.
DFIIntoDKAHarvester::main();