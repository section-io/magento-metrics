<?php
/**
 * Copyright © 2016 Section.io. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    // var \Magento\Store\Model\StoreManagerInterface $storeManager
    protected $storeManager;

    // var \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    protected $resultRawFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    ) {
        $this->storeManager = $storeManager;
        $this->resultRawFactory = $resultRawFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute()
    {
        $token = $this->getRequest()->getParam('token');
        $hostname = parse_url($this->storeManager->getStore()->getBaseUrl(), PHP_URL_HOST);
        $response = $this->performAcmeRequest($token, $hostname);

        $result = $this->resultRawFactory->create();
        $result = $result->setStatusHeader($response['http_code']);
        $result = $result->setContents($response['body_content']);
        return $result;
    }

    /**
     * Perform acme request back to sectionio/aperture
     *
     * @param string $token
     * @param string $hostname
     *
     * @return array() $response
     */
    public function performAcmeRequest($token, $hostname) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('https://aperture.section.io/acme/acme-challenge/%s', $token));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [sprintf('upstream-host: %s', $hostname)]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $curl_response = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $curl_info['body_content'] = $curl_response;
        return $curl_info;
    }
}