<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;

class Save extends Action
{
    /** @var PageFactory */
    protected $resultPageFactory;
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var PageCacheConfig $applicationFactory */
    protected $pageCacheConfig;

    /**
     * @param Magento\Backend\App\Action\Context $context
     * @param Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\PageCache\Model\Config $pageCacheConfig
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\PageCache\Model\Config $pageCacheConfig,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->pageCacheConfig = $pageCacheConfig;
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
    }

    /**
     * Index action
     *
     * @return void
     */
    public function execute()
    {
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
            if ($password != $settingsFactory->getData('password')) {
                $account_id = NULL;
                $this->cleanSettings();
				$update_flag = true;
                $settingsFactory->setData('password', $password);
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

                    $environment_name = 'Development';
                    $proxy_name = 'varnish';
                    $service_url = sprintf('https://aperture.section.io/api/v1/account/%d/application/%d/environment/%s/proxy/%s/configuration', $account_id, $application_id, $environment_name, $proxy_name);
                    $credentials = ($settingsFactory->getData('user_name') . ':' . $settingsFactory->getData('password'));
                    $vcl = $this->pageCacheConfig->getVclFile(\Magento\PageCache\Model\Config::VARNISH_4_CONFIGURATION_PATH);
                    $response = $this->performCurl($service_url, $credentials, 'POST', array('content' => $vcl, 'personality' => 'MagentoTurpentine'));
                    print_r($response);

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
    
    /**
     * Set active account
     *
     * @param int $account_id
     *
     * @return void
     */
    public function setDefaultAccount($account_id) {
        /** @var \Sectionio\Metrics\Model\Resource\Account\Collection $collection */
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
        /** @var \Sectionio\Metrics\Model\Resource\Account\Collection $collection */
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
        /** @var \Sectionio\Metrics\Model\Resource\Application\Collection $collection */
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
        /** @var \Sectionio\Metrics\Model\Resource\Application\Collection $collection */
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
        /** @var \Sectionio\Metrics\Model\Resource\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        // delete all existing accounts
        foreach ($collection as $model) {
            $model->delete();    
        }
    }    

    /**
     * Perform Sectionio curl call
     *
     * @param string $service_url
     * @param array() $credentials
     *
     * @return array() $results
     */
    public function performCurl ($service_url, $credentials, $method, $payload) {
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
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $json = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json)));
        }

        // if response received
        if ($curl_response = curl_exec($ch)) {
            return (json_decode ($curl_response, true));
        }
        return false;
    }
}
