<?php
/**
 * Copyright © 2016 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Block\Adminhtml\Report\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class Credentials extends Generic implements TabInterface
{
    // var \Sectionio\Metrics\Helper\Data $helper
    protected $helper;
    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Sectionio\Metrics\Helper\Data $helper
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Sectionio\Metrics\Helper\Data $helper,
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->helper = $helper;
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->setUseContainer(true);
    }

    private function prepareLoginForm($form, $settingsFactory) {

        $fieldset = $form->addFieldset(
            'edit_form_fieldset_settings',
            ['legend' => $this->helper->getCopy('credentials:fieldset-legend', 'section.io Account Credentials')]
        );

        $placeholder = $fieldset->addField('label', 'hidden', [
            'value' => '_placeholder',
        ]);

        // credentials provided
        $copy = $this->helper->getCopy('credentials:login-page-message', 'Please enter the credentials as provided by section.io.  For questions or assistance, please <a href="https://community.section.io/tags/magento" target=\"_blank\">click here</a>.');
        $placeholder->setBeforeElementHtml('
            <div class="messages">
                <div class="message message-notice">' . $copy . '</div>
            </div>
        ');

        // general_id field (hidden)
        $fieldset->addField(
            'general_id',
            'hidden',
            [
                'name' => 'general_id',
                'value' => $settingsFactory->getData('general_id')
            ]
        );

        // user_name field
        $fieldset->addField(
            'user_name',
            'text',
            [
                'name' => 'user_name',
                'label' => __('User Name'),
                'title' => __('User Name'),
                'style' => 'width:75%',
                'required' => true,
                'value' => $settingsFactory->getData('user_name')
            ]
        );

        // password field
        $password = $fieldset->addField(
            'password',
            'password',
            [
                'name' => 'password',
                'label' => __('Password'),
                'title' => __('Password'),
                'style' => 'width:75%',
                'required' =>  ('' == $settingsFactory->getData('password') ? true : false),
                'value' => ''
            ]
        );

        // button to fetch info field
        $fieldset->addField(
            'save_info',
            'submit',
            [
                'value' => $this->helper->getCopy('credentials:save-button-value', 'Save Settings'),
                'class' => 'action-save action-secondary',
                'style' => 'width:auto'
            ]
        );
    }

    /**
     * Init form
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('edit_settings');
        $this->setTitle(__('section.io Account Credentials'));
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
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            ['data' => ['id' => 'edit_settings', 'action' => $this->getData('action'), 'method' => 'post']]
        );

        $this->prepareLoginForm($form, $settingsFactory);

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
        return __('section.io Account Credentials');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('section.io Account Credentials');
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
        /** @var int $account_id */
        if ($account_id = $accountFactory->getData('account_id')) {
            return $account_id;
        }
        return false;
    }

}
