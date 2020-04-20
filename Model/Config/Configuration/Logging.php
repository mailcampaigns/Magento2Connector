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

        foreach ($levels as $key => $value) {
            // Do not include levels more severe than errors in this choice list.
            if ($value > Logger::ERROR) {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => ucfirst(strtolower($key))
            ];
        }

        return $options;
    }
}
