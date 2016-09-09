<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;
    
    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory 
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory, 
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieves the section.io site metrics 
     *
     * @param int $account_id
     * @param int $application_id
     * 
     * @return array()
     */    
    public function getMetrics($account_id, $application_id) {    
    
        /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
        $settingsFactory = $this->settingsFactory->create()->getCollection()->getFirstItem();
        /** @var string $credentials */
        $credentials = ($settingsFactory->getData('user_name') . ':' . $settingsFactory->getData('password'));
        /** @var string $service_url */
        $initial_url = 'https://www.section.io/magento-section-io-plugin-config.json';
        /** @var array() $response */
        $response = array();
        /** @var int $count */
        $count = 0;

        // setup curl call
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $initial_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        // if response received
        if ($curl_response = curl_exec($ch)) {    
            if ($data = json_decode ($curl_response, true)) {
                // loop through return data
                foreach ($data as $key => $charts) {
                    if (is_array ($charts)) {
                        // loop through each chart / graph
                        foreach ($charts as $chart) {
                            // make sure return data exists
                            if (isset ($chart['url']) && isset ($chart['title'])) {
                                /** @var string $url */
                                $url = str_replace ('https://aperture.section.io/account/1/application/1/', '', $chart['url']);
                                /** @var string $service_url */
                                $service_url = ('https://aperture.section.io/account/' . $account_id . '/application/' . $application_id . '/' . $url);
                                // append time zone
                                $service_url .= '&tz=' . $this->scopeConfig->getValue('general/locale/timezone', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                                /** @var object $image */
                                if ($image = $this->performCurl($service_url, $credentials)) {
                                    // build return array
                                    $response[$count]['title'] = $chart['title'];
                                    $response[$count]['chart'] = base64_encode ($image);
                                    $response[$count]['help'] = $chart['help'];
                                    $response[$count]['docs'] = $chart['docs'];
		                            if (isset ($chart['apertureLink'])) {
		                                $response[$count]['apertureLink'] = $chart['apertureLink'];
		                            }
                                    // increment count 
                                    $count ++;
                                }
                            }
                        }
                    }
                    elseif ($key == 'intro') {
                        if (is_string ($charts)) {
                            $response['intro'] = $charts;
                        }
                    }
                }
            }
        }
        return $response;
    }
    
    /**
     * Perform Sectionio curl call
     *
     * @param string $service_url
     * @param array() $credentials
     *
     * @return array() $results
     */
    public function performCurl ($service_url, $credentials) {
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

        // if response received
        if ($curl_response = curl_exec($ch)) {    
            return $curl_response;
        }
        return false;     
    }
    
}