<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class MainImageFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {

	const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';

	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$urlBase = self::DFI_IMAGE_SCANPIX_BASE_PATH;
		
		$mainImage = $externalObject->MainImage->SrcMini;
		$filenameMatches = array();
		if(count($mainImage) > 0 && preg_match("#$urlBase(.*)#", $mainImage[0], $filenameMatches) === 1) {
			$pathinfo = pathinfo($filenameMatches[1]);
			$shadow->fileShadows[] = $this->createFileShadow($pathinfo['dirname'], $pathinfo['basename']);
		}
	
		return $shadow;
	}
	
}