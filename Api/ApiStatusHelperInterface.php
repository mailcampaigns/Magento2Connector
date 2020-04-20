<?php

namespace MailCampaigns\Magento2Connector\Api;

interface ApiStatusHelperInterface
{
    /**
     * Returns timestamp of last time given `type` was synchronized or a default
     * interval in past if no status was found.
     *
     * @param string $type Note: Types are defined in: Model\ApiStatus::TYPE_*_CRON
     * @return int
     */
    public function getSyncStart(?string $type = null): int;

    /**
     * @param string $type
     * @return $this
     */
    public function updateStatus(string $type): self;
}
