<?php
namespace dfi;
class DFIVideoExtractor extends \CHAOSFileExtractor {
	const DFI_VIDEO_BASE = 'http://video.dfi.dk/';
	
	public $_CHAOSVideoFormatID;
	public $_CHAOSVideoDestinationID;
	
	public static $singleton;
	/**
	 * Process the DFI movieitem.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient The CHAOS client to use for the importing.
	 * @param dfi\DFIClient $dfiClient The DFI client to use for importing.
	 * @param dfi\model\Item $movieItem The DFI movie item.
	 * @param stdClass $object Representing the DKA program in the CHAOS service, of which the images should be added to.
	 * @return array An array of processed files.
	 */
	function process($chaosClient, $dfiClient, $movieItem, $object) {
		$videosProcessed = array();
		$urlBase = self::DFI_VIDEO_BASE;
		
		$movies = $movieItem->FlashMovies;
		
		printf("\tUpdating files for %u videos:\t", count($movies));
		
		echo self::PROGRESS_END_CHAR;
		foreach($movies as $m) {
			// The following line is needed as they forget to set their encoding.
			//$i->Caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			
			$miniFilenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $m->FlashMovieItem->FilmUrl, $miniFilenameMatches) === 1) {
				$pathinfo = pathinfo($miniFilenameMatches[1]);
				$response = $this->getOrCreateFile($chaosClient, $object, null, $this->_CHAOSVideoFormatID, $this->_CHAOSVideoDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
				if($response == null) {
					throw new RuntimeException("Failed to create a video file.");
				} else {
					$object->ProcessedFiles[] = $response;
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