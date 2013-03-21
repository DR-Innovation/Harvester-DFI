<?php
namespace CHAOS\Harvester\DFI\Processors;
use CHAOS\Harvester\Shadows\ObjectShadow;

class MovieObjectProcessor extends \CHAOS\Harvester\Processors\ObjectProcessor {
	
	protected function generateQuery($externalObject) {
		$legacyQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-DFI-ID:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		$newQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-ExternalIdentifier:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		return sprintf("(%s OR %s)", $legacyQuery, $newQuery);
	}
	
	public function process(&$externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
		
		/* @var $externalObject \SimpleXMLElement */
		
		$this->_harvester->info("Processing '%s' #%d", $externalObject->Title, $externalObject->ID);

		$shadow = new ObjectShadow();
		$shadow = $this->initializeShadow($shadow);
		$shadow->extras["fileTypes"] = array();
		$shadow->query = $this->generateQuery($externalObject);
		$this->_harvester->process('movie_file_video', $externalObject, $shadow);
		$this->_harvester->process('movie_file_images', $externalObject, $shadow);
		$this->_harvester->process('movie_file_lowres_images', $externalObject, $shadow);
		$this->_harvester->process('movie_file_main_image', $externalObject, $shadow);
		if(is_array($shadow->extras["fileTypes"])) {
			$shadow->extras["fileTypes"] = implode(', ', $shadow->extras["fileTypes"]);
		}
		$this->_harvester->process('movie_metadata_dfi', $externalObject, $shadow);
		$this->_harvester->process('movie_metadata_dka', $externalObject, $shadow);
		$this->_harvester->process('movie_metadata_dka2', $externalObject, $shadow);
		
		$shadow->commit($this->_harvester);
		
		return $shadow;
	}
	
	function skip($externalObject, &$shadow = null) {
		$shadow = new ObjectShadow();
		$shadow->skipped = true;
		$shadow = $this->initializeShadow($shadow);
		$shadow->query = $this->generateQuery($externalObject);
		
		$shadow->commit($this->_harvester);
		
		return $shadow;
	}
}