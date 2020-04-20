<?php

namespace MailCampaigns\Magento2Connector\Setup;

use Exception;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class Uninstall implements UninstallInterface
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
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $conn = $setup->getConnection();
            $setup->startSetup();

            if ($setup->tableExists('mc_api_queue')) {
                $conn->dropTable('mc_api_queue');
            }

            if ($setup->tableExists('mc_api_pages')) {
                $conn->dropTable('mc_api_pages');
            }

            if ($setup->tableExists('mc_api_status')) {
                $conn->dropTable('mc_api_status');
            }

            $setup->endSetup();
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
