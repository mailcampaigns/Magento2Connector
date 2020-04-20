<?php

namespace MailCampaigns\Magento2Connector\Setup;

use Exception;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(LogHelperInterface $logHelper)
    {
        $this->logger = $logHelper->getLogger();
    }

    /**
     * @inheritDoc
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $setup->startSetup();

            // todo: when upgrading to 2.2.0, convert config vars to renamed ones

            $setup->endSetup();
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
