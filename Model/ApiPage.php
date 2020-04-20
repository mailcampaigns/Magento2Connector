<?php


namespace MailCampaigns\Magento2Connector\Model;

use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Model\ResourceModel;

class ApiPage extends AbstractModel implements ApiPageInterface
{
    /**
     * @inheritDoc
     */
    public function getDatetime(): int
    {
        return $this->_getData('datetime');
    }

    /**
     * @inheritDoc
     */
    public function setDatetime($ts): ApiPageInterface
    {
        $this->setData('datetime', (int)$ts);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCollection(): string
    {
        return $this->_getData('collection');
    }

    /**
     * @inheritDoc
     */
    public function setCollection(string $collection): ApiPageInterface
    {
        $this->setData('collection', $collection);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->_getData('store_id');
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): ApiPageInterface
    {
        $this->setData('store_id', $storeId);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPage(): int
    {
        return $this->_getData('page');
    }

    /**
     * @inheritDoc
     */
    public function setPage(int $page): ApiPageInterface
    {
        $this->setData('page', $page);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return $this->_getData('total');
    }

    /**
     * @inheritDoc
     */
    public function setTotal(int $total): ApiPageInterface
    {
        $this->setData('total', $total);
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\ApiPage::class);

        // Set defaults.
        $this
            ->setDatetime(time())
            ->setPage(1);
    }
}
