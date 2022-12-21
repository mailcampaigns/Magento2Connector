<?php

namespace MailCampaigns\Magento2Connector\Model\Config\Configuration;

use Magento\Framework\Data\OptionSourceInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class Logging implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $levels = Logger::getLevels();
        $options = [];

        if(false === empty($levels)){
            foreach ($levels as $key => $value) {
                $options[] = [
                    'value' => $value,
                    'label' => ucfirst(strtolower($key))
                ];
            }
        }

        return $options;
    }
}
