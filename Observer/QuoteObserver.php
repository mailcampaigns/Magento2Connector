<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\QuoteSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class QuoteObserver extends AbstractObserver
{
    /**
     * @var QuoteSynchronizerInterface
     */
    protected $quoteSynchronizer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        QuoteSynchronizerInterface $quoteSynchronizer
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper);
        $this->quoteSynchronizer = $quoteSynchronizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(EventObserver $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_realtime_sync/general/tracking_quote_session';
            $storeId = $observer->getDataByKey('store');

            if (!$this->scopeConfig->getValue($cnfPath, ScopeInterface::SCOPE_STORE, $storeId)) {
                return;
            }

            /** @var Quote $quote */
            $quote = $observer->getEvent()->getDataByKey('quote');

            $this->quoteSynchronizer->synchronize($quote, $storeId);
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
