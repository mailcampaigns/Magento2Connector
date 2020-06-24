<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

interface SynchronizerInterface
{
    /**
     * @param AbstractModel $model
     * @param int|null $storeId
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null): self;

    /**
     * @param ApiPageInterface $page
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function historicalSync(ApiPageInterface $page): self;
}
