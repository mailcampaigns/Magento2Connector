<?php

namespace MailCampaigns\Connector\Cron;

use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\Subscriber;

class StatusCron {
 
 	protected $helper;
	protected $resource;
	protected $connection;
	protected $objectmanager;
	protected $storemanager;
	protected $customerrepository;
	protected $countryinformation;
	protected $subscriberfactory;
	protected $subscriber;
	protected $mcapi;
	protected $tn__mc_api_pages;
	protected $tn__mc_api_queue;
  
    public function __construct(
       	\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcApi,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
		\Magento\Newsletter\Model\Subscriber $Subscriber,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation
    ) {
        $this->resource 				= $Resource;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcApi;
		$this->objectmanager 		= $objectManager;
		$this->customerrepository 	= $customerRepository;
		$this->countryinformation	= $countryInformation;
		$this->productrepository	= $productRepository;
		$this->subscriberfactory	= $subscriberFactory;
		$this->subscriber			= $Subscriber;
		$this->storemanager 			= $storeManager;
    }
 
    public function execute() 
	{			
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
		//tables
		$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
		$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');
		
		$stores = $this->storemanager->getStores();
		foreach ($stores as $store) 
		{					
			$this->mcapi->APIStoreID = $store->getStoreId();
			$this->mcapi->APIKey 	 = $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
			$this->mcapi->APIToken 	 = $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
			
			if (isset($this->mcapi->APIKey) && isset($this->mcapi->APIToken))
			{
				try
				{
					$mc_import_data = array("store_id" => $this->mcapi->APIStoreID);
					$jsondata = $this->mcapi->Call("get_magento_updates", $mc_import_data);
					$data = json_decode($jsondata["message"], true);
				
					// Mailinglist entries
					foreach ($data as $subscriber)
					{
						$email 	= $subscriber["E-mail"];
						$status = $subscriber["status"];
						$active = $subscriber["active"];
											
						$STATUS_SUBSCRIBED = 1;
						$STATUS_NOT_ACTIVE = 2;
						$STATUS_UNSUBSCRIBED = 3;
						$STATUS_UNCONFIRMED = 4;
						
						if ($active == 0)
						{
							$status = $STATUS_NOT_ACTIVE;
						}
						else
						if ($status == 0)
						{
							$status = $STATUS_UNSUBSCRIBED;
						}
						else
						if ($status == 1)
						{
							$status = $STATUS_SUBSCRIBED;
						}
						
						$subscriber_object = $this->subscriber->loadByEmail($email);
						$subscriber_id = $subscriber_object->getId();
						
						if ((int)$subscriber_id > 0)
						{
							// update
							$subscriber_object
								->setStatus($status)
								->setEmail($email)
								->save();
						}
						else
						{
							// add
							$this->subscriberfactory->create()
								->setStatus($status)
								->setEmail($email)
								->save();	
						}	
					}
					
					//  TO DO: Customers
					foreach ($data as $customer)
					{
						
					}
					
					// Report queue size
					$sql        = "SELECT COUNT(*) AS queue_size FROM `".$this->tn__mc_api_queue."`";
					$rows       = $this->connection->fetchAll($sql);
					foreach ($rows as $row)
					{
						$this->mcapi->DirectOrQueueCall("report_magento_queue_status", array("queue_size" => (int)$row["queue_size"], "datetime" => time()));
					}
				}
				catch (\Magento\Framework\Exception\NoSuchEntityException $e)
				{
					$this->mcapi->DebugCall($e->getMessage());
				}
				catch (Exception $e)
				{
					$this->mcapi->DebugCall($e->getMessage());
				}
			}
		}
			
		return $this;
    }
}