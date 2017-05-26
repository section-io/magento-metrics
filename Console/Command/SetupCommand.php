<?php
/**
 * Copyright © 2017 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sectionio\Metrics\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GreetingCommand
 */
class SetupCommand extends Command
{
    /**
     * Name argument
     */
    const USERNAME_ARGUMENT = 'username';

    /**
     * Password argument
     */
    const PASSWORD_ARGUMENT = 'password';

    /**
     * Account ID argument
     */
    const ACCOUNT_ID_ARGUMENT = 'accountid';

    /**
     * Application ID argument
     */
    const APPLICATION_ID_ARGUMENT = 'applicationid';

    /** @var \Sectionio\Metrics\Model\SettingsFactory $settingsFactory */
    protected $settingsFactory;
    /** @var \Sectionio\Metrics\Model\AccountFactory $accountFactory */
    protected $accountFactory;
    /** @var \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory */
    protected $applicationFactory;
    /** @var \Sectionio\Metrics\Helper\Data $helper */
    protected $helper;
    /** @var \Sectionio\Metrics\Helper\Aperture $aperture */
    protected $aperture;
    /** @var \Magento\Framework\Message\ManagerInterface $messageManager **/
    protected $messageManager;
    /** @var \Magento\Framework\App\State $state **/
    protected $state;

    /**
     * @param \Sectionio\Metrics\Model\SettingsFactory $settingsFactory
     * @param \Sectionio\Metrics\Model\AccountFactory $accountFactory
     * @param \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory
     * @param \Sectionio\Metrics\Helper\Data $helper
     * @param \Sectionio\Metrics\Helper\Aperture $aperture
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Sectionio\Metrics\Model\SettingsFactory $settingsFactory,
        \Sectionio\Metrics\Model\AccountFactory $accountFactory,
        \Sectionio\Metrics\Model\ApplicationFactory $applicationFactory,
        \Sectionio\Metrics\Helper\Data $helper,
        \Sectionio\Metrics\Helper\Aperture $aperture,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\State $state
    ) {
        parent::__construct();
        $this->settingsFactory = $settingsFactory;
        $this->accountFactory = $accountFactory;
        $this->applicationFactory = $applicationFactory;
        $this->aperture = $aperture;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $state->setAreaCode('adminhtml');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sectionio:setup')
            ->setDescription('Greeting command')
            ->setDefinition([
                new InputArgument(
                    self::USERNAME_ARGUMENT,
                    InputArgument::REQUIRED,
                    'section.io username'
                ),
                new InputArgument(
                    self::PASSWORD_ARGUMENT,
                    InputArgument::REQUIRED,
                    'section.io password'
                ),
                new InputArgument(
                    self::ACCOUNT_ID_ARGUMENT,
                    InputArgument::REQUIRED,
                    'section.io account ID'
                ),
                new InputArgument(
                    self::APPLICATION_ID_ARGUMENT,
                    InputArgument::REQUIRED,
                    'section.io application ID'
                ),
            ]);

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument(self::USERNAME_ARGUMENT);
        $password = $input->getArgument(self::PASSWORD_ARGUMENT);
        $accountid = $input->getArgument(self::ACCOUNT_ID_ARGUMENT);
        $applicationid = $input->getArgument(self::APPLICATION_ID_ARGUMENT);

        $settingsFactory = $this->settingsFactory->create()->getCollection()->getFirstItem();

        // Load existing model if available
        $general_id = $settingsFactory->getData('general_id');
        if ($general_id) {
            $settingsFactory->load($general_id);
        }

        // delete all existing accounts in case there was a different login used previously
        $this->helper->cleanSettings();

        //Update the username & password
        $settingsFactory->setData('user_name', $username);
        $this->helper->savePassword($settingsFactory, $password);
        $settingsFactory->save();

        //Refresh the account/application list
        $this->helper->refreshApplications($this->messageManager, $accountid, $applicationid);
        $errors = $this->messageManager->getMessages()->getErrors();
        if ($errors && count($errors) > 0) {
            foreach ($errors as $error) {
                $output->writeln('Error: ' . $error->getText());
            }
            return;
        }
    }
}
