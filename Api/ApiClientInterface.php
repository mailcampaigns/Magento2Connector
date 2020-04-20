<?php

namespace MailCampaigns\Magento2Connector\Api;

use Exception;

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
     * @param int|null $timeout Custom request timeout in seconds (optional).
     * @return array The returned data from the Api.
     * @throws Exception
     */
    public function call(
        string $function,
        array $filters,
        bool $isQueueable = true,
        ?int $timeout = null
    ): array;

    /**
     * Add the request data to queue (locally) to be sent to the Api later. A cron
     * job will pick this up later.
     *
     * @param array $content The Api request data (body).
     * @param string|void $function Must be supplied only if not present in $content.
     * @return $this
     */
    public function queue(array $content, ?string $function = null): self;

    /**
     * Process a queued call.
     *
     * @param ApiQueueInterface $apiQueue
     * @return $this
     */
    public function processQueuedCall(ApiQueueInterface $apiQueue): self;
}
