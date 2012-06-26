<?php
namespace dfi\model;

use SimpleXMLElement;

class Item extends SimpleXMLElement {
	/**
	 * Fetch details regarding an item.
	 * @param DFIClient $client The DFI client to use for fetching.
	 * @param string $url Ref url of the item.
	 * @return Item The item.
	 */
	public static function fetch($client, $url) {
		timed();
		return simplexml_load_file($url, get_called_class());
		timed('dfi');
	}
}