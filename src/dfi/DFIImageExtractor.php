<?php
namespace dfi;
use RuntimeException;
class DFIImageExtractor extends \ACHAOSFileExtractor {
	const DFI_IMAGE_SCANPIX_BASE_PATH = 'http://www2.scanpix.eu/';
	
	public $_imageFormatID;
	public $_lowResImageFormatID;
	public $_thumbnailImageFormatID;
	public $_imageDestinationID;
	
	public static $singleton;
	/**
	 * Process the DFI movieitem.
	 * @param \DFIIntoDKAHarvester $harvester The CHAOS client to use for the importing.
	 * @param dfi\DFIClient $dfiClient The DFI client to use for importing.
	 * @param dfi\model\Item $movieItem The DFI movie item.
	 * @param stdClass $object Representing the DKA program in the CHAOS service, of which the images should be added to.
	 * @return array An array of processed files.
	 */
	function process($harvester, $object, $movieItem, &$extras) {
		$imagesProcessed = array();
		$urlBase = self::DFI_IMAGE_SCANPIX_BASE_PATH;
		
		printf("\tUpdating the file for the main (thumbnail) image: ");
		// Update the thumbnail.
		$mainImage = $movieItem->MainImage->SrcMini;
		$filenameMatches = array();
		if(count($mainImage) > 0 && preg_match("#$urlBase(.*)#", $mainImage[0], $filenameMatches) === 1) {
			$pathinfo = pathinfo($filenameMatches[1]);
			$response = $this->getOrCreateFile($harvester, $object, null, $this->_thumbnailImageFormatID, $this->_imageDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
			
			if($response == null) {
				throw new RuntimeException("Failed to create the main image file.");
			} else {
				$object->ProcessedFiles[] = $response;
				$imagesProcessed[] = $response;
			}
		} else {
			printf("no main image detected:");
		}
		printf(" Done.\n");
		
		$imagesRef = strval($movieItem->Images);
		if ($imagesRef == null || $imagesRef === '') {
			printf("\tFound no reference to images:\tDone\n");
			return;
		}
		$images = $harvester->getExternalClient()->load($imagesRef);
		
		printf("\tUpdating files for %u images:\t", count($images->PictureItem));
		//$this->resetProgress(count($images->PictureItem));
		//$progress = 0;
		echo self::PROGRESS_END_CHAR;
		
		foreach($images->PictureItem as $i) {
			//$this->updateProgress($progress++);
			// The following line is needed as they forget to set their encoding.
			//$i->Caption = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $i->Caption );
			//echo "\$caption = $caption\n";
			//printf("\tFound an image with the caption '%s'.\n", $i->Caption);
			$miniImageID = null;
			$filenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $i->SrcMini, $filenameMatches) === 1) {
				$pathinfo = pathinfo($filenameMatches[1]);
				$response = $this->getOrCreateFile($harvester, $object, null, $this->_imageFormatID, $this->_imageDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
			
				if($response == null) {
					throw new RuntimeException("Failed to create an image file.");
				} else {
					$object->ProcessedFiles[] = $response;
					$imagesProcessed[] = $response;
					$miniImageID = $response->ID;
				}
			} else {
				printf("\tWarning: Found an images which was didn't have a scanpix/mini URL. This was not imported.\n");
			}
			
			$filenameMatches = array();
			if(preg_match("#$urlBase(.*)#", $i->SrcThumb, $filenameMatches) === 1) {
				$pathinfo = pathinfo($filenameMatches[1]);
				$response = $this->getOrCreateFile($harvester, $object, $miniImageID, $this->_lowResImageFormatID, $this->_imageDestinationID, $pathinfo['basename'], $pathinfo['basename'], $pathinfo['dirname']);
					
				if($response == null) {
					throw new RuntimeException("Failed to create an image file.");
				} else {
					$object->ProcessedFiles[] = $response;
					$imagesProcessed[] = $response;
				}
			} else {
				printf("\tWarning: Found an images which was didn't have a scanpix/mini URL. This was not imported.\n");
			}
		}
		echo self::PROGRESS_END_CHAR;
		
		printf(" Done\n");
		return $imagesProcessed;
	}
}