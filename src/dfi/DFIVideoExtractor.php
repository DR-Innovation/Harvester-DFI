<?php
namespace dfi;
use RuntimeException;
class DFIVideoExtractor extends \AChaosFileExtractor {
	const DFI_VIDEO_BASE = 'http://video.dfi.dk/';
	
	public $_videoFormatID;
	public $_videoDestinationID;
	
	public static $singleton;
	/**
	 * Process the DFI movieitem.
	 * @param \DFIIntoDKAHarvester $harvester The Chaos client to use for the importing.
	 * @param dfi\DFIClient $dfiClient The DFI client to use for importing.
	 * @param dfi\model\Item $movieItem The DFI movie item.
	 * @param stdClass $object Representing the DKA program in the Chaos service, of which the images should be added to.
	 * @return array An array of processed files.
	 */
	function process($harvester, $object, $movieItem, &$extras) {
		if($object == null) {
			throw new Exception("Cannot extract files from an empty object.");
		}
		
		$videosProcessed = array();
		$urlBase = self::DFI_VIDEO_BASE;
		
		$movies = $movieItem->FlashMovies->FlashMovieItem;
		
		printf("\tUpdating files for %u videos:\t", count($movies));
		
		echo self::PROGRESS_END_CHAR;
		foreach($movies as $m) {
			// The following line is needed as they forget to set their encoding.
			//$i->Caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			
			$miniFilenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $m->FilmUrl, $miniFilenameMatches) === 1) {
				$pathinfo = pathinfo($miniFilenameMatches[1]);
				$response = $this->getOrCreateFile($harvester, $object, null, $this->_videoFormatID, $this->_videoDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
				if($response == null) {
					throw new RuntimeException("Failed to create a video file.");
				} else {
					$videosProcessed[] = $response;
				}
			} else {
				printf("\tWarning: Found an images which was didn't have a video URL. This was not imported.\n");
			}
		}
		echo self::PROGRESS_END_CHAR;
		printf(" Done\n");
		
		return $videosProcessed;
	}
}