<?php

namespace MailCampaigns\Magento2Connector\Api;

use Exception;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

interface ApiClientInterface
{
    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId = null): self;

    /**
     * @param string $function Name of the function that will be called.
     * @param array $filters This array contains the payload/data to be sent.
     * @param bool $isQueueable When true, will add to queue when not successful.
     * @param bool $useShortTimeout Will use a short timout for connection(s) when set to true.
     * @return array The returned data from the Api.
     * @throws Exception|ApiCredentialsNotSetException
     */
    public function call(
        string $function,
        array $filters,
        bool $isQueueable = true,
        bool $useShortTimeout = false
    ): array;

    /**
     * Add the request data to queue (locally) to be sent to the Api later. A cron
     * job will pick this up later.
     *
     * @param array $content The Api request data (body).
     * @param string|void $function Must be supplied only if not present in $content.
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function queue(array $content, ?string $function = null): self;

    /**
     * Process a queued call.
     *
     * @param ApiQueueInterface $apiQueue
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function processQueuedCall(ApiQueueInterface $apiQueue): self;
}
