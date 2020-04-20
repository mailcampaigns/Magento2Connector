<?php

namespace MailCampaigns\Magento2Connector\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;

class ModuleVersion extends Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'MailCampaigns_Magento2Connector::system/config/module_version.phtml';

    /**
     * @var ApiHelperInterface
     */
    protected $apiHelper;

    /**
     * @param Context $context
     * @param ApiHelperInterface $apiHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ApiHelperInterface $apiHelper,
        array $data = []
    ) {
        $this->apiHelper = $apiHelper;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate collect button html
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return $this->apiHelper->getModuleVersion();
    }
}
