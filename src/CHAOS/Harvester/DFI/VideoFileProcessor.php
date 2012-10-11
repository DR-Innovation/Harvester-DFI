<?php
namespace CHAOS\Harvester\DFI;

use CHAOS\Harvester\Shadows\FileShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class VideoFileProcessor extends \CHAOS\Harvester\FileProcessor {
	
	const DFI_VIDEO_BASE = 'http://video.dfi.dk/';

	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
	
		assert($shadow instanceof ObjectShadow);
		
		$urlBase = self::DFI_VIDEO_BASE;
		$movies = $externalObject->FlashMovies->FlashMovieItem;
		foreach($movies as $m) {
			$miniFilenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $m->FilmUrl, $miniFilenameMatches) === 1) {
				$pathinfo = pathinfo($miniFilenameMatches[1]);
				$response = $this->getOrCreateFile($harvester, $object, null, $this->_videoFormatID, $this->_videoDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
				if($response == null) {
					throw new RuntimeException("Failed to create a video file.");
				} else {
					$videoFile = new FileShadow();
					// TODO: Implemnent this.
					// $videoFile->
					$shadow->fileShadows[] = $videoFile;
				}
			} else {
				trigger_error("\tWarning: Found an images which was didn't have a video URL. This was not imported.\n", E_USER_WARNING);
			}
		}
	
		return $shadow;
	}
	
}