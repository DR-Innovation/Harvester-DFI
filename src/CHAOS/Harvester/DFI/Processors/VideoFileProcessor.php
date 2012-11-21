<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class VideoFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {
	
	const DFI_VIDEO_BASE = 'http://video.dfi.dk/';

	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$urlBase = self::DFI_VIDEO_BASE;
		foreach($externalObject->FlashMovies->FlashMovieItem as $m) {
			$filenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $m->FilmUrl, $filenameMatches) === 1) {
				$pathinfo = pathinfo($filenameMatches[1]);
				$shadow->fileShadows[] = $this->createFileShadow($pathinfo['dirname'], $pathinfo['basename']);
				if(!in_array('Video', $shadow->extras['fileTypes'])) {
					$shadow->extras['fileTypes'][] = 'Video';
				}
			} else {
				trigger_error("Found a video with unknown URL.\n", E_USER_WARNING);
			}
		}
	
		return $shadow;
	}
	
}