<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

interface SynchronizerInterface
{
    /**
     * @param AbstractModel $model
     * @param int|null $storeId
     * @param bool $useShortTimeout Will use a short timout for connection(s) when set to true.
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null, bool $useShortTimeout = false): self;

    /**
     * @param ApiPageInterface $page
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function historicalSync(ApiPageInterface $page): self;
}
