<?php
namespace CHAOS\Harvester\DFI\Processors;
use CHAOS\Harvester\Shadows\ObjectShadow;

class MovieObjectProcessor extends \CHAOS\Harvester\Processors\ObjectProcessor implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
		$this->_harvester->info("Processing '%s' #%d", $externalObject->Title, $externalObject->ID);
		
		
		$legacyQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-DFI-ID:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		$newQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-ExternalIdentifier:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		
		$shadow = new ObjectShadow();
		$shadow->query = sprintf("(%s OR %s)", $legacyQuery, $newQuery);
		$shadow->objectTypeId = $this->_objectTypeId;
		$shadow->folderId = $this->_folderId;
		$shadow = $this->_harvester->process('movie_metadata_dfi', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_metadata_dka', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_metadata_dka2', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_video', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_images', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_lowres_images', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_main_image', $externalObject, $shadow);
		
		// TODO: Implement the file relations.
		/*
		if($this->_harvester->hasOption('debug')) {
			var_dump($shadow);
		}
		*/
		return $shadow;
	}
}