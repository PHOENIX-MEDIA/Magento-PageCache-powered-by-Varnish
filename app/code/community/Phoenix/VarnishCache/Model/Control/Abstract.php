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

class Phoenix_VarnishCache_Model_Control_Abstract
{
    protected $_helperName;

    /**
     * Retrieve adminhtml session model object
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Returns true if Varnish PageCache enabled and Purge Product config option set to 1
     *
     * @return bool
     */
    protected function _canPurge()
    {
        if (!$this->_helperName) {
            return false;
        }
        return Mage::helper($this->_helperName)->canPurge();
    }

    /**
     * Get Varnish control model
     *
     * @return Phoenix_VarnishCache_Model_Control
     */
    protected function _getCacheControl()
    {
        return Mage::helper('varnishcache')->getCacheControl();
    }

    /**
     * Returns domain list for store
     *
     * @return array
     */
    protected function _getStoreDomainList()
    {
        return Mage::helper('varnishcache/cache')->getStoreDomainList();
    }

    /**
     * Get url rewrite collection
     *
     * @return Phoenix_VarnishCache_Model_Resource_Mysql4_Core_Url_Rewrite_Collection
     */
    protected function _getUrlRewriteCollection()
    {
        return Mage::getResourceModel('varnishcache/core_url_rewrite_collection');
    }

    /**
     * Get product relation collection
     *
     * @return Phoenix_VarnishCache_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
     */
    protected function _getProductRelationCollection()
    {
        return Mage::getResourceModel('varnishcache/catalog_product_relation_collection');
    }

    /**
     * Get catalog category product relation collection
     *
     * @return Phoenix_VarnishCache_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
     */
    protected function _getCategoryProductRelationCollection()
    {
        return Mage::getResourceModel('varnishcache/catalog_category_product_collection');
    }
}
