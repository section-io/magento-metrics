<?php
/**
 * Copyright © 2016 Section.io. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    // var \Sectionio\Metrics\Helper\Data $helper
    protected $helper;
    // var \Sectionio\Metrics\Helper\Aperture $aperture
    protected $aperture;
    // var \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    protected $resultRawFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Sectionio\Metrics\Helper\Aperture $aperture,
        \Sectionio\Metrics\Helper\Data $helper,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->aperture = $aperture;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute()
    {
        $token = $this->getRequest()->getParam('token');
        $response = $this->aperture->acmeChallenge($token, $this->helper->getHostname());

        $result = $this->resultRawFactory->create();
        $result = $result->setStatusHeader($response['http_code']);
        $result = $result->setContents($response['body_content']);
        return $result;
    }
}