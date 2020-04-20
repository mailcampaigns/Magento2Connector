<?php

namespace MailCampaigns\Magento2Connector\Api;

interface ApiStatusInterface
{
    /**
     * @return int Unix timestamp.
     */
    public function getDatetime();

    /**
     * @param int $ts
     * @return $this
     */
    public function setDatetime($ts);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type);
}
