<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Exception;

class InvalidQueuedCallException extends Exception
{
    protected $message = 'Invalid queued Api call.';
}
