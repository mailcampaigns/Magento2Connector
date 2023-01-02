<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\SubscriberSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class SubscriberObserver extends AbstractObserver
{
    /**
     * @var SubscriberSynchronizerInterface
     */
    protected $subscriberSynchronizer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        SubscriberSynchronizerInterface $subscriberSynchronizer
    ) {
        parent::__construct($scopeConfig, $apiHelper);
        $this->subscriberSynchronizer = $subscriberSynchronizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_realtime_sync/general/import_mailing_list';
            $storeId = $observer->getDataByKey('store');

            if (!$this->scopeConfig->getValue($cnfPath, ScopeInterface::SCOPE_STORE, $storeId)) {
                return;
            }

            /** @var Subscriber $subscriber */
            $subscriber = $observer->getEvent()->getDataByKey('subscriber');

            $this->subscriberSynchronizer->synchronize($subscriber, $storeId);
        } catch (ApiCredentialsNotSetException $e) {
        } catch (Exception $e) {
            throw $e;
        }
    }
}
