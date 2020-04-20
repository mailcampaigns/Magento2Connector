<?php

namespace MailCampaigns\Magento2Connector\Api;

interface ApiPageInterface
{
    /**
     * @return int Unix timestamp.
     */
    public function getDatetime(): int;

    /**
     * @param int $ts
     * @return $this
     */
    public function setDatetime($ts): self;

    /**
     * @return string
     */
    public function getCollection(): string;

    /**
     * @param string $collection
     * @return $this
     */
    public function setCollection(string $collection): self;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @return int
     */
    public function getPage(): int;

    /**
     * @param int $page
     * @return $this
     */
    public function setPage(int $page): self;

    /**
     * @return int
     */
    public function getTotal(): int;

    /**
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total): self;
}
