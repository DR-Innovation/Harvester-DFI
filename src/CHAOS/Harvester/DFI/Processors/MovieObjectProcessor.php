<?php
namespace CHAOS\Harvester\DFI\Processors;
use CHAOS\Harvester\Shadows\ObjectShadow;
use CHAOS\Harvester\Shadows\SkippedObjectShadow;

class MovieObjectProcessor extends \CHAOS\Harvester\Processors\ObjectProcessor implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name, $parameter = null) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	protected function generateQuery($externalObject) {
		$legacyQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-DFI-ID:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		$newQuery = sprintf("(FolderTree:%s AND ObjectTypeID:%s AND DKA-ExternalIdentifier:%s)", $this->_folderId, $this->_objectTypeId, intval($externalObject->ID));
		return sprintf("(%s OR %s)", $legacyQuery, $newQuery);
	}
	
	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
		
		/* @var $externalObject \SimpleXMLElement */
		
		$this->_harvester->info("Processing '%s' #%d", $externalObject->Title, $externalObject->ID);

		$shadow = new ObjectShadow();
		$shadow = $this->initializeShadow($shadow);
		$shadow->query = $this->generateQuery($externalObject);
		$shadow = $this->_harvester->process('movie_metadata_dfi', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_metadata_dka', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_metadata_dka2', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_video', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_images', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_lowres_images', $externalObject, $shadow);
		$shadow = $this->_harvester->process('movie_file_main_image', $externalObject, $shadow);
		
		$shadow->commit($this->_harvester);
		
		return $shadow;
	}
	
	function skip($externalObject, $shadow = null) {
		$shadow = new SkippedObjectShadow();
		$shadow = $this->initializeShadow($shadow);
		$shadow->query = $this->generateQuery($externalObject);
		
		$shadow->commit($this->_harvester);
		
		return $shadow;
	}
}