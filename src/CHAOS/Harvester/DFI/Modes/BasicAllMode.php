<?php
namespace CHAOS\Harvester\DFI\Modes;
class BasicAllMode extends \CHAOS\Harvester\Modes\AllMode implements \CHAOS\Harvester\Loadable {
	
	public function execute() {
		$this->_harvester->debug(__CLASS__." is executing.");
		
		$chaos = $this->_harvester->getChaosClient();
		if($chaos == null || ! $chaos instanceof \CHAOS\Portal\Client\PortalClient) {
			throw new RuntimeException(__CLASS__." expects a chaos client from the harvester.");
		}
		
		$dfi = $this->_harvester->getExternalClient('dfi');
		if($dfi == null || ! $dfi instanceof \CHAOS\Harvester\DFI\DFIClient) {
			throw new RuntimeException(__CLASS__." expects a dfi client from the harvester.");
		}
		
		$m = 1;
		
		$this->_harvester->info("Fetching references to all movies.");
		$movies = $dfi->fetchMultipleMovies();
		foreach($movies as $movie) {
			printf("[#%u] ", $m++);
			$this->_harvester->info("Fetching external object of '%s' #%s.", $movie->Name, $movie->ID);
			
			// Needed for making the error reporting not through warnings.
			$movieObject = null;
			$movieShadow = null;
			try {
				$movieObject = $dfi->load($movie->Ref);
				$movieObject->registerXPathNamespace("dfi", "http://schemas.datacontract.org/2004/07/Netmester.DFI.RestService.Items");
				$movieObject->registerXPathNamespace("a", "http://schemas.microsoft.com/2003/10/Serialization/Arrays");
				
				$movieShadow = $this->_harvester->process('movie', $movieObject);
			} catch(\Exception $e) {
				$this->_harvester->registerProcessingException($e, $movieObject, $movieShadow);
			}
			print("\n");
		}
	}
}