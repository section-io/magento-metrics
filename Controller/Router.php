<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Controller;
/**
 * UrlRewrite Controller Router
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Router implements \Magento\Framework\App\RouterInterface
{
    /** var \Magento\Framework\App\ActionFactory */
    protected $actionFactory;
    /** @var \Magento\Framework\UrlInterface */
    protected $url;
    /** @var \Magento\Framework\App\ResponseInterface */
    protected $response;

    protected $logger;
    /**
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\Framework\App\ResponseInterface $response
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\App\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->actionFactory = $actionFactory;
        $this->url = $url;
        $this->response = $response;
        $this->logger = $logger;
    }
    /**
     * Match corresponding URL Rewrite and modify request
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        $this->response->setRedirect('http://www.google.com', 301);
        $request->setDispatched(true);
        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}