<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Store\Model\ScopeInterface;

class ProductDeletionObserver extends AbstractObserver
{
    /**
     * @inheritDoc
     */
    public function execute(EventObserver $observer)
    {
        try {
            $cnfPath = 'mailcampaigns_realtime_sync/general/import_products';

            /** @var Product $product */
            $product = $observer->getEvent()->getDataByKey('product');

            // Only continue if product sync is enabled.
            $enabled = $this->scopeConfig->getValue(
                $cnfPath,
                ScopeInterface::SCOPE_STORE,
                $product->getStoreId()
            );

            if (!$enabled) {
                return;
            }

            $this->apiHelper->deleteProduct($product, $product->getStoreId());
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
