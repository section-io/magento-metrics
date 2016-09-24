<?php
/**
 * Copyright © 2016 Section.io. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller;

/**
 * Acme Router
 */
class Router implements \Magento\Framework\App\RouterInterface
{
    /** var \Magento\Framework\App\ActionFactory */
    protected $actionFactory;
    /** @var \Magento\Framework\App\ResponseInterface */
    protected $response;
    /** @var \Psr\Log\LoggerInterface */
    protected $logger;
    /**
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\App\ResponseInterface $response
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\App\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->actionFactory = $actionFactory;
        $this->response = $response;
        $this->logger = $logger;
    }
    /**
     * Match well known url for acme challege
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        /** @var string $urlPath */
        $urlPath = $request->getPathInfo();

        //It must match /.well-known\/acme-challenge\*
        if (!preg_match('/^\/.well-known\/acme-challenge\/([\w]+)$/', $urlPath, $matches)) {
            return null;
        }

        /** @var string $token */
        $token = $matches[1];

        $request->setParam('token', $token);
        $request->setModuleName('acmechallenge');
        $request->setControllerName('index');
        $request->setActionName('index');

        return $this->actionFactory->create('Magento\Framework\App\Action\Forward');
    }
}