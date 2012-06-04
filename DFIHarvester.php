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

/**
 * Main class of the DFI Harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-DFI
 * @since      Class available since Release 0.1
 */
class DFIHarvester {
	
	const VERSION = "0.1";
	
	/**
	 * Main method of the harvester, call this once.
	 */
	function main() {
		printf("DFIHarvester %s started %s.\n", DFIHarvester::VERSION, date('D, d M Y H:i:s'));
		
		try {
			$h = new DFIHarvester();
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
	protected $_chaos;
	
	/**
	 * The DFI client to be used for communication with the DFI Service. 
	 * @var PortalClient
	 */
	protected $_dfi;
	
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
	
	// CHAOS specific
	protected function initializeCHAOS() {
		printf("Creating a session for the CHAOS service on %s using clientGUID %s: ", $this->_CHAOSURL, $this->_CHAOSClientGUID);
		
		// Create a new client, a session is automaticly created.
		$this->_chaos = new PortalClient($this->_CHAOSURL, $this->_CHAOSClientGUID);
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded.\n");
		}
		
		printf("Authenticating the session using email %s: ", $this->_CHAOSEmail);
		$result = $this->_chaos->EmailPassword()->Login($this->_CHAOSEmail, $this->_CHAOSPassword);
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters.");
		} else {
			printf("Succeeded.\n");
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

DFIHarvester::main();