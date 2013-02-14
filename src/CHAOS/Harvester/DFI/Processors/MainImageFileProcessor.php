<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class MainImageFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {

	// const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';

	public function process($externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$mainImage = strval($externalObject->MainImage->SrcMini);
		$fileShadow = $this->createFileShadowFromURL($mainImage);
		if($fileShadow) {
			$shadow->fileShadows[] = $fileShadow;
			if(!in_array('Image', $shadow->extras['fileTypes'])) {
				$shadow->extras['fileTypes'][] = 'Image';
			}
		}
		return $shadow;
	}
	
}