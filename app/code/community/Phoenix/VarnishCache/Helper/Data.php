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
 * @copyright  Copyright (c) 2011-2015 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Phoenix_VarnishCache_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_VARNISH_CACHE_ENABLED  = 'varnishcache/general/enabled';
    const XML_PATH_VARNISH_CACHE_DEBUG    = 'varnishcache/general/debug';
    const ESI_CAPABLE_HEADER_SIGNATURE    = 'X-ESI-Capability';
    const XML_PATH_VARNISH_CACHE_ESI_HTTPS = 'varnishcache/general/disable_esi_https';

    /**
     * Check whether Varnish cache is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_ENABLED);
    }

    /**
     * Check whether debuging is enabled
     *
     * @return bool
     */
    public function isDebug()
    {
        if (Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_DEBUG)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether ESI form key generation is enabled over HTTPS
     *
     * @return bool
     */
    public function isHttpsEsiDisabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_ESI_HTTPS);
    }

    /**
     * Log debugging data
     *
     * @param string|array
     * @return void
     */
    public function debug($debugData)
    {
        if ($this->isDebug()) {
            Mage::log($debugData, null, 'varnish_cache.log');
        }
    }

    /**
     * @return string
     */
    public function getLicenseFilePath()
    {
        return Mage::getBaseDir() . DS . 'LICENSE_VARNISH_CACHE.lic';
    }

    /**
     * @return string
     */
    public function getLicenseCheckUrl()
    {
        return Mage::getModel('adminhtml/url')->getUrl('*/varnishCache/checkLicense');
    }

    /**
     * Get Varnish control model
     *
     * @return Phoenix_VarnishCache_Model_Control
     */
    public function getCacheControl()
    {
        return Mage::getSingleton('varnishcache/control');
    }

    /**
     * Returns if ESI can be processed by varnish
     *
     * @return bool
     */
    public function isEsiCapable()
    {
        $request = Mage::app()->getRequest();
        if ($request->isSecure() && $this->isHttpsEsiDisabled()) {
            return false;
        }

        $isEsiCapable = $request->getHeader(self::ESI_CAPABLE_HEADER_SIGNATURE);
        if ((false === is_null($isEsiCapable)) && ($isEsiCapable == 'on')) {
            return true;
        }

        return false;
    }
}
