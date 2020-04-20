<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use InvalidArgumentException;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\QuoteSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

class QuoteSynchronizer extends AbstractSynchronizer implements QuoteSynchronizerInterface
{
    /**
     * @inheritDoc
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null): SynchronizerInterface
    {
        if (!$model instanceof Quote) {
            throw new InvalidArgumentException('Expected Quote model instance.');
        }

        $mappedQuote = $this->mapQuote($model);

        $apiClient = $this->apiHelper->getClient()->setStoreId($model->getStoreId());
        $apiClient->call('update_magento_abandonded_cart_quotes', [$mappedQuote]);

        // Delete quote items (products) first before adding them again.
        $apiClient->call('delete_magento_abandonded_cart_products', [
            'quote_id' => $model->getId(),
            'store_id' => $model->getStoreId()
        ]);

        $mappedItems = $this->mapQuoteItems($model);

        // Insert quote items (products).
        if (count($mappedItems) > 0) {
            $apiClient->call('update_magento_abandonded_cart_products', $mappedItems);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        // Historical sync is not used for quotes.
        return $this;
    }

    /**
     * @param Quote $quote
     * @return array
     */
    protected function mapQuote(Quote $quote): array
    {
        $mappedQuote = $quote->toArray();

        if ($quote->getShippingAddress() instanceof Quote\Address) {
            $address = $quote->getShippingAddress();

            $mappedQuote['BaseShippingAmount'] = $address->getBaseShippingAmount();
            $mappedQuote['BaseShippingDiscountAmount'] = $address->getBaseShippingDiscountAmount();
            $mappedQuote['BaseShippingHiddenTaxAmount'] = $address->getBaseShippingTaxAmount();
            $mappedQuote['BaseShippingInclTax'] = $address->getBaseShippingInclTax();
            $mappedQuote['BaseShippingTaxAmount'] = $address->getBaseShippingTaxAmount();

            $mappedQuote['ShippingAmount'] = $address->getShippingAmount();
            $mappedQuote['ShippingDiscountAmount'] = $address->getShippingDiscountAmount();
            $mappedQuote['ShippingHiddenTaxAmount'] = $address->getBaseShippingTaxAmount();
            $mappedQuote['ShippingInclTax'] = $address->getShippingInclTax();
            $mappedQuote['ShippingTaxAmount'] = $address->getShippingTaxAmount();
        }

        // Vat calculations.
        $mappedQuote['grand_total_vat'] = $mappedQuote['grand_total']
            - $mappedQuote['subtotal'];
        $mappedQuote['base_grand_total_vat'] = $mappedQuote['base_grand_total']
            - $mappedQuote['base_subtotal'];
        $mappedQuote['grand_total_with_discount_vat'] = $mappedQuote['grand_total']
            - $mappedQuote['subtotal_with_discount'];
        $mappedQuote['base_grand_total_with_discount_vat'] = $mappedQuote['base_grand_total']
            - $mappedQuote['base_subtotal_with_discount'];

        return $mappedQuote;
    }

    /**
     * @param Quote $quote
     * @return array
     */
    protected function mapQuoteItems(Quote $quote): array
    {
        $mappedItems = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $mappedItem = $item->toArray();
            $mappedItem['image'] = $item->getProduct()->getImage();

            $mappedItems[] = $mappedItem;
        }

        return $mappedItems;
    }
}
