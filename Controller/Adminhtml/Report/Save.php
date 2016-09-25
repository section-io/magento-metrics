<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;

class Save extends Action
{
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var PageCache $pageCacheConfig */
    protected $pageCacheConfig;
    /** @var \Sectionio\Metrics\Helper\Data $helper */
    protected $helper;
    /** @var \Sectionio\Metrics\Helper\State $state */
    protected $state;
    /** @var \Sectionio\Metrics\Helper\Aperture $aperture */
    protected $aperture;

    /**
     * @param Magento\Backend\App\Action\Context $context
     * @param \Magento\PageCache\Model\Config $pageCacheConfig
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Sectionio\Metrics\Helper\Data $helper
     * @param \Sectionio\Metrics\Helper\State $state
     * @param \Sectionio\Metrics\Helper\Aperture $aperture
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\PageCache\Model\Config $pageCacheConfig,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Sectionio\Metrics\Helper\Data $helper,
        \Sectionio\Metrics\Helper\State $state,
        \Sectionio\Metrics\Helper\Aperture $aperture
    ) {
        parent::__construct($context);
        $this->pageCacheConfig = $pageCacheConfig;
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->helper = $helper;
        $this->aperture = $aperture;
        $this->state = $state;
    }

    /**
     * Index action
     *
     * @return void
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('vcl_btn') != null) {
            return $this->updateVarnishConfiguration();
        }
        if ($this->getRequest()->getParam('certificate_btn') != null) {
            return $this->certificateChallenge();
        }
        return $this->saveConfiguration();
    }

    public function saveConfiguration() {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
        $settingsFactory = $this->settingsFactory->create();
        /** @var int $account_id */
        $account_id = $this->getRequest()->getParam('account_id');
        /** @var boolean $update_flag */
        $update_flag = false;
        /** @var int $general_id */
        if ($general_id = $this->getRequest()->getParam('general_id')) {
            // loads model if available
            $settingsFactory->load($general_id);
        }
        // new account - set update_flag
        else {
            $update_flag = true;
        }
        // only update on change
        if ($user_name = $this->getRequest()->getParam('user_name')) {
            if ($user_name != $settingsFactory->getData('user_name')) {
                $account_id = NULL;
                $this->cleanSettings();
                $update_flag = true;
                $settingsFactory->setData('user_name', $user_name);
            }
        }
        // only update on change
        if ($password = $this->getRequest()->getParam('password')) {
            if ($password != '') {
                $account_id = NULL;
                $this->cleanSettings();
                $update_flag = true;
                $this->state->savePassword($settingsFactory, $password);
            }
        }
        // update settings
        if ($update_flag) {
            // save updated settings
            $settingsFactory->save();
            // fetch new account and application data
            return $resultRedirect->setPath('metrics/report/fetchInfo');
        }
        else {
            // if $account_id
            if ($account_id) {
                // set default account
                $this->setDefaultAccount($account_id);
                // if exists
                if ($application_id = $this->getRequest()->getParam('application_id' . $account_id)) {
                    // set default application
                    $this->setDefaultApplication($application_id);
                }
                else {
                    // clear default application
                    $this->clearDefaultApplication();
                }
            }
            else {
                // clear default account and application
                $this->clearDefaultAccount();
                $this->clearDefaultApplication();
            }
            $this->messageManager
                ->addSuccess(__('You have successfully updated the account information.'));
            return $resultRedirect->setPath('metrics/report/index');
        }
    }

    public function certificateChallenge() {
        /** @var int $account_id */
        $account_id = $this->state->getAccountId();
        /** @var string $hostname */
        $hostname = $this->state->getHostname();

        $result = $this->aperture->renewCertificate($account_id, $hostname);
        if ($result['http_code'] == 200) {
            $this->messageManager->addSuccess(__('A new certificate has been added to your domain ' . $hostname));
        } else {
            $this->messageManager->addError(__('Error validating ownership for certificate for domain ' . $hostname . '. Please check that this Magento installation is exposed to the internet on port 80.'));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('metrics/report/index');
    }

    public function updateVarnishConfiguration() {
        /** @var int $account_id */
        $account_id = $this->state->getAccountId();
        /** @var int $application_id */
        $application_id = $this->state->getApplicationId();
        /** @var string $environment_name */
        $environment_name = $this->state->getEnvironmentName();

        /** Extract the generated Varnish 4 VCL code */
        $vcl = $this->pageCacheConfig->getVclFile(\Magento\PageCache\Model\Config::VARNISH_4_CONFIGURATION_PATH);
        $result = $this->aperture->updateProxyConfiguration($account_id, $application_id, $environment_name, 'varnish', $vcl, 'MagentoTurpentine');

        if ($result['http_code'] == 200) {
            $this->messageManager->addSuccess(__('You have successfully updated varnish configuration.'));
        } else {
            $this->messageManager->addError(__('Error updating varnish configuration, upstream returned HTTP ' . $result['http_code'] . '.'));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('metrics/report/index');
    }

    /**
     * Set active account
     *
     * @param int $account_id
     *
     * @return void
     */
    public function setDefaultAccount($account_id) {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        $collection->addFieldToFilter('account_id', ['eq' => $account_id]);
        /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
        $accountFactory = $collection->getFirstItem();

        if (! $accountFactory->getData('is_active')) {
            $this->clearDefaultAccount();
            $accountFactory->setData('is_active', '1');
            $accountFactory->save();
        }
    }

    /**
     * Clear default account
     *
     * @return void
     */
    public function clearDefaultAccount() {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        $collection->addFieldToFilter('is_active', ['eq' => '1']);
        /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
        $accountFactory = $collection->getFirstItem();

        if ($accountFactory->getData('id')) {
            $accountFactory->setData('is_active', '0');
            $accountFactory->save();
        }
    }

    /**
     * Set active application
     *
     * @param int $application_id
     *
     * @return void
     */
    public function setDefaultApplication($application_id) {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Application\Collection $collection */
        $collection = $this->applicationFactory->create()->getCollection();
        $collection->addFieldToFilter('application_id', ['eq' => $application_id]);
        /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
        $applicationFactory = $collection->getFirstItem();

        if (! $applicationFactory->getData('is_active')) {
            $this->clearDefaultApplication();
            $applicationFactory->setData('is_active', '1');
            $applicationFactory->save();
        }
    }

    /**
     * Clear default application
     *
     * @return void
     */
    public function clearDefaultApplication() {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Application\Collection $collection */
        $collection = $this->applicationFactory->create()->getCollection();
        $collection->addFieldToFilter('is_active', ['eq' => '1']);
        /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
        $applicationFactory = $collection->getFirstItem();

        if ($applicationFactory->getData('id')) {
            $applicationFactory->setData('is_active', '0');
            $applicationFactory->save();
        }
    }

    /**
     * Clean current accounts (new credentials detected)
     *
     * @return void
     */
    public function cleanSettings() {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        // delete all existing accounts
        foreach ($collection as $model) {
            $model->delete();
        }
    }
}
