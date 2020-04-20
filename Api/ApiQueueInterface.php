<?php

namespace MailCampaigns\Magento2Connector\Api;

interface ApiQueueInterface
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
    public function getStreamData();

    /**
     * @param string $streamData
     * @return $this
     */
    public function setStreamData($streamData);

    /**
     * @return bool
     */
    public function hasError();

    /**
     * @param bool $hasError
     * @return $this
     */
    public function setHasError($hasError);
}
