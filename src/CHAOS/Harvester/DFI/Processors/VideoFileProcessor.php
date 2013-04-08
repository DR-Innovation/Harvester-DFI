<?php
namespace CHAOS\Harvester\DFI\Processors;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class VideoFileProcessor extends \CHAOS\Harvester\Processors\FileProcessor {
	
	// const DFI_VIDEO_BASE = 'http://video.dfi.dk/';

	public function process(&$externalObject, &$shadow = null) {
		// Precondition
		assert($shadow instanceof ObjectShadow);
		
		foreach($externalObject->FlashMovies->FlashMovieItem as $m) {
			$fileShadow = $this->createFileShadowFromURL($m->FilmUrl);
			if($fileShadow) {
				$shadow->fileShadows[] = $fileShadow;
				if(!in_array('Video', $shadow->extras['fileTypes'])) {
					$shadow->extras['fileTypes'][] = 'Video';
				}
			} else {
				trigger_error("Found a video with unknown URL: {$m->FilmUrl}\n", E_USER_WARNING);
			}
		}
	
		return $shadow;
	}
	
}