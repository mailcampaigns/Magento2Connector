<?php

namespace MailCampaigns\Magento2Connector\Api;

interface LogReaderInterface
{
    /**
     * Returns log entries for the specified date range (if supplied, the whole
     * log will be returned otherwise).
     *
     * Note: The date strings must be given in this format: YYYY-MM-DD
     *
     * @param string|null $start The start date of the range (optional).
     * @param string|null $end The end date of the range (optional).
     * @return array
     */
    public function read(?string $start = null, ?string $end = null): array;
}
