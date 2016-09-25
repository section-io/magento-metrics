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
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var \Sectionio\Metrics\Helper\Data $helper */
    protected $helper;
    /** @var \Psr\Log\LoggerInterface $logger */
    protected $logger;
    /** @var \Sectionio\Metrics\Helper\Aperture $aperture */
    protected $aperture;
    /** @var \Sectionio\Metrics\Helper\State $state */
    protected $state;

    /**
     * @param Magento\Backend\App\Action\Context $context
     * @param Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Sectionio\Metrics\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Sectionio\Metrics\Helper\Aperture $aperture
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Sectionio\Metrics\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger,
        \Sectionio\Metrics\Helper\Aperture $aperture,
        \Sectionio\Metrics\Helper\State $state
    ) {

        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->storeManager = $storeManager;
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->state = $state;
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
        /** @var int $general_id */
        $general_id = $settingsFactory->getData('general_id');
        /** @var string $service_url */
        $service_url = $this->helper->generateApertureUrl(['uriStem' => '/account']);
        // remove the existing accounts
        $this->cleanSettings();
        // perform account curl call
        $curl_response = $this->helper->performCurl($service_url);
        $this->logger->debug(print_r($service_url, true));
        if ($curl_response['http_code'] == 200) {
            $accountData = json_decode($curl_response['body_content'], true);

            if (!$accountData) {
                /** @var string $hostname */
                $hostname = $this->state->getHostname();
                /** @var string $service_url */
                $service_url = $this->helper->generateApertureUrl([
                    'uriStem' => '/origin?hostName=' . $hostname
                ]);
                /** @var array $origin */
                $origin = json_decode($this->helper->performCurl($service_url)['body_content'], true);
                /** @var string $origin_address */
                $origin_address = $origin['origin_detected'] ? $origin['origin'] : '192.168.35.10';
                /** @var array $payload */
                $payload = ['name' => $hostname, 'hostname' => $hostname, 'origin' => $origin_address, 'stackName' => 'varnish'];
                /** @var string $service_url */
                $service_url = $this->helper->generateApertureUrl(['uriStem' => '/account/create']);
                /** @var array $account_response */
                $account_response = $this->helper->performCurl($service_url, 'POST', $payload);
                /** @var string $account_content */
                $account_content = json_decode($account_response['body_content'], true);
                if ($account_response['http_code'] != 200) {
                    $this->messageManager
                        ->addError(__($account_content['message']));
                    return $resultRedirect->setPath('metrics/report/index');
                }
                $accountData[] = $account_content;

                $this->logger->info('Retrieving certificate started for ' . $hostname);
                $certificate_response = $this->aperture->renewCertificate($account_content['id'], $hostname);
                $this->logger->info('Retrieving certificate finished for ' . $hostname . '  with result ' . $certificate_response['http_code']);
            }

            // loop through accounts discovered
            foreach ($accountData as $account) {
                /** @var int $id */
                $id = $account['id'];
                /** @var string $account_name */
                $account_name = $account['account_name'];
                /** @var int $account_id */
                if ($account_id = $this->updateAccount($general_id, $id, $account_name)) {
                    /** @var string $service_url */
                    $service_url = $this->helper->generateApertureUrl(['accountId' => $id, 'uriStem' => '/application']);
                    // perform application curl call
                    if ($applicationData = json_decode($this->helper->performCurl($service_url)['body_content'], true)) {
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
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
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
        /** @var \Sectionio\Metrics\Model\ResourceModel\Application\Collection $collection */
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
     * Clean current accounts
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