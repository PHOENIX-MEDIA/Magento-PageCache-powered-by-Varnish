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

class Phoenix_VarnishCache_Helper_Cache extends Mage_Core_Helper_Abstract
{
    const XML_PATH_VARNISH_CACHE_DISABLE_CACHING      = 'varnishcache/general/disable_caching';
    const XML_PATH_VARNISH_CACHE_DISABLE_CACHING_VARS = 'varnishcache/general/disable_caching_vars';
    const XML_PATH_VARNISH_CACHE_DISABLE_ROUTES       = 'varnishcache/general/disable_routes';
    const XML_PATH_VARNISH_CACHE_TTL                  = 'varnishcache/general/ttl';
    const XML_PATH_VARNISH_CACHE_ROUTES_TTL           = 'varnishcache/general/routes_ttl';
    const XML_PATH_VARNISH_CACHE_ENABLE_CACHING_HTTPS = 'varnishcache/general/enable_caching_https';

    const REGISTRY_VAR_VARNISH_CACHE_CONTROL_HEADERS_SET_FLAG = '_varnish_cache_control_headers_set_flag';

    /**
     * Cookie name for disabling external caching
     *
     * @var string
     */
    const NO_CACHE_COOKIE = 'EXTERNAL_NO_CACHE';

    /**
     * Cookie name for disabling external caching for messages
     *
     * @var string
     */
    const MESSAGE_NO_CACHE_COOKIE = 'MESSAGE_NO_CACHE';

    /**
     * Cookie name for environment cookie
     *
     * @var string
     */
    const ENVIRONMENT_COOKIE = 'PAGECACHE_ENV';

    /**
     * Header for debug flag
     *
     * @var string
     * @return void
     */
    const DEBUG_HEADER = 'X-Cache-Debug: 1';


    /**
     * Get Cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public static function getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Set appropriate cache control headers
     *
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    public function setCacheControlHeaders()
    {
        if (Mage::registry(self::REGISTRY_VAR_VARNISH_CACHE_CONTROL_HEADERS_SET_FLAG)) {
            return $this;
        } else {
            Mage::register(self::REGISTRY_VAR_VARNISH_CACHE_CONTROL_HEADERS_SET_FLAG, 1);
        }

        // set debug header
        if (Mage::helper('varnishcache')->isDebug()) {
            $this->setDebugHeader();
        }

        // disable caching of secure pages
        if (
            Mage::app()->getStore()->isCurrentlySecure() &&
            !Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_ENABLE_CACHING_HTTPS)
        ) {
            return $this->setNoCacheHeader();
        }

        $request = Mage::app()->getRequest();

        // check for disable caching vars
        if ($disableCachingVars = trim(Mage::getStoreConfig(self::XML_PATH_VARNISH_CACHE_DISABLE_CACHING_VARS))) {
            foreach (explode(',', $disableCachingVars) as $param) {
                if ($request->getParam(trim($param))) {
                    $this->setNoCacheHeader();
                    return $this->setNoCacheCookie();
                }
            }
        }

        // renew no-cache cookie
        $this->setNoCacheCookie(true);

        /**
         * disable page caching
         */

        // disable page caching for POSTs
        if ($request->isPost()) {
            return $this->setNoCacheHeader();
        }

        // disable page caching for due to HTTP status codes
        if (!in_array(Mage::app()->getResponse()->getHttpResponseCode(), array(200, 301, 404))) {
            return $this->setNoCacheHeader();
        }

        // disable page caching for certain GET parameters
        $noCacheGetParams = array(
            'no_cache',     // explicit
            '___store'      // language switch
        );
        foreach($noCacheGetParams as $param) {
            if($request->getParam($param)) {
                return $this->setNoCacheHeader();
            }
        }

        // disable page caching because of configuration
        if (Mage::getStoreConfigFlag(self::XML_PATH_VARNISH_CACHE_DISABLE_CACHING)) {
            return $this->setNoCacheHeader();
        }


        /**
         * Check for ruleset depending on request path
         *
         * see: Mage_Core_Controller_Varien_Action::getFullActionName()
         */
        $fullActionName = $request->getRequestedRouteName().'_'.
            $request->getRequestedControllerName().'_'.
            $request->getRequestedActionName();

        // check caching blacklist for request routes
        $disableRoutes = explode("\n", trim(Mage::getStoreConfig(self::XML_PATH_VARNISH_CACHE_DISABLE_ROUTES)));
        foreach ($disableRoutes as $route) {
            $route = trim($route);
            // if route is found at first position we have a hit
            if (!empty($route) && strpos($fullActionName, $route) === 0) {
                return $this->setNoCacheHeader();
            }
        }

        // set TTL header
        $regexp = null;
        $value = null;
        $routesTtl = unserialize(Mage::getStoreConfig(self::XML_PATH_VARNISH_CACHE_ROUTES_TTL));
        if (is_array($routesTtl)) {
            foreach ($routesTtl as $routeTtl) {
                extract($routeTtl, EXTR_OVERWRITE);
                $regexp = trim($regexp);
                if (!empty($regexp) && strpos($fullActionName, $regexp) === 0) {
                    break;
                }
                $value = null;
            }
        }
        if (!isset($value)) {
            $value = Mage::getStoreConfig(self::XML_PATH_VARNISH_CACHE_TTL);
        }
        $this->setTtlHeader(intval($value));

        return $this;
    }

    /**
     * Check for a NO_CACHE cookie and if found force a TTL=0 for this
     * page.
     *
     *  @return void
     */
    public static function sanitizeCacheControlHeader()
    {
        $cookie = self::getCookie();
        if ($cookie->get(self::NO_CACHE_COOKIE) || $cookie->get(self::MESSAGE_NO_CACHE_COOKIE)) {
            self::setNoCacheHeader();
        }
    }

    /**
     * Disable caching of this and all future request for this visitor
     *
     * @param bool
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    public function setNoCacheCookie($renewOnly = false)
    {
        if ($this->getCookie()->get(self::NO_CACHE_COOKIE)) {
            $this->getCookie()->renew(self::NO_CACHE_COOKIE);
        } elseif (!$renewOnly) {
            $this->getCookie()->set(self::NO_CACHE_COOKIE, 1);
        }
        return $this;
    }

    /**
     * Disable caching for this request
     *
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    public static function setNoCacheHeader()
    {
        return self::setTtlHeader(0);
    }

    /**
     * Set debug flag in HTTP header
     *
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    public function setDebugHeader()
    {
        $response = Mage::app()->getResponse();
        if (!$response->canSendHeaders()) {
            return;
        }
        $el = explode(':', self::DEBUG_HEADER, 2);
        $response->setHeader($el[0], $el[1], true);
        return $this;
    }

    /**
     * Set TTL HTTP header for cache
     *
     * For mod_expires it is important to have "Expires" header. However for
     * Varnish it is easier to deal with "Cache-Control: s-maxage=xx" as it
     * is relative to its system time and not depending on timezone settings.
     *
     * Magento normaly doesn't set any Cache-Control or Expires headers. If they
     * appear the are set by PHP's setcookie() function.
     *
     * @param int   Time to life in seconds. Value greater than 0 means "cacheable".
     * @return void
     */
    public static function setTtlHeader($ttl)
    {
        $maxAge = 's-maxage=' . (($ttl < 0) ? 0 : $ttl);
        $cacheControlValue = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, '.$maxAge;

        // retrieve existing "Cache-Control" header
        $response = Mage::app()->getResponse();
        if (!$response->canSendHeaders()) {
            return;
        }
        $headers = $response->getHeaders();

        foreach ($headers as $key => $header) {
            if ('Cache-Control' == $header['name'] && !empty($header['value'])) {
                // replace existing "max-age" value
                if (strpos($header['value'], 'age=') !== false) {
                    $cacheControlValue = preg_replace('/(s-)?max[-]?age=[0-9]+/', $maxAge, $header['value']);
                } else {
                    $cacheControlValue .= $header['value'].', '.$maxAge;
                }
            }
        }

        // set "Cache-Control" header with "s-maxage" value
        $response->setHeader('Cache-Control', $cacheControlValue, true);

        // set "Expires" header in the past to keep mod_expires from applying it's ruleset
        $response->setHeader('Expires', 'Mon, 31 Mar 2008 10:00:00 GMT', true);

        // set "Pragma: no-cache" - just in case
        $response->setHeader('Pragma', 'no-cache', true);
    }

    /**
     * Find all domains for store
     *
     * @param int    $storeId
     * @param string $seperator
     * @return string
     */
    public function getStoreDomainList($storeId = 0, $seperator = '|')
    {
        return implode($seperator, $this->_getStoreDomainsArray($storeId));
    }

    /**
     * @param int $storeId
     *
     * @return array
     */
    protected function _getStoreDomainsArray($storeId = 0)
    {
        if (!isset($this->_storeDomainArray[$storeId])) {
            $this->_storeDomainArray[$storeId] = array();

            $storeIds = array($storeId);
            // if $store is empty or 0 get all store ids
            if (empty($storeId)) {
                $storeIds = Mage::getResourceModel('core/store_collection')->getAllIds();
            }

            $urlTypes = array(
                Mage_Core_Model_Store::URL_TYPE_LINK,
                Mage_Core_Model_Store::URL_TYPE_DIRECT_LINK,
                Mage_Core_Model_Store::URL_TYPE_WEB,
                Mage_Core_Model_Store::URL_TYPE_SKIN,
                Mage_Core_Model_Store::URL_TYPE_JS,
                Mage_Core_Model_Store::URL_TYPE_MEDIA
            );
            foreach ($storeIds as $_storeId) {
                $store = Mage::getModel('core/store')->load($_storeId);

                foreach ($urlTypes as $urlType) {
                    // get non-secure store domain
                    $this->_storeDomainArray[$storeId][] = Zend_Uri::factory($store->getBaseUrl($urlType, false))->getHost();
                    // get secure store domain
                    $this->_storeDomainArray[$storeId][] = Zend_Uri::factory($store->getBaseUrl($urlType, true))->getHost();
                }
            }

            // get only unique values
            $this->_storeDomainArray[$storeId] = array_unique($this->_storeDomainArray[$storeId]);
        }

        return $this->_storeDomainArray[$storeId];
    }

    /**
     * Set appropriate cache control raw headers.
     * Called when script exits before controller_action_postdispatch
     * avoiding Zend_Controller_Response_Http#sendResponse()
     *
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    public function setCacheControlHeadersRaw()
    {
        if (Mage::registry(self::REGISTRY_VAR_VARNISH_CACHE_CONTROL_HEADERS_SET_FLAG)
            || Mage::app()->getStore()->isAdmin()) {
            return $this;
        }

        try {
            $response =  Mage::app()->getResponse();
            $response->canSendHeaders(true);
            $this->setCacheControlHeaders();
            foreach ($response->getHeaders() as $header) {
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        } catch (Exception $e) {
//            Mage::helper('varnishcache')->debug(
//            	'Error while trying to set raw cache control headers: '.$e->getMessage()
//            );
        }

        return $this;
    }

    /**
     * Return "1" if message cookie is set.
     *
     * @return null | string
     */
    public function getMessageNoCacheCookie()
    {
        return $this->getCookie()->get(self::MESSAGE_NO_CACHE_COOKIE);
    }

    /**
     * Sets a temporary no-cache-cookie for messages
     *
     * @return void;
     */
    public function setMessageNoCacheCookie()
    {
        $this->getCookie()->set(self::MESSAGE_NO_CACHE_COOKIE, 1);
        $this->setNoCacheHeader();
    }

    /**
     * Deletes temporary no-cache-cookie for messages
     *
     * @return void;
     */
    public function deleteMessageNoCacheCookie()
    {
        $this->getCookie()->delete(self::MESSAGE_NO_CACHE_COOKIE);
        $this->setNoCacheHeader();
    }
}

