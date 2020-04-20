<?php

namespace MailCampaigns\Magento2Connector\Api;

use MailCampaigns\Magento2Connector\Helper\InvalidQueuedCallException;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiQueue\Collection;

interface ApiQueueHelperInterface
{
    /**
     * @return int
     */
    public function getQueueSize(): int;

    /**
     * @return int
     */
    public function getErrorCount(): int;

    /**
     * @return Collection
     */
    public function getQueuedCalls(): Collection;

    /**
     * @param array $data
     * @return $this
     * @throws InvalidQueuedCallException
     */
    public function validateQueuedCallData(array $data): self;

    /**
     * @param string $streamData
     * @return ApiQueueHelperInterface
     */
    public function add(string $streamData): ApiQueueHelperInterface;

    /**
     * @param ApiQueueInterface $apiQueue
     * @return $this
     */
    public function save(ApiQueueInterface $apiQueue): self;

    /**
     * @param ApiQueueInterface $apiQueue
     * @return $this
     */
    public function removeFromQueue(ApiQueueInterface $apiQueue): self;
}
