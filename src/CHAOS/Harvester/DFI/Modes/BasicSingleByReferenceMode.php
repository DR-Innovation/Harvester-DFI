<?php
namespace CHAOS\Harvester\DFI\Modes;
class BasicSingleByReferenceMode extends \CHAOS\Harvester\Modes\SingleByReferenceMode implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function execute($reference) {
		$this->_harvester->debug(__CLASS__." is executing.");
		
		$chaos = $this->_harvester->getChaosClient();
		if($chaos == null || ! $chaos instanceof \CHAOS\Portal\Client\PortalClient) {
			throw new RuntimeException(__CLASS__." expects a chaos client from the harvester.");
		}
	
		$dfi = $this->_harvester->getExternalClient('dfi');
		if($dfi == null || ! $dfi instanceof \CHAOS\Harvester\DFI\DFIClient) {
			throw new RuntimeException(__CLASS__." expects a dfi client from the harvester.");
		}
		
		if(is_numeric($reference)) {
			// This is an integer id.
			$reference = 'http://nationalfilmografien.service.dfi.dk/movie.svc/'.$reference;
		}
		
		$movieObject = $dfi->load($reference);
		$movieObject->registerXPathNamespace("dfi", "http://schemas.datacontract.org/2004/07/Netmester.DFI.RestService.Items");
		$movieObject->registerXPathNamespace("a", "http://schemas.microsoft.com/2003/10/Serialization/Arrays");
		
		print("\n");
		$this->_harvester->info("Fetching external object of %s.", $reference);
		$movieShadow = $this->_harvester->process('movie', $movieObject);
		
		timed();
		$movieShadow->commit($this->_harvester);
		timed('chaos');
	}
}