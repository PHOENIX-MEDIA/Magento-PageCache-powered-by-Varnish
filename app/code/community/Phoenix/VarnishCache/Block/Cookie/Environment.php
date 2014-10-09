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

class Phoenix_VarnishCache_Block_Cookie_Environment extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        // set default cache lifetime and cache tags
        $this->addData(array(
                            'cache_lifetime'    => false,
                            'cache_tags'        => array(Mage_Core_Model_Store::CACHE_TAG),
                       ));
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
        // get default store settings
        $defaultStore = Mage::app()->getDefaultStoreView();
        $defaultSettings = array(
            'storeId'       => $defaultStore->getId(),
            'currency'      => $defaultStore->getDefaultCurrencyCode(),
            'customerGroup' => Mage_Customer_Model_Group::NOT_LOGGED_IN_ID
        );

        // get current store settings
        $currentSettings = array(
            'storeId'       => Mage::app()->getStore()->getId(),
            'currency'      => Mage::app()->getStore()->getCurrentCurrencyCode(),
            'customerGroup' => Mage::getSingleton('customer/session')->getCustomerGroupId()
        );

        $cookieValue = '';
        // only set cookie value if not in default environment
        if (array_diff($defaultSettings, $currentSettings)) {
            $cookieValue = md5(serialize($currentSettings));
        }

        return $cookieValue;
    }

    /**
     * Return environment cookie name
     *
     * @return string
     */
    public function getCookieName()
    {
        return Phoenix_VarnishCache_Helper_Cache::ENVIRONMENT_COOKIE;
    }
}
