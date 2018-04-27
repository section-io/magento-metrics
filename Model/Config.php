<?php
/**
 * Copyright © 2018 Sectionio. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sectionio\Metrics\Model;

/**
 * Class Config
 *
 */
 class Config extends \Magento\PageCache\Model\Config
 {

    /**
     * Returns include CMS in product purge.
     *
     * @return bool
     */
    public function includeCmsInProductPurge()
    {
        return $this->_scopeConfig->isSetFlag('system/full_page_cache/sectionio/include_cms_in_product_purge');
    }
 }
