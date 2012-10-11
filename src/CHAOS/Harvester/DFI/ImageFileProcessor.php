<?php
namespace CHAOS\Harvester\DFI;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class ImageFileProcessor extends \CHAOS\Harvester\FileProcessor {

	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
	
		$file = new FileShadow();
		$shadow->fileShadows[] = $file;
	
		return $shadow;
	}
	
}