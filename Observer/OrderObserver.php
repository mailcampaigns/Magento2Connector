<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\OrderSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class OrderObserver extends AbstractObserver
{
    /**
     * @var OrderSynchronizerInterface
     */
    protected $orderSynchronizer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        OrderSynchronizerInterface $orderSynchronizer
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper);
        $this->orderSynchronizer = $orderSynchronizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(EventObserver $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_realtime_sync/general/import_orders';
            $storeId = $observer->getDataByKey('store');

            if (!$this->scopeConfig->getValue($cnfPath, ScopeInterface::SCOPE_STORE, $storeId)) {
                return;
            }

            /** @var Order $order */
            $order = $observer->getEvent()->getDataByKey('order');

            $this->orderSynchronizer->synchronize($order, $storeId, true);
        } catch (ApiCredentialsNotSetException $e) {
            // Just add a debug message to the filelog.
            $this->logger->addDebug($e->getMessage());
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
