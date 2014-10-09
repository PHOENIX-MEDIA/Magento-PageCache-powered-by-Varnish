<?php
/**
 * PageCache powered by Varnish
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@phoenix-media.eu so we can send you a copy immediately.
 * 
 * @category   Phoenix
 * @package    Phoenix_VarnishCache
 * @copyright  Copyright (c) 2011-2014 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Phoenix_VarnishCache_Helper_Control_Catalog_Product extends Phoenix_VarnishCache_Helper_Data
{
    const XML_PATH_VARNISH_CACHE_PURGE  = 'varnishcache/general/purge_catalog_product';

    /**
     * Returns true if Varnish cache is enabled and product should be purged on save
     *
     * @return boolean
     */
    public function canPurge()
    {
        return $this->isEnabled() && $this->isPurge();
    }

    /**
     * Returns true if CMS page should be purged on save
     *
     * @return boolean
     */
    public function isPurge()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_PURGE);
    }
}
