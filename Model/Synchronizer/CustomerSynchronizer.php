<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use InvalidArgumentException;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\CustomerSynchronizerInterface;

use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

class CustomerSynchronizer extends AbstractSynchronizer implements CustomerSynchronizerInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,

        CollectionFactory $collectionFactory
    ) {
        parent::__construct($scopeConfig, $apiHelper);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null, bool $useShortTimeout = false): SynchronizerInterface
    {
        if (!$model instanceof Customer) {
            throw new InvalidArgumentException('Expected Customer model instance.');
        }

        $this->apiHelper->updateCustomers([$this->mapCustomer($model)], $storeId, $useShortTimeout);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        parent::historicalSync($page);

        $pageSizeCnfPath = 'mailcampaigns_historical_sync/general/import_customers_amount';

        // Get the page size from configuration settings.
        $pageSize = (int)$this->scopeConfig->getValue(
            $pageSizeCnfPath,
            ScopeInterface::SCOPE_STORE,
            $page->getStoreId()
        );

        // Load customers.
        $collection = $this->collectionFactory->create()->setPage(
            $page->getPage(),
            $pageSize
        );

        $pageCount = $collection->getLastPageNumber();
        $mappedCustomers = [];

        /** @var Customer $customer */
        foreach ($collection as $customer) {
            $mappedCustomers[] = $this->mapCustomer($customer);
        }

        $this->apiHelper->updateCustomers($mappedCustomers, $page->getStoreId());

        $this->updateHistoricalSyncProgress($page, $pageCount);

        return $this;
    }

    /**
     * @param Customer $customer
     * @return array
     */
    protected function mapCustomer(Customer $customer): array
    {
        $data = array_merge($this->mapAddress($customer), $customer->getData());
        $data = array_filter($data, 'is_scalar');

        return $data;
    }

    /**
     * @param Customer $customer
     * @return array
     */
    protected function mapAddress(Customer $customer): array
    {
        $address = null;
        $data = [];

        if ($customer->getDefaultBillingAddress() instanceof Address) {
            $address = $customer->getDefaultBillingAddress();
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
}
