<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class SynchronizeCustomer implements ObserverInterface
{
	protected $helper;
	protected $storemanager;
	protected $objectmanager;
	protected $mcapi;
	protected $countryinformation;
	protected $subscriberfactory;
	protected $subscriber;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation,
		\Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
		\Magento\Newsletter\Model\Subscriber $Subscriber
    )
	{
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->objectmanager 		= $objectManager;
		$this->countryinformation	= $countryInformation;
		$this->subscriberfactory	= $subscriberFactory;
		$this->subscriber			= $Subscriber;
    }

    public function execute(EventObserver $observer)
    {				
		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore(); 
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
  		$this->mcapi->ImportCustomers 	= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_customers',$this->mcapi->APIStoreID);
		
		if ($this->mcapi->ImportCustomers == 1)
		{
			try
			{
				// Retrieve the customer being updated from the event observer
				$customer 		= $observer->getEvent()->getCustomer();
				$customer_data 	= array();
				$address_data 	= array();
				
				$customerAddressId = $customer->getDefaultBilling();
				
				if ($customerAddressId)
				{
					try
					{
						$address 		= $this->objectmanager->create('Magento\Customer\Model\Address')->load($customerAddressId);
						$address_data 	= $address->getData();
						
						/*$country_id 		= $address_data["country_id"];
						$country 		= $this->countryinformation->getCountryInfo($country_id);
						$country_name 	= $country->getFullNameLocale();
						$address_data["country_name"] = $country_name;*/
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
				
				unset($address_data["entity_id"]);
				unset($address_data["parent_id"]);
				unset($address_data["is_active"]);
				unset($address_data["created_at"]);
				unset($address_data["updated_at"]);
							
				$customer_data[0] = array_filter(array_merge($address_data, $customer->getData()), 'is_scalar');	// ommit sub array levels
				$this->mcapi->DirectOrQueueCall("update_magento_customers", $customer_data);
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
		
		return $this;
    }
}