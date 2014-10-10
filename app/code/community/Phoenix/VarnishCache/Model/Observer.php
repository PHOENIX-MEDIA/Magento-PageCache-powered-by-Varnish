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

class Phoenix_VarnishCache_Model_Observer
{
    const SET_CACHE_HEADER_FLAG = 'VARNISH_CACHE_CONTROL_HEADERS_SET';
    const MESSAGE_ADDED_FLAG = 'VARNISH_CACHE_MESSAGE_ADDED_FLAG';

    /**
     * @var Phoenix_VarnishCache_Helper_Cache
     */
    protected $_cacheHelper = null;


    /**
     * Retrieve session model
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Check if full page cache is enabled
     *
     * @return bool
     */
    protected function _isCacheEnabled()
    {
        return Mage::helper('varnishcache')->isEnabled();
    }

    /**
     * Get Varnish control model
     *
     * @return Phoenix_VarnishCache_Model_Control
     */
    protected function _getCacheControl()
    {
        return Mage::getSingleton('varnishcache/control');
    }

    /**
     * Return cache helper
     *
     * @return Phoenix_VarnishCache_Helper_Cache
     */
    protected function _getCacheHelper()
    {
        if (is_null($this->_cacheHelper)) {
            $this->_cacheHelper = Mage::helper('varnishcache/cache');
        }
        return $this->_cacheHelper;
    }

    /**
     * Clean all Varnish cache items
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function cleanCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $this->_getCacheControl()->clean($this->_getCacheHelper()->getStoreDomainList());

            $this->_getSession()->addSuccess(
                Mage::helper('varnishcache')->__('The Varnish cache has been cleaned.')
            );
        }
        return $this;
    }

    /**
     * Clean media (CSS/JS) cache
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function cleanMediaCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $this->_getCacheControl()->clean(
                $this->_getCacheHelper()->getStoreDomainList(),
                '^/media/(js|css|css_secure)/'
            );

            // also clean HTML files
            $this->_getCacheControl()->clean(
                $this->_getCacheHelper()->getStoreDomainList(),
                '.*',
                Phoenix_VarnishCache_Model_Control::CONTENT_TYPE_HTML
            );

            $this->_getSession()->addSuccess(
                Mage::helper('varnishcache')->__('The JavaScript/CSS cache has been cleaned on the Varnish servers.')
            );
        }
        return $this;
    }

    /**
     * Clean catalog images cache
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function cleanCatalogImagesCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $this->_getCacheControl()->clean(
                $this->_getCacheHelper()->getStoreDomainList(),
                '^/media/catalog/product/cache/',
                Phoenix_VarnishCache_Model_Control::CONTENT_TYPE_IMAGE
            );

            // also clean HTML files
            $this->_getCacheControl()->clean(
                $this->_getCacheHelper()->getStoreDomainList(),
                '.*',
                Phoenix_VarnishCache_Model_Control::CONTENT_TYPE_HTML
            );

            $this->_getSession()->addSuccess(
                Mage::helper('varnishcache')->__('The catalog image cache has been cleaned on the Varnish servers.')
            );
        }
        return $this;
    }

    /**
     * Purge category
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function purgeCatalogCategory(Varien_Event_Observer $observer)
    {
        try {
            $category = $observer->getEvent()->getCategory();
            if (!Mage::registry('varnishcache_catalog_category_purged_' . $category->getId())) {
                Mage::getModel('varnishcache/control_catalog_category')->purge($category);
                Mage::register('varnishcache_catalog_category_purged_' . $category->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('varnishcache')->debug('Error on save category purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge product
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function purgeCatalogProduct(Varien_Event_Observer $observer)
    {
        try {
            $product = $observer->getEvent()->getProduct();
            if (!Mage::registry('varnishcache_catalog_product_purged_' . $product->getId())) {
                Mage::getModel('varnishcache/control_catalog_product')->purge($product, true, true);
                Mage::register('varnishcache_catalog_product_purged_' . $product->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('varnishcache')->debug('Error on save product purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge Cms Page
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function purgeCmsPage(Varien_Event_Observer $observer)
    {
        try {
            $page = $observer->getEvent()->getObject();
            if (!Mage::registry('varnishcache_cms_page_purged_' . $page->getId())) {
                Mage::getModel('varnishcache/control_cms_page')->purge($page);
                Mage::register('varnishcache_cms_page_purged_' . $page->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('varnishcache')->debug('Error on save cms page purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge product
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function purgeCatalogProductByStock(Varien_Event_Observer $observer)
    {
        try {
            $item = $observer->getEvent()->getItem();
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            if (!Mage::registry('varnishcache_catalog_product_purged_' . $product->getId())) {
                Mage::getModel('varnishcache/control_catalog_product')->purge($product, true, true);
                Mage::register('varnishcache_catalog_product_purged_' . $product->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('varnishcache')->debug('Error on save product purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Set appropriate cache control headers
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function setCacheControlHeaders(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            if (!Mage::registry(self::SET_CACHE_HEADER_FLAG)) {
                $this->_getCacheHelper()->setCacheControlHeaders();
                Mage::register(self::SET_CACHE_HEADER_FLAG, true);
            }
        }
        return $this;
    }

    /**
     * If the page has been cached by the FPC and a NO_CACHE cookie has
     * been set, the cached Cache-Control header might allow caching of the
     * page while the NO_CACHE cookie which should prevent it.
     * To sanitize this conflict we will force a TTL=0 before sending out
     * the page.
     */
    public function sanitizeCacheControlHeader()
    {
        $this->_getCacheHelper()->sanitizeCacheControlHeader();
    }

    /**
     * Disable page caching by setting no-cache header
     *
     * @param Varien_Event_Observer $observer | null
     * @return Mage_PageCache_Model_Observer
     */
    public function disablePageCaching($observer = null)
    {
        if ($this->_isCacheEnabled() || Mage::app()->getStore()->isAdmin()) {
            $this->_getCacheHelper()->setNoCacheHeader();
        }
        return $this;
    }

    /**
     * Disable page caching for visitor by setting no-cache cookie
     *
     * @param Varien_Event_Observer $observer
     * @return Mage_PageCache_Model_Observer
     */
    public function disablePageCachingPermanent($observer = null)
    {
        if ($this->_isCacheEnabled()) {
            $this->_getCacheHelper()->setNoCacheCookie();
        }
        return $this;
    }

    /**
     * Sets shutdown listener to ensure cache control headers sent in case script exits unexpectedly
     *
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function registerShutdownFunction()
    {
        if ($this->_isCacheEnabled()) {
            /**
             *  workaround for PHP bug with autoload and open_basedir restriction:
             *  ensure the Zend exception class is loaded.
             */
            $exception = new Zend_Controller_Response_Exception;
            unset($exception);
            
            // register shutdown method
            register_shutdown_function(array($this->_getCacheHelper(), 'setCacheControlHeadersRaw'));
        }
        return $this;
    }

    /**
     * Shows notice to update Varnish VCL file
     *
     * @param Varien_Event_Observer $observer
     * @return Phoenix_VarnishCache_Model_Observer
     */
    public function showVclUpdateMessage(Varien_Event_Observer $observer)
    {
        try {
            Mage::getSingleton('core/session')->addNotice(
                Mage::helper('varnishcache')->__(
                    'Update Varnish VCL with design exceptions by using the following snippet:'
                )
            );

            $designExceptionSubSnippet = Mage::getSingleton('varnishcache/vcl')
                ->generateDesignExceptionSub();

            $designExceptionSubSnippet = str_replace(' ', '&nbsp;', $designExceptionSubSnippet);
            $designExceptionSubSnippet = nl2br($designExceptionSubSnippet);

            Mage::getSingleton('core/session')->addNotice($designExceptionSubSnippet);
        } catch (Exception $e) {
            $msg = 'Failed to prepare vcl: '.$e->getMessage();
            Mage::helper('varnishcache')->debug($msg);
            Mage::throwException($msg);
        }

        return $this;
    }

    /**
     * Set no-cache cookie for messages if messages have not been cleared before shutdown.
     *
     * @param Varien_Event_Observer $observer
     */
    public function handleMessageCookie(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            /**
             * the following lines are not "lege artis" but very efficient if assumed that the session structure for
             * messages are designed in "Magento style". that is each session object has its namespace in $_SESSION with
             * the property "messages" which is an instance of Mage_Core_Model_Message_Collection that holds all
             * messages for the session object. If the count of message items is > 0 before shutdown a message-no-cache
             * cookie should be set to keep Varnish from serving cached pages until the messages have been cleared
             * again. if no messages are in the session objects but a message-no-cache cookie has been send in the
             * request the messages have been cleared now and the message-no-cache cookie can be removed again, to allow
             * Varnish to serve cached pages for the next request.
             */
            $cntMessagesInSession = 0;
            if (isset($_SESSION)) {
                foreach ($_SESSION as $sessionData) {
                    if (isset($sessionData['messages']) &&
                        $sessionData['messages'] instanceof Mage_Core_Model_Message_Collection) {
                        $cntMessagesInSession += $sessionData['messages']->count();
                    }
                }
            }

            if ($cntMessagesInSession > 0) {
                $this->_getCacheHelper()->setMessageNoCacheCookie();
            } else {
                // remove message no-cache cookie for non ESI requests if no messages are left in session
                if ((Mage::registry('ESI_PROCESSING') === null) && $this->_getCacheHelper()->getMessageNoCacheCookie()) {
                    $this->_getCacheHelper()->deleteMessageNoCacheCookie();
                }
            }
        }
    }

    /**
     * replace all occurrences of the form key
     *
     * @param Varien_Event_Observer $observer
     */
    public function replaceFormKeys(Varien_Event_Observer $observer)
    {
        $esiHelper = Mage::helper('varnishcache/esi');
        /* @var $esiHelper Phoenix_VarnishCache_Helper_Esi */
        if (!$esiHelper->hasFormKey() || Mage::app()->getRequest()->isPost()) {
            return false;
        }

        $response = $observer->getResponse();
        $html     = $response->getBody();
        $html     = $esiHelper->replaceFormKey($html);

        $response->setBody($html);
    }

    /**
     * Register form key in session from cookie value
     *
     * @param Varien_Event_Observer $observer
     */
    public function registerCookieFormKey(Varien_Event_Observer $observer)
    {
        if ($formKey = Mage::helper('varnishcache/esi')->getCookieFormKey()) {
            $session = Mage::getSingleton('core/session');
            $session->setData('_form_key', $formKey);
        }
    }
}
