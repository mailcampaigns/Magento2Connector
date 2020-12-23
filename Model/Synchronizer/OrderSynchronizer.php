<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use InvalidArgumentException;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\OrderSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

class OrderSynchronizer extends AbstractSynchronizer implements OrderSynchronizerInterface
{
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepo;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        CategoryRepositoryInterface $categoryRepo,
        CollectionFactory $orderCollectionFactory
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper);
        $this->categoryRepo = $categoryRepo;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null, bool $useShortTimeout = false): SynchronizerInterface
    {
        if (!$model instanceof Order) {
            throw new InvalidArgumentException('Expected Order model instance.');
        }

        $mappedOrder = $this->mapOrder($model);
        $mappedOrderItems = $this->mapOrderItems($model);
        $mappedCategories = $this->mapCategories($model);

        $apiClient = $this->apiHelper->getClient()->setStoreId($storeId);

        $apiClient->call('update_magento_orders', $mappedOrder, true, $useShortTimeout);
        $apiClient->call('update_magento_order_products', $mappedOrderItems, true, $useShortTimeout);
        $apiClient->call('update_magento_categories', $mappedCategories, true, $useShortTimeout);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        parent::historicalSync($page);

        $pageSizeCnfPath = 'mailcampaigns_historical_sync/general/import_order_amount';

        // Get the page size from configuration settings.
        $pageSize = (int)$this->scopeConfig->getValue(
            $pageSizeCnfPath,
            ScopeInterface::SCOPE_STORE,
            $page->getStoreId()
        );

        // Load orders.
        $collection = $this->orderCollectionFactory->create()
            ->setPage($page->getPage() - 1, $pageSize);

        // Filter on store(s), always include store with id 0.
        $storeIds = [0];
        if (null !== $page->getStoreId() && !in_array($page->getStoreId(), $storeIds)) {
            $storeIds[] = $page->getStoreId();
        }
        $collection->addFieldToFilter('store_id', $storeIds);

        $pageCount = $collection->getLastPageNumber();

        $mappedOrders = [];
        $mappedOrderItems = [];
        $mappedCategories = [];

        /** @var Order $order */
        foreach ($collection as $order) {
            if ($page->getCollection() === 'sales/order') {
                $mappedOrders[] = $this->mapOrder($order);
            }

            if ($page->getCollection() === 'sales/order/products') {
                $mappedOrderItems[] = $this->mapOrderItems($order);
                $mappedCategories[] = $this->mapCategories($order);
            }
        }

        $apiClient = $this->apiHelper->getClient()->setStoreId($page->getStoreId());

        if (count($mappedOrders) > 0) {
            $apiClient->call('update_magento_multiple_orders', $mappedOrders);
        }

        if (count($mappedOrderItems) > 0) {
            // Flatten the results.
            $mappedOrderItems = array_merge(...$mappedOrderItems);

            $apiClient->call('update_magento_order_products', $mappedOrderItems);
        }

        if (count($mappedCategories) > 0) {
            // Flatten the results.
            $mappedCategories = array_merge(...$mappedCategories);

            $apiClient->call('update_magento_categories', $mappedCategories);
        }

        $this->updateHistoricalSyncProgress($page, $pageCount);

        return $this;
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function mapOrder(Order $order): array
    {
        $mappedOrder = [
            'store_id' => $order->getStoreId(),
            'order_id' => $order->getEntityId(),
            'order_name' => $order->getIncrementId(),
            'order_status' => $order->getStatus(),
            'order_total' => $order->getGrandTotal(),
            'tax_amount' => $order->getTaxAmount(),
            'order_total_excl_tax' => $order->getGrandTotal() - $order->getTaxAmount(),
            'customer_id' => $order->getCustomerId(),
            'coupon_code' => $order->getCouponCode(),
            'quote_id' => $order->getQuoteId(),
            'customer_email' => $order->getCustomerEmail(),
            'firstname' => $order->getCustomerFirstname(),
            'lastname' => $order->getCustomerLastname(),
            'middlename' => $order->getCustomerMiddlename(),
            'dob' => $order->getCustomerDob(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
            'shipping_amount' => $order->getShippingAmount(),
            'shipping_amount_incl_tax' => $order->getShippingInclTax(),
            'discount' => $order->getDiscountAmount()
        ];

        $mappedAddress = $this->mapAddress($order);

        return array_merge($mappedOrder, $mappedAddress);
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function mapAddress(Order $order): array
    {
        $address = null;
        $data = [];

        // Use shipping address if available, otherwise use billing address.
        if ($order->getShippingAddress() instanceof OrderAddressInterface) {
            $address = $order->getShippingAddress();
        } elseif ($order->getBillingAddress() instanceof OrderAddressInterface) {
            $address = $order->getBillingAddress();
        }

        if ($address instanceof OrderAddressInterface) {
            $data['telephone'] = $address->getTelephone();
            $data['street'] = implode(', ', $address->getStreet());
            $data['postcode'] = $address->getPostcode();
            $data['city'] = $address->getCity();
            $data['region'] = $address->getRegion();
            $data['country_id'] = $address->getCountryId();
            $data['company'] = $address->getCompany();
        } else {
            $data['telephone'] = '';
            $data['street'] = '';
            $data['postcode'] = '';
            $data['city'] = '';
            $data['region'] = '';
            $data['country_id'] = '';
            $data['company'] = '';
        }

        return $data;
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function mapOrderItems(Order $order): array
    {
        $mappedItems = [];
        $items = $order->getItems();

        $orderData = $order->toArray(['store_id', 'customer_id']);

        /** @var Order\Item $item */
        foreach ($items as $item) {
            $itemData = $item->toArray(['order_id', 'product_id', 'qty_ordered',
                'price', 'name', 'sku']);
            $itemData['price'] = $item->getPriceInclTax();
            $itemData['categories'] = $item->getProduct() ? $item->getProduct()->getCategoryIds() : [];

            /** @codingStandardsIgnoreStart */
            $mappedItems[] = array_merge($orderData, $itemData);
            /** @codingStandardsIgnoreEnd */
        }

        return $mappedItems;
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function mapCategories(Order $order): array
    {
        $mappedCategories = [];

        $items = $order->getItems();

        /** @var Order\Item $item */
        foreach ($items as $item) {
            $categories = $item->getProduct() ? $item->getProduct()->getCategoryCollection() : [];

            /** @var Category $c */
            foreach ($categories as $c) {
                // The category is not fully loaded, so we'll have to do this first.
                /** @var Category $category */
                $category = $this->categoryRepo->get($c->getId());

                // Now we've got the category name.
                $mappedCategories[$c->getId()] = $category->getName();
            }
        }

        return $mappedCategories;
    }
}
