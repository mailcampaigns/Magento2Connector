<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel;

use InvalidArgumentException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MailCampaigns\Magento2Connector\Model;

class ApiStatus extends AbstractDb
{
    /**
     * @param string $type
     * @return $this
     */
    public function removeByType(string $type): self
    {
        if (!in_array($type, Model\ApiStatus::$types)) {
            throw new InvalidArgumentException('Invalid cron type!');
        }

        $conn = $this->getConnection();

        $where = $conn->quoteInto('type = ?', $type);
        $conn->delete($this->_mainTable, $where);

        return $this;
    }

    protected function _construct()
    {
        $this->_init('mc_api_status', 'id');
    }
}
