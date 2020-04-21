<?php

namespace MailCampaigns\Magento2Connector\Api;

interface LogReaderInterface
{
    /**
     * Returns log entries for the specified datetime range (if supplied, the whole
     * log will be returned otherwise with a max of one month).
     *
     * Note: The datetime strings must be given in this format: YYYY-MM-DD HH:II:SS
     *
     * @param string|null $start The start date of the range (optional).
     * @param string|null $end The end date of the range (optional).
     * @return array
     */
    public function read(?string $start = null, ?string $end = null): array;
}
