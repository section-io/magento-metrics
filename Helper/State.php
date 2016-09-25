<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class State extends AbstractHelper
{
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;
     /** @var \Magento\Framework\Encryption\EncryptorInterface $encryptor */
    protected $encryptor;
    // var \Magento\Store\Model\StoreManagerInterface $storeManager
    protected $storeManager;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->scopeConfig = $context->getScopeConfig();
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
    }

    /**
     * Save the user's password encrypted in the database
     *
     * @param string $password
     *
     */
    public function savePassword ($settingsFactory, $password) {
        $settingsFactory->setData('password', $this->encryptor->encrypt($password));
    }

    /**
     * Get store hostname
     */
    public function getHostname () {
        return parse_url($this->storeManager->getStore()->getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * Get active accountId
    */
    public function getAccountId() {
        return $this->accountFactory->create()->getCollection()
            ->addFieldToFilter('is_active', ['eq' => '1'])
            ->getFirstItem()
            ->getData('account_id');
    }

    /**
     * Get active applicationId
    */
    public function getApplicationId() {
        return $this->applicationFactory->create()->getCollection()
            ->addFieldToFilter('is_active', ['eq' => '1'])
            ->getFirstItem()
            ->getData('application_id');
    }

    /**
     * Get target environmentName
    */
    public function getEnvironmentName() {
        return 'Development';
    }

}