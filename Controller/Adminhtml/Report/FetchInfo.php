<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;

class FetchInfo extends Action
{
    /** @var PageFactory */
    protected $resultPageFactory;
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;

    /**
     * @param Magento\Backend\App\Action\Context $context
     * @param Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory 
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
    }

    /**
     * Execute account and application api calls
     *
     * @return void
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
        $settingsFactory = $this->settingsFactory->create()->getCollection()->getFirstItem();
        /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
        $accountFactory = $this->accountFactory->create();
        /** @var string $credentials */
        $credentials = ($settingsFactory->getData('user_name') . ':' . $settingsFactory->getData('password'));
        /** @var int $general_id */
        $general_id = $settingsFactory->getData('general_id');
        /** @var string $service_url */
        $service_url = 'https://aperture.section.io/api/v1/account';
        // perform account curl call
        if ($accountData = $this->performCurl($service_url, $credentials)) {
            // loop through accounts discovered
            foreach ($accountData as $account) {
                /** @var int $id */
                $id = $account['id'];
                /** @var string $account_name */
                $account_name = $account['account_name'];
                /** @var int $account_id */
                if ($account_id = $this->updateAccount($general_id, $id, $account_name)) {    
                    /** @var string $service_url */
                    $service_url = 'https://aperture.section.io/api/v1/account/' . $id . '/application';
                    // perform application curl call
                    if ($applicationData = $this->performCurl($service_url, $credentials)) {
                        // loop through available applications
                        foreach ($applicationData as $application) {
                            /** @var int $application_id */
                            $application_id = $application['id'];
                            /** @var string $application_name */
                            $application_name = $application['application_name'];
                            // record application results
                            $this->updateApplication($account_id, $application_id, $application_name);
                        }
                    }
                }        
            }
            // successful results
            $this->messageManager
                ->addSuccess(__('You have successfully updated account and application data.  Please select your default account and application and save the selections.  Once complete, you will be able to view the Section.io site metrics.'));        
            return $resultRedirect->setPath('metrics/report/index');    
        }
        // no account data
        else {
            $this->messageManager
                ->addError(__('No accounts discovered.  Please check your credentials and try again.'));        
            return $resultRedirect->setPath('metrics/report/index');    
        }
    }
    
    /**
     * Process account result
     *
     * @param int $general_id
     * @param int $id
     * @param string $account_name
     *
     * @return int
     */
    public function updateAccount ($general_id, $id, $account_name) {
        /** @var \Sectionio\Metrics\Model\AccountFactory $model */
        $model = $this->accountFactory->create();
        /** @var \Sectionio\Metrics\Model\Resource\Account\Collection $collection */
        $collection = $model->getCollection();
        /** @var boolean $flag */
        $flag = true;
        // loop through $collection
        foreach ($collection as $account) {
            // if account already exists
            if ($account->getData('account_id') == $id) {
                $flag = false;
                break;
            }
        }
        // new account discovered
        if ($flag) {
            // set data
            $model->setData('general_id', $general_id);
            $model->setData('account_id', $id);
            $model->setData('account_name', $account_name);
            $model->setData('is_active', '0');
            // save account
            $model->save();    
            // return unique id
            return ($model->getData('id'));
        }
        // no results
        return false;
    }
    
    /**
     * Process application result
     *
     * @param int $account_id
     * @param int $id
     * @param string $application_name
     *
     * @return void
     */
    public function updateApplication ($account_id, $id, $application_name) {
        /** @var \Sectionio\Metrics\Model\ApplicationFactory $model */
        $model = $this->applicationFactory->create();
        /** @var \Sectionio\Metrics\Model\Resource\Application\Collection $collection */
        $collection = $model->getCollection();
        /** @var boolean $flag */
        $flag = true;
        // loop through collection
        foreach ($collection as $application) {
            // if application already exists
            if ($application->getData('application_id') == $id) {
                $flag = false;
                break;
            }
        }
        // new application discovered		
        if ($flag) {
            // set data
            $model->setData('account_id', $account_id);
            $model->setData('application_id', $id);
            $model->setData('application_name', $application_name);
            $model->setData('is_active', '0');
            // save application
            $model->save();    
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
            return (json_decode ($curl_response, true));
        }
        return false;     
    }
}