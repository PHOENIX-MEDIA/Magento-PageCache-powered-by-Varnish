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

class Phoenix_VarnishCache_Helper_Environment extends Mage_Core_Helper_Abstract
{
    /**
     * Cookie name for environment cookie
     *
     * @var string
     */
    const ENVIRONMENT_COOKIE = 'PAGECACHE_ENV';


    /**
     * Return value for environment cookie if current env differs from default
     *
     * @return string
     */
    public function getEnvironmentHash()
    {
        // get default store settings
        $defaultStore = Mage::app()->getDefaultStoreView();
        $defaultLimit = Mage::getStoreConfig('catalog/frontend/list_per_page');

        $defaultSettings = array(
            'storeId'       => $defaultStore->getId(),
            'currency'      => $defaultStore->getDefaultCurrencyCode(),
            'customerGroup' => Mage_Customer_Model_Group::NOT_LOGGED_IN_ID,
            'limit'         => $defaultLimit,
        );

        // get current store settings
        $currentStore = Mage::app()->getStore();
        $currentSettings = array(
            'storeId'       => $currentStore->getId(),
            'currency'      => $currentStore->getCurrentCurrencyCode(),
            'customerGroup' => Mage::getSingleton('customer/session')->getCustomerGroupId(),
            'limit'         => $this->_getCurrentLimit($defaultLimit),
        );

        $cookieValue = '';
        // only set cookie value if not in default environment
        if (array_diff($defaultSettings, $currentSettings)) {
            $cookieValue = md5(serialize($currentSettings));
        }

        return $cookieValue;
    }

    /**
     * Return cookie lifetime in ms
     *
     * @return int
     */
    public function getCookieLiftime()
    {
        return (intval($this->_getCookie()->getLifetime() * 1000));
    }

    /**
     * Sets environment cookie
     *
     * @return void
     */
    public function setEnvironmentCookie()
    {
        if ($envHash = $this->getEnvironmentHash()) {
            $this->_getCookie()->set(
                self::ENVIRONMENT_COOKIE,
                $envHash,
                $this->getCookieLiftime()
            );
        } else {
            $this->_getCookie()->delete(self::ENVIRONMENT_COOKIE);
        }
    }

    /**
     * Return the name of the cookie
     *
     * @return string
     */
    public function getCookieName()
    {
        return self::ENVIRONMENT_COOKIE;
    }

    /**
     * @return Mage_Core_Model_Cookie
     */
    protected function _getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Return current pagination limit
     *
     * @param int $defaultLimit
     *
     * @return int
     */
    protected function _getCurrentLimit($defaultLimit)
    {
        $currentLimit = Mage::getSingleton('catalog/session')->getLimitPage();
        if (is_null($currentLimit) === false) {
            return $currentLimit;
        }

        return $defaultLimit;
    }
}
