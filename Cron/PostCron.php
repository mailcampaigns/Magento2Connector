<?php

namespace MailCampaigns\Connector\Cron;

class PostCron {
 
 	protected $helper;
	protected $resource;
	protected $connection;
	protected $tn__mc_api_pages;
	protected $tn__mc_api_queue;
	protected $mcapi;
  
    public function __construct(
       	\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi
    ) {
        $this->resource 		= $Resource;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
    }
 
    public function execute() 
	{					
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
		//tables
		$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
		$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');
							
		// Process one page per each cron
		$sql        = "SELECT * FROM `".$this->tn__mc_api_queue."` ORDER BY id ASC LIMIT 2000";
		$rows       = $this->connection->fetchAll($sql);
		$starttime 	= time();

		// Loop through queue list
		foreach ($rows as $row)
		{			
			// Send it to MailCampaigns API
			if ($this->mcapi->PostCall($row["stream_data"]))
			{
				// Delete queued call
				$sql = "DELETE FROM `".$this->tn__mc_api_queue."` WHERE id = '".$row["id"]."'";
				$this->connection->query($sql);
			}
						
			// Detect timeout
			if ((time() - $starttime) > 55)
			{
				return;	
			}
		}
			
		return $this;
    }
}