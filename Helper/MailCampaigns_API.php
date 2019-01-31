<?php

namespace MailCampaigns\Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\ObjectManagerInterface;

class MailCampaigns_API extends \Magento\Framework\App\Helper\AbstractHelper
{	
	public $APIKey;
	public $APIToken;
	public $APIStoreID;
	public $APIWebsiteID;
	public $ImportOrdersHistory;
	public $ImportProductsHistory;
	public $ImportMailinglistHistory;
	public $ImportCustomersHistory;
	public $ImportOrderProductsHistory;
	public $ImportProductsHistoryOffset;
	public $ImportProducts;
	public $ImportMailinglist;
	public $ImportCustomers;
	public $ImportQuotes;
	public $ImportOrders;
		
	public $tn__mc_api_pages;
	public $tn__mc_api_queue;
	public $connection;
	protected $resource;

    public function __construct(
		\Magento\Framework\App\ResourceConnection $Resource
    ) {
		$this->resource 		= $Resource;
		
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
		//tables
		$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
		$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');
    }
	
	function QueueAPICall($api_function, $api_filters, $timeout = 0 /* not used */)
	{			
		$body = array();
		$body["api_key"] 	= $this->APIKey;
		$body["api_token"] 	= $this->APIToken;
		$body["method"] 	    = $api_function;
		$body["filters"]  	= $api_filters;
		$body_json 			= json_encode($body);
		
		if ($this->APIKey == "" || $this->APIToken == "" || $this->APIKey == NULL || $this->APIToken == NULL) 
			return false;
		
		return $this->connection->insert($this->tn__mc_api_queue, array(
			'stream_data'   => $body_json,
			'datetime'      => time()
		));	
	}
	
	function DirectOrQueueCall($api_function, $api_filters, $timeout = 2)
	{		
		$body = array();
		$body["api_key"] 	= $this->APIKey;
		$body["api_token"] 	= $this->APIToken;
		$body["method"] 	    = $api_function;
		$body["filters"]  	= $api_filters;
		$body_json 			= json_encode($body);

		if ($this->APIKey == "" || $this->APIToken == "")
			return false;

		try
		{
			$response = file_get_contents('https://api.mailcampaigns.nl/api/v1.1/rest',null,stream_context_create(array(
				'http' => array(
					'protocol_version' => 1.1,
					'method'           => 'POST',
					'header'           => "Content-type: application/json\r\n".
										  "Connection: close\r\n" .
										  "Content-length: " . strlen($body_json) . "\r\n",
					'content'          => $body_json,
					'timeout'		   => $timeout
				),
			)));
			
			if ($response === false)
			{
				$response = $this->connection->insert($this->tn__mc_api_queue, array(
					'stream_data'   => $body_json,
					'datetime'      => time()
				));
			}
		}
		catch (Exception $e)
		{
			$response = $this->connection->insert($this->tn__mc_api_queue, array(
				'stream_data'   => $body_json,
				'datetime'      => time()
			));
		}

		return json_decode($response, true);
	}
	
	function Call($api_function, $api_filters, $timeout = 5)
	{		
		$body = array();
		$body["api_key"] 	= $this->APIKey;
		$body["api_token"] 	= $this->APIToken;
		$body["method"] 	    = $api_function;
		$body["filters"]  	= $api_filters;
		$body_json 			= json_encode($body);
		
		if ($this->APIKey == "" || $this->APIToken == "") 
			return false;
	 
	 	try 
		{
			if ($timeout == 0)
			{
				$response = file_get_contents('https://api.mailcampaigns.nl/api/v1.1/rest',null,stream_context_create(array(
					'http' => array(
						'protocol_version' => 1.1,
						'method'           => 'POST',
						'header'           => "Content-type: application/json\r\n".
											  "Connection: close\r\n" .
											  "Content-length: " . strlen($body_json) . "\r\n",
						'content'          => $body_json,
					),
				)));
			}
			else
			if ($timeout > 0)
			{
				$response = file_get_contents('https://api.mailcampaigns.nl/api/v1.1/rest',null,stream_context_create(array(
					'http' => array(
						'protocol_version' => 1.1,
						'method'           => 'POST',
						'header'           => "Content-type: application/json\r\n".
											  "Connection: close\r\n" .
											  "Content-length: " . strlen($body_json) . "\r\n",
						'content'          => $body_json,
						'timeout'		   => $timeout
					),
				)));
			}
		} 
		catch (Exception $e) 
		{ 
		
		}
								 
		return json_decode($response, true);
	}

	function PostCall($json_data)
	{	
		try
		{	
			$response = file_get_contents('https://api.mailcampaigns.nl/api/v1.1/rest',null,stream_context_create(array(
				'http' => array(
					'protocol_version' => 1.1,
					'method'           => 'POST',
					'header'           => "Content-type: application/json\r\n".
										  "Connection: close\r\n" .
										  "Content-length: " . strlen($json_data) . "\r\n",
					'content'          => $json_data,
					'timeout'		   => 5
				),
			)));
		} 
		catch (Exception $e) 
		{ 
		
		}
									 
		return json_decode($response, true);
	}
	
	
	function DebugCall($debug_string)
	{	
		$debug_string = "".$this->APIKey." - ".date("d/m/Y H:i:s", time())." - " . $debug_string;
			
		try
		{
			$response = file_get_contents('https://api.mailcampaigns.nl/api/v1.1/debug',null,stream_context_create(array(
				'http' => array(
					'protocol_version' => 1.1,
					'method'           => 'POST',
					'header'           => "Content-type: application/json\r\n".
										  "Connection: close\r\n" .
										  "Content-length: " . strlen($debug_string) . "\r\n",
					'content'          => $debug_string,
					'timeout'		   => 5
				),
			)));
		} 
		catch (Exception $e) 
		{ 
		
		}
								 
		return json_decode($response, true);
	}
}