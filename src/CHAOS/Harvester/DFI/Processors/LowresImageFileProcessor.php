<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class LowresImageFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {

	// const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';

	public function process(&$externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$imagesRef = strval($externalObject->Images);
		if ($imagesRef == null || strlen($imagesRef) == 0) {
			return $shadow;
		}
		
		$images = $this->_harvester->getExternalClient('dfi')->load($imagesRef);
		
		$position = 0;
		foreach($images->PictureItem as $i) {
			$fileShadow = $this->createFileShadowFromURL($i->SrcThumb);
			if($fileShadow) {
				// Find the highres version of this file.
				$fileShadow->parentFileShadow = $shadow->fileShadows[$position];
				
				$shadow->fileShadows[] = $fileShadow;
				
				if(!in_array('Image', $shadow->extras['fileTypes'])) {
					$shadow->extras['fileTypes'][] = 'Image';
				}
				
				$position++;
			} else {
				trigger_error("Found an image with unknown URL: {$i->SrcThumb}\n", E_USER_WARNING);
			}
		}
	
		return $shadow;
	}
	
}