<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ApiPage extends AbstractDb
{
    /**
     * @param string $collection
     * @param int $storeId
     * @return $this
     */
    public function deleteByCollectionName(string $collection, int $storeId): self
    {
        $conn = $this->getConnection();

        $where = [
            $conn->quoteInto('collection = ?', $collection),
            $conn->quoteInto('store_id = ?', $storeId),
        ];

        $conn->delete($this->_mainTable, $where);

        return $this;
    }

    protected function _construct()
    {
        $this->_init('mc_api_pages', 'id');
    }
}
