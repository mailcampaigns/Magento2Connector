<?php

namespace MailCampaigns\Magento2Connector\Observer\Configuration;

use Exception;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;

class ProductHistoricalSyncConfigObserver extends AbstractHistoricalSyncConfigObserver
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        CollectionFactory $collectionFactory,
        Message\ManagerInterface $messageManager
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper, $messageManager);
        $this->collectionFactory = $collectionFactory;
    }

    public function execute(EventObserver $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_historical_sync/general/import_products_history';
            $storeId = $observer->getDataByKey('store');

            // Only continue when this feature is enabled.
            if (!$this->scopeConfig->getValue($cnfPath, ScopeInterface::SCOPE_STORE, $storeId)) {
                return;
            }

            $collectionName = 'catalog/product';
            $collection = $this->collectionFactory->create();
            $pageSizeCnfPath = 'mailcampaigns_historical_sync/general/import_products_amount';

            // Delete page records locally and send command to drop tables on remote. Then
            // send the initial progress to the Api.
            $this->apiHelper->initHistoricalSync(
                $collectionName,
                $storeId,
                $collection,
                $pageSizeCnfPath
            );

            $this->messageManager->addNoticeMessage('De bulk import van product gegevens wordt gestart.');
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
