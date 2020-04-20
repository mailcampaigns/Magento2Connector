<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Framework\Model\AbstractModel;

interface SynchronizerInterface
{
    /**
     * @param AbstractModel $model
     * @param int|null $storeId
     * @return $this
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null): self;

    /**
     * @param ApiPageInterface $page
     * @return $this
     */
    public function historicalSync(ApiPageInterface $page): self;
}
