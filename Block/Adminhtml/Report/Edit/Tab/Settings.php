<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Block\Adminhtml\Report\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class Settings extends Generic implements TabInterface
{
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    // var \Sectionio\Metrics\Helper\Data $helper
    protected $helper;
    // var \Sectionio\Metrics\Helper\Status $state
    protected $state;
    /** @var \Magento\PageCache\Model\Config $pageCacheConfig */
    protected $pageCacheConfig;
    /** @var \Magento\Backend\Model\UrlInterface $urlBuilder */
    protected $urlBuilder;
    protected $logger;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Sectionio\Metrics\Helper\Data $helper
     * @param \Sectionio\Metrics\Helper\Data $state
     * @param \Magento\Backend\Model\UrlInterface $urlBuilder
     * @param \Magento\PageCache\Model\Config $pageCacheConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Sectionio\Metrics\Helper\Data $helper,
        \Sectionio\Metrics\Helper\State $state,
        \Magento\Backend\Model\UrlInterface $urlBuilder,
        \Magento\PageCache\Model\Config $pageCacheConfig,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->helper = $helper;
        $this->state = $state;
        $this->urlBuilder = $urlBuilder;
        $this->pageCacheConfig = $pageCacheConfig;
        $this->logger = $context->getLogger();
        $this->setUseContainer(true);
    }

    /**
     * Init form
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('edit_defaults');
        $this->setTitle(__('Management'));
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
        $settingsFactory = $this->settingsFactory->create()->getCollection()->getFirstItem();
        /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
        $accountFactory = $this->accountFactory->create();
        /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
        $applicationFactory = $this->applicationFactory->create();
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            ['data' => [
                'id' => 'edit_settings',
                'action' => $this->getData('action'),
                'method' => 'post']
            ]
        );

        $fieldset = $form->addFieldset(
            'edit_form_fieldset_settings',
            ['legend' => __('Account and Application Selection')]
        );

        $placeholder = $fieldset->addField('label', 'hidden', [
            'value' => __('Account and Application Selection'),
        ]);


        $pageMessages = [];

        // if Magento is configured to used FPC instead of Varnish, warn the user to change it
        if ($this->pageCacheConfig->getType() != \Magento\PageCache\Model\Config::VARNISH) {
            $pageMessages[] = [ 'type' => 'error', 'message' => 'Magento is configured to use the built-in Full Page Cache not Varnish.  To use section.io\'s caching you need to change this option to "Varnish Cache" under the Full Page Cache settings in <a href="' . $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/system') . '">Stores Configuration</a> '];
        }

        // only display if account credentials have been provided
        if ($general_id = $settingsFactory->getData('general_id')) {
            $pageMessages[] = [ 'type' => 'notice', 'message' => 'Please choose your account and application.  For questions or assistance, please <a href="https://community.section.io/tags/magento" target="_blank">click here</a>.'];

            /** @var string $url */
            $url = $this->getUrl('*/*/fetchInfo');
            // button to fetch account and application data
            $fieldset->addField(
                'refresh_defaults',
                'button',
                    [
                        'value'   => __('Refresh Accounts and Applications'),
                        'title'   => __('Update available accounts and applications.'),
                        'onclick' => "setLocation('{$url}')",
                        'class'   => 'action action-secondary',
                    ]
            );
            /** @var array() $accountData */
            $accountData = $this->getAccountData($general_id);
            /** @var int $defaultAccount */
            if (! $defaultAccount = $this->getActiveAccountByGeneralId($general_id)) {
                $defaultAcount = 0;
            }
            // account_id field
            $accountIdField = $fieldset->addField(
                'account_id',
                'select',
                [
                    'name' => 'account_id',
                    'label' => __('Account'),
                    'title' => __('Account'),
                    'style' => 'width:75%',
                    'value' => $defaultAccount,
                    'required' => true,
                    'options' => $accountData
                ]
            );

            $default_map = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Form\Element\Dependence');
            $default_map->addFieldMap(
                $accountIdField->getHtmlId(),
                $accountIdField->getName()
            );

            /** @var array() $applications */
            $applications = [];

            // create application values for each account
            foreach ($accountData as $key => $value) {
                /** @var array() $applicationData */
                $applicationData = $this->getApplicationData($key);
                /** @var int $defaultApplication */
                if (! $defaultApplication = $this->getActiveApplicationByAcccountId($key)) {
                    $defaultApplication = 0;
                }
                $applications[$key] = $fieldset->addField(
                    ('application_id' . $key),
                    'select',
                    [
                        'name' => ('application_id' . $key),
                        'label' => __('Application'),
                        'title' => __('Application'),
                        'style' => 'width:75%',
                        'value' => $defaultApplication,
                        'required' => true,
                        'options' => $applicationData
                    ]
                );
                // set form dependencies
                $default_map
                    ->addFieldMap(
                        $applications[$key]->getHtmlId(),
                        $applications[$key]->getName()
                    )->addFieldDependence(
                        $applications[$key]->getName(),
                        $accountIdField->getName(),
                        $key
                );
            }
            $this->setChild('form_after', $default_map);
            // button to save default selections
            $fieldset->addField(
                'save_defaults',
                'submit',
                [
                    'value' => __('Update application'),
                    'class' => 'action-save action-primary',
                    'style' => 'width: auto;'
                ]
            );

            if ($this->state->getApplicationId() != null) {
                $managementFieldset = $form->addFieldset(
                    'field_fieldset_management',
                    ['legend' => __('Management')]
                );

                //Varnish Configuration
                $managementFieldset->addField(
                    'vcl_lbl',
                    'note',
                    [
                        'text' => 'Update Varnish Configuration with section.io. It will update and apply configuration in the <strong>Production branch</strong>.'
                    ]
                );
                $managementFieldset->addField(
                    'vcl_btn',
                    'submit',
                    [
                        'name'  => 'vcl_btn',
                        'value' => __('Update varnish configuration'),
                        'class' => 'action action-secondary',
                        'style' => 'width: auto;'
                    ]
                );

                //HTTPS one click
                $hostname = $this->state->getHostname();
                $managementFieldset->addField(
                    'certificate_lbl',
                    'note',
                    [
                        'text' => 'Complementary one click HTTPS certificate via LetsEncrypt. Domain <strong>' . $hostname . '</strong> must be exposed on the internet over port 80/HTTP.'
                    ]
                );
                $managementFieldset->addField(
                    'certificate_btn',
                    'submit',
                    [
                        'name'  => 'certificate_btn',
                        'value' => __('One click HTTPS'),
                        'class' => 'action action-secondary',
                        'style' => 'width: auto;'
                    ]
                );

                $dnsFieldset = $form->addFieldset(
                    'field_fieldset_dns',
                    ['legend' => __('Go-live steps')]
                );

                $domainUrl = 'https://aperture.section.io/account/'
                    . $this->state->getAccountId()
                    . '/application/'
                    . $this->state->getApplicationId()
                    . '/environment/Production/domains';

                $dnsFieldset->addField(
                    'dns_lbl',
                    'note',
                    [
                        'text' => '
                            Going live with section.io is as straightforward as changing a DNS record.
                            <a href="' . $domainUrl . '">Visit aperture to view instructions</a> on how to complete.
                        '
                    ]
                );

                $dnsFieldset->addField(
                    'verify_btn',
                    'submit',
                    [
                        'name'  => 'verify_btn',
                        'value' => __('Verify DNS change'),
                        'class' => 'action action-secondary',
                        'style' => 'width: auto;'
                    ]
                );

                $dnsFieldset->addField(
                    'launch_btn',
                    'button',
                    [
                        'value' => __('Launch aperture'),
                        'class' => 'action action-secondary',
                        'style' => 'width: auto;',
                        'onclick' => 'setLocation(\'' . $domainUrl . '\')'
                    ]
                );
            }
        } else {
            // no credential provided
            $pageMessages[] = [
                'type' => 'error',
                'message' => 'Unable to retrieve account and application data at this time.  Please reset your account credentials and try again.  For questions or assistance, please <a href="https://community.section.io/tags/magento" target="_blank">click here.</a>.'
            ];
        }

        $messagesHtml = '<div class="messages">';
        foreach ($pageMessages as $msg) {
            $messagesHtml .= '
                    <div class="message message-' . $msg['type'] . '">
                        ' . $msg['message'] . '
                    </div>';
        }

        $messagesHtml .= '</div>';
        $placeholder->setBeforeElementHtml($messagesHtml);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Management');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('Management');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Get account data to populate account_id form field
     *
     * @param int $general_id
     *
     * @return array()
     */
    public function getAccountData($general_id) {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        $collection->addFieldToFilter('general_id', ['eq' => $general_id]);
        /** @var array() $accountData */
        $accountData = [];
        // sets not selected option
        $accountData[0] = 'Not Selected';
        // add available accounts to array
        foreach ($collection as $accounts) {
            $accountData[$accounts->getData('account_id')] = $accounts->getData('account_name');
        }
        return $accountData;
    }

    /**
     * Get application data to populate application_id form field
     *
     * @param int $account_id
     *
     * @return array()
     */
    public function getApplicationData($account_id = NULL) {

        /** @var array() $applicationData */
        $applicationData = [];
        // sets not selected option
        $applicationData[0] = 'Not Selected';

        if ($account_id) {
            /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $accountCollection */
            $accountCollection = $this->accountFactory->create()->getCollection();
            // loop through results
            foreach ($accountCollection as $account) {
                // if application is part of current account
                if ($account->getData('account_id') == $account_id) {
                    /** @var int $id */
                    $id = $account->getData('id');
                    /** @var \Sectionio\Metrics\Model\ResourceModel\Application\Collection $collection */
                    if ($collection = $this->applicationFactory->create()->getCollection()) {
                        // build response
                        foreach ($collection as $application) {
                            // filter by account_id
                            if ($application->getData('account_id') == $id) {
                                $applicationData[$application->getData('application_id')] = $application->getData('application_name');
                            }
                        }
                    }
                }
            }
        }
        return ($applicationData);
    }

    /**
     * Get active account
     *
     * @param int $general_id
     *
     * @return string
     */
    public function getActiveAccountByGeneralId($general_id) {
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $collection */
        $collection = $this->accountFactory->create()->getCollection();
        $collection->addFieldToFilter('general_id', ['eq' => $general_id]);
        $collection->addFieldToFilter('is_active', ['eq' => '1']);
        /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
        $accountFactory = $collection->getFirstItem();

        if ($account_id = $accountFactory->getData('account_id')) {
            return $account_id;
        }
        return false;
    }

    /**
     * Get active application
     *
     * @param int $account_id
     *
     * @return string
     */
    public function getActiveApplicationByAcccountId($account_id) {
        /** @var int $id */
        $id = 0;
        /** @var \Sectionio\Metrics\Model\ResourceModel\Account\Collection $accountCollection */
        $accountCollection = $this->accountFactory->create()->getCollection();
        // translate account_id into $id */
        foreach ($accountCollection as $account) {
            // locate matching id
            if ($account->getData('account_id') == $account_id) {
                /** @var int $id */
                $id = $account->getData('id');
            }
        }
        /** @var \Sectionio\Metrics\Model\ResourceModel\Application\Collection $collection */
        $collection = $this->applicationFactory->create()->getCollection();
        // loop through collection
        foreach ($collection as $application) {
            // if accounts match
            if ($application->getData('account_id') == $id) {
                // if is active
                if ($application->getData('is_active')) {
                    return $application->getData('application_id');
                }
            }
        }
        return false;
    }
}