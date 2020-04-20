<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\SalesSequence\Model\Manager;

class Quote extends \Magento\Quote\Model\ResourceModel\Quote
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        Context $context,
        Snapshot $entitySnapshot,
        RelationComposite $entityRelationComposite,
        Manager $sequenceManager,
        CollectionFactory $collectionFactory,
        $connectionName = null
    ) {
        parent::__construct(
            $context,
            $entitySnapshot,
            $entityRelationComposite,
            $sequenceManager,
            $connectionName
        );

        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Retrieve quotes that are due to be synchronized.
     *
     * @param string $syncStartStr
     * @return AbstractCollection
     */
    public function getQuotesToSynchronize(string $syncStartStr): AbstractCollection
    {
        /** @var $quotes Collection */
        $quotes = $this->collectionFactory->create()
            ->addFieldToFilter(['updated_at', 'created_at'], [['gteq' => $syncStartStr],
                ['gteq' => $syncStartStr]])
            ->setOrder('updated_at', Collection::SORT_ORDER_DESC);

        return $quotes;
    }
}
