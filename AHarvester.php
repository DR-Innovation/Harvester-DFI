<?php
/**
 * This abstract harvester copies information into a CHAOS service.
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

/**
 * An abstract harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-DFI
 * @since      Class available since Release 0.1
 */
abstract class AHarvester {
	protected static function extractOptionsFromArguments($args) {
		$result = array();
		for($i = 0; $i < count($args); $i++) {
			if(strpos($args[$i], '--') === 0) {
				$equalsIndex = strpos($args[$i], '=');
				if($equalsIndex === false) {
					$name = substr($args[$i], 2);
					$result[$name] = true;
				} else {
					$name = substr($args[$i], 2, $equalsIndex-2);
					$value = substr($args[$i], $equalsIndex+1);
					if($value == 'true') {
						$result[$name] = true;
					} elseif($value == 'false') {
						$result[$name] = false;
					} else {
						$result[$name] = $value;
					}
				}
			}
		}
		return $result;
	}
	
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
}