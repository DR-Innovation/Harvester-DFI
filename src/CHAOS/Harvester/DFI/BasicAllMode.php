<?php
namespace CHAOS\Harvester\DFI;
class BasicAllMode extends \CHAOS\Harvester\AllMode implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
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
		
		$this->_harvester->info("Fetching references to all movies.");
		$movies = $dfi->fetchMultipleMovies();
		foreach($movies as $movie) {
			print("\n");
			$this->_harvester->info("Fetching external object of '%s' #%s.", $movie->Name, $movie->ID);
			$movieObject = $dfi->load($movie->Ref);
			$this->_harvester->process('movie', $movieObject);
		}
	}
}