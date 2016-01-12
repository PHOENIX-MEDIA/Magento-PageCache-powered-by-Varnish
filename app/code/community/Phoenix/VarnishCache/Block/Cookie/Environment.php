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

class Phoenix_VarnishCache_Block_Cookie_Environment extends Mage_Core_Block_Template
{
    /**
     * @var Phoenix_VarnishCache_Helper_Environment
     */
    protected $_helper;


    protected function _construct()
    {
        // set default cache lifetime and cache tags
        $this->addData(array(
                            'cache_lifetime'    => false,
                            'cache_tags'        => array(Mage_Core_Model_Store::CACHE_TAG),
                       ));
    }

    /**
     * @return Phoenix_VarnishCache_Helper_Environment
     */
    protected function _getHelper()
    {
        if (is_null($this->_helper)) {
            $this->_helper = Mage::helper('varnishcache/environment');
        }
        return $this->_helper;
    }

    /**
     * Get key pieces for caching block content
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        $cacheId = array(
            'VARNISH_COOKIE_ENVIRONMENT_',
            Mage::app()->getStore()->getId(),
            Mage::getDesign()->getPackageName(),
            Mage::getDesign()->getTheme('template'),
            Mage::getSingleton('customer/session')->getCustomerGroupId(),
            Mage::app()->getStore()->getCurrentCurrencyCode(),
            'template' => $this->getTemplate(),
            'name' => $this->getNameInLayout(),
        );
        return $cacheId;
    }

    /**
     * Return value for environment cookie if current env differs from default
     *
     * @return string
     */
    public function getEnvironmentHash()
    {
        return $this->_getHelper()->getEnvironmentHash();
    }

    /**
     * Return cookie lifetime for environment cookie
     *
     * @return int
     */
    public function getCookieLifetime()
    {
        return $this->_getHelper()->getCookieLiftime();
    }

    /**
     * Return environment cookie name
     *
     * @return string
     */
    public function getCookieName()
    {
        return $this->_getHelper()->getCookieName();
    }
}