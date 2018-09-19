<?php

namespace MailCampaigns\Connector\Cron;

use Magento\Newsletter\Model\SubscriberFactory;

class StatusCron {
 
 	protected $helper;
	protected $resource;
	protected $connection;
	protected $objectmanager;
	protected $storemanager;
	protected $customerrepository;
	protected $countryinformation;
	protected $subscriberfactory;
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
		$this->storemanager 			= $storeManager;
    }
 
    public function execute() 
	{			
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
		$stores = $this->storemanager->getStores();
		foreach ($stores as $store) 
		{					
			$this->mcapi->APIStoreID = $store->getStoreId();
			$this->mcapi->APIKey 	 = $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
			$this->mcapi->APIToken 	 = $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
			
			/*
			try
			{
				$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "lastupdate" => (time() - 3600));
				$data = json_decode($this->mcapi->Call("get_magento_optin_status", $mc_import_data), false);
			
				foreach ($data as $subscriber)
				{
					$email 	= $subscriber->email;
					$status = $subscriber->status;
					$active = $subscriber->active;
					
					$subscriberobject = $this->subscriber->loadByEmail($email);
					
					if ($active == 0)
					{
						$this->subscriberfactory->create()
								->setStatus(Subscriber::STATUS_NOT_ACTIVE)
								->setEmail($email)
								->save();
					}
					else
					if ($status == 0)
					{
						$this->subscriberfactory->create()
								->setStatus(Subscriber::STATUS_UNSUBSCRIBED)
								->setEmail($email)
								->save();
					}
					else
					if ($status == 1)
					{
						$this->subscriberfactory->create()
								->setStatus(Subscriber::STATUS_SUBSCRIBED)
								->setEmail($email)
								->save();
					}				
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
			*/
		}
			
		return $this;
    }
}