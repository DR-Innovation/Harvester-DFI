<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class LowresImageFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {

	const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';

	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$imagesRef = strval($externalObject->Images);
		if ($imagesRef == null || strlen($imagesRef) == 0) {
			return $shadow;
		}
		
		$images = $this->_harvester->getExternalClient('dfi')->load($imagesRef);
		
		$position = 0;
		$urlBase = self::DFI_IMAGE_SCANPIX_BASE_PATH;
		foreach($images->PictureItem as $i) {
			$filenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $i->SrcThumb, $filenameMatches) === 1) {
				$pathinfo = pathinfo($filenameMatches[1]);
				$fileShadow = $this->createFileShadow($pathinfo['dirname'], $pathinfo['basename']);
				
				// Find the highres version of this file.
				$fileShadow->parentFileShadow = $shadow->fileShadows[$position];
				
				$shadow->fileShadows[] = $fileShadow;
				$position++;
			} else {
				trigger_error("Found an image with unknown URL.\n", E_USER_WARNING);
			}
		}
	
		return $shadow;
	}
	
}