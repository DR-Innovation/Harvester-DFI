<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class ImageFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {

	// const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';

	public function process($externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$imagesRef = strval($externalObject->Images);
		if ($imagesRef == null || strlen($imagesRef) == 0) {
			return $shadow;
		}
		
		$images = $this->_harvester->getExternalClient('dfi')->load($imagesRef);
		
		// $urlBase = self::DFI_IMAGE_SCANPIX_BASE_PATH;
		foreach($images->PictureItem as $i) {
			$fileShadow = $this->createFileShadowFromURL($i->SrcMini);
			if($fileShadow) {
				$shadow->fileShadows[] = $fileShadow;
				if(!in_array('Image', $shadow->extras['fileTypes'])) {
					$shadow->extras['fileTypes'][] = 'Image';
				}
			}
		}
	
		return $shadow;
	}
	
}