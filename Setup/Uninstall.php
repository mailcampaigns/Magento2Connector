<?php

namespace MailCampaigns\Connector\Setup;
 
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
 
class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
		
  		if ($setup->tableExists('mc_api_queue')) 
		{
            $setup->getConnection()->dropTable('mc_api_queue');
        }
		
		if ($setup->tableExists('mc_api_pages')) 
		{
            $setup->getConnection()->dropTable('mc_api_pages');
        }
 
        $setup->endSetup();
    }
}