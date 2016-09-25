<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Aperture extends AbstractHelper
{
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
     /** @var \Magento\Framework\Encryption\EncryptorInterface $encryptor */
    protected $encryptor;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->settingsFactory = $settingsFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * Generate an aperture API URL
     *
     * @param array $parameters
     *
     */
    public function generateUrl ($parameters) {
        /** @var string $url */
        $url = 'https://aperture.section.io';

        if (isset($parameters['api'])) {
            $url .= '/api/v1';
        }

        if (isset($parameters['accountId'])) {
            $url .= '/account/' . $parameters['accountId'];
        }

        if (isset($parameters['applicationId'])) {
            $url .= '/application/' . $parameters['applicationId'];
        }

        if (isset($parameters['environmentName'])) {
            $url .= '/environment/' . $parameters['environmentName'];
        }

        if (isset($parameters['proxyName'])) {
            $url .= '/proxy/' . $parameters['proxyName'];
        }

        if (isset($parameters['domain'])) {
            $url .= '/domain/' . $parameters['domain'];
        }

        if (isset($parameters['uriStem'])) {
            $url .= $parameters['uriStem'];
        }
        return $url;
    }

    /**
     * Perform Acme Challenge
     *
     * @param string $token
     * @param string $hostname
     *
     * @return array() $response
     */
    public function acmeChallenge($token, $hostname) {
        /** @var string $service_url */
        $service_url = $this->generateUrl([
            'uriStem' => sprintf('/acme/acme-challenge/%s', $token)
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $service_url);
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

    public function renewCertificate($accountId, $hostname) {
        /** @var string $service_url */
        $service_url = $this->generateUrl([
            'api'       => true,
            'accountId' => $accountId,
            'domain'    => $hostname,
            'uriStem'   => '/renewCertificate'
        ]);
        return $this->executeAuthRequest($service_url, 'POST', []);
    }

    public function updateProxyConfiguration($accountId, $applicationId, $environmentName, $proxyName, $content, $personality) {
        /** @var string $service_url */
        $service_url = $this->generateUrl([
            'api'             => true,
            'accountId'       => $accountId,
            'applicationId'   => $applicationId,
            'environmentName' => $environmentName,
            'proxyName'       => $proxyName,
            'uriStem'         => '/configuration'
        ]);
        return $this->executeAuthRequest($service_url, 'POST', [
            'content' => $content,
            'personality' => $personality
        ]);
    }

    /**
     * Perform Sectionio curl call
     *
     * @param string $service_url
     * @param array() $credentials
     * @param string $method
     * @param array() $payload
     *
     * @return array() $response
     */
    public function executeAuthRequest ($service_url, $method = 'GET', $payload = null) {

        /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
        $settingsFactory = $this->settingsFactory->create()->getCollection()->getFirstItem();
        /** @var string $credentials */
        $credentials = ($settingsFactory->getData('user_name') . ':' . $this->encryptor->decrypt($settingsFactory->getData('password')));

        // setup curl call
         $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $service_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_USERPWD, $credentials);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            /** @var string $json */
            $json = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json)]);
        }

        // construct the response object from the curl info and the body response
        $curl_response = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $curl_info['body_content'] = $curl_response;

        return $curl_info;
    }

}