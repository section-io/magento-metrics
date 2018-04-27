<?php
namespace Sectionio\Metrics\Model;

use Magento\Framework\Cache\InvalidateLogger;
use Magento\Framework\App\DeploymentConfig;

class PurgeCache
{
    const HEADER_X_MAGENTO_TAGS_PATTERN = 'X-Magento-Tags-Pattern';
    const BAN_TIMEOUT_SECONDS = 10;

    /**
     * @var InvalidateLogger
     */
    private $logger;

    /**
     * Application config object
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /** @var \Sectionio\Metrics\Helper\State $helper */
    protected $state;

    /** @var \Sectionio\Metrics\Helper\Aperture $aperture */
    protected $aperture;

    /** @var \Sectionio\Metrics\Helper\Data $helper */
    protected $helper;

    /**
     * Constructor
     *
     * @param InvalidateLogger $logger
     * @param \Sectionio\Metrics\Model\Config $config
     * @param \Sectionio\Metrics\Helper\State $state
     * @param \Sectionio\Metrics\Helper\Aperture $aperture
     */
    public function __construct(
        InvalidateLogger $logger,
        \Sectionio\Metrics\Model\Config $config,
        \Sectionio\Metrics\Helper\State $state,
        \Sectionio\Metrics\Helper\Aperture $aperture
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->state = $state;
        $this->aperture = $aperture;
    }

    /**
     * Send curl purge request
     * to invalidate cache by tags pattern
     *
     * @param string $tagsPattern
     * @return bool Return true if successful; otherwise return false
     */
    public function sendPurgeRequest($tagsPattern)
    {
        $account_id = $this->state->getAccountId();
        $application_id = $this->state->getApplicationId();
        $environment_name = $this->state->getEnvironmentName();
        $proxy_name = $this->state->getProxyName();

        $banExpression = urlencode('obj.http.X-Magento-Tags ~ ' . $tagsPattern);

        // If this ban contains product tags & includeCmsInProductPurge is false, exclude any object that also has a CMS Page tag
        if (!$this->config->includeCmsInProductPurge() && strpos($tagsPattern, \Magento\Catalog\Model\Product::CACHE_TAG) !== false) {
            $banExpression .= urlencode(' && obj.http.X-Magento-Tags !~ (^|,)'.\Magento\Cms\Model\Page::CACHE_TAG.'_[0-9]+');
        }

        $uri = $this->aperture->generateUrl([
            'api' => true,
            'accountId' => $account_id,
            'applicationId' => $application_id,
            'environmentName' => $environment_name,
            'proxyName' => $proxy_name,
            'uriStem'   => '/state?async=true&banExpression=' . $banExpression
        ]);

        $info = $this->aperture->executeAuthRequest($uri, 'POST', [], self::BAN_TIMEOUT_SECONDS);
        if ($info['http_code'] != 200) {
            $this->logger->execute('Error executing purge: ' . $tagsPattern.', Error: ' . $info['body_content']);
            return false;
        }
        //$this->logger->execute(compact('server', 'tagsPattern'));
        return true;
    }
}

