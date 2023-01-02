<?php

namespace MailCampaigns\Magento2Connector\Setup;

use Exception;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class Uninstall implements UninstallInterface
{
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
            throw $e;
        }
    }
}
