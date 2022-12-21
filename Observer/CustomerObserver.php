<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Exception;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\CustomerSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class CustomerObserver extends AbstractObserver
{
    /**
     * @var CustomerSynchronizerInterface
     */
    protected $customerSynchronizer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        CustomerSynchronizerInterface $customerSynchronizer
    ) {
        parent::__construct($scopeConfig, $apiHelper);
        $this->customerSynchronizer = $customerSynchronizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(EventObserver $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_realtime_sync/general/import_customers';
            $storeId = $observer->getDataByKey('store');

            if (!$this->scopeConfig->getValue($cnfPath, ScopeInterface::SCOPE_STORE, $storeId)) {
                return;
            }

            /** @var Customer $customer */
            $customer = $observer->getEvent()->getDataByKey('customer');

            $this->customerSynchronizer->synchronize($customer, $storeId, true);
        } catch (ApiCredentialsNotSetException $e) {
        } catch (Exception $e) {
            throw $e;
        }
    }
}
