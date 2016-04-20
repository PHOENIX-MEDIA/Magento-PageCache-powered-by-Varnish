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

class Phoenix_VarnishCache_Adminhtml_VarnishCacheController
    extends Mage_Adminhtml_Controller_Action
{   
    /**
     * get current session
     *
     * @return mixed
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * clean cache
     */
    public function cleanAction()
    {
        try {
            if (Mage::helper('varnishcache')->isEnabled()) {
                // get domains for purging
                $domains = Mage::helper('varnishcache/cache')
                    ->getStoreDomainList($this->getRequest()->getParam('stores', 0));

                // clean Varnish cache
                Mage::getModel('varnishcache/control')
                    ->clean($domains, '.*', $this->getRequest()->getParam('content_types', '.*'));

                $this->_getSession()->addSuccess(
                    Mage::helper('varnishcache')->__('The Varnish cache has been cleaned.')
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('varnishcache')->__('An error occurred while clearing the Varnish cache.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * quick purge cache
     */
    public function quickPurgeAction()
    {
        try {
            if (Mage::helper('varnishcache')->isEnabled()) {

                $url = $this->getRequest()->getParam('quick_purge_url', false);
                if (!$url) {
                    throw new Mage_Core_Exception(Mage::helper('varnishcache')->__('Invalid URL "%s".', $url));
                }

                $domainList = Mage::helper('varnishcache/cache')->getStoreDomainList();
                extract(parse_url($url));
                if (!isset($host)) {
                    throw new Mage_Core_Exception(Mage::helper('varnishcache')->__('Invalid URL "%s".', $url));
                }
                if (!in_array($host, explode('|', $domainList))) {
                    throw new Mage_Core_Exception(Mage::helper('varnishcache')->__('Invalid domain "%s".', $host));
                }

                $uri = '';
                if (isset($path)) {
                    $uri .= $path;
                }
                if (isset($query)) {
                    $uri .= '\?';
                    $uri .= $query;
                }
                if (isset($fragment)) {
                    $uri .= '#';
                    $uri .= $fragment;
                }

                Mage::getModel('varnishcache/control')
                    ->clean($host, sprintf('^%s$', $uri));

                $this->_getSession()->addSuccess(
                    Mage::helper('varnishcache')->__('The URL\'s "%s" cache has been cleaned.', $url)
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('varnishcache')->__('An error occurred while clearing the Varnish cache.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * export vcl
     *
     * @return mixed
     */
    public function exportVclAction()
    {
        // check if vcl is valid
        $vcl = $this->getRequest()->getParam('vcl', false);
        if (! $this->_isVclValid($vcl)) {
            $this->_redirectReferer();
        }

        try {
            return $this->_prepareDownloadResponse(
                basename($vcl),
                Mage::getSingleton('varnishcache/vcl')->export($vcl)
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectReferer();
        }
    }

    /**
     * Check if cache management is allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/phoenix_varnish_cache');
    }

    /**
     * check if given vcl file is valid
     *
     * @param $vclFile
     * @return bool
     */
    protected function _isVclValid($vclFile)
    {
        $validVcls = Mage::getSingleton('varnishcache/admin_vcl')->toOptionArray();

        // check if vcl is valid
        foreach($validVcls as $validVcl) {
            if ($validVcl['value'] == $vclFile) {
                return true;
            }
        }

        return false;
    }
}
