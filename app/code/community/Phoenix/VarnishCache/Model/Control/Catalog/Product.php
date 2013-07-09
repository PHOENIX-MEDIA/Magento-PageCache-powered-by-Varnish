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
 * @copyright  Copyright (c) 2011-2013 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Phoenix_VarnishCache_Model_Control_Catalog_Product
    extends Phoenix_VarnishCache_Model_Control_Abstract
{
    protected $_helperName = 'varnishcache/control_catalog_product';

    /**
     * Purge product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $purgeParentProducts
     * @param bool $purgeCategories
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Product
     */
    public function purge(Mage_Catalog_Model_Product $product, $purgeParentProducts = false, $purgeCategories = false)
    {
        if ($this->_canPurge()) {
            $this->_purgeById($product->getId());
            $this->_getSession()->addSuccess(
            	Mage::helper('varnishcache')->__('Varnish cache for "%s" has been purged.', $product->getName())
            );
            if ($purgeParentProducts) {
                // purge parent products
                $productRelationCollection = $this->_getProductRelationCollection()
                    ->filterByChildId($product->getId());
                foreach ($productRelationCollection as $productRelation) {
                    $this->_purgeById($productRelation->getParentId());
                }
                // purge categories of parent products
                if ($purgeCategories) {
                    $categoryProductCollection = $this->_getCategoryProductRelationCollection()
                        ->filterAllByProductIds($productRelationCollection->getAllIds());
                    $catalogCacheControl = $this->_getCategoryCacheControl();
                    foreach ($categoryProductCollection as $categoryProduct) {
                        $catalogCacheControl->purgeById($categoryProduct->getCategoryId());
                    }
                }
            }
            if ($purgeCategories) {
                $catalogCacheControl = $this->_getCategoryCacheControl();
                foreach ($product->getCategoryCollection() as $category) {
                    $catalogCacheControl->purge($category);
                }
                $this->_getSession()->addSuccess(
                	Mage::helper('varnishcache')->__('Varnish cache for the product\'s categories has been purged.')
                );
            }
        }
        return $this;
    }

    /**
     * Purge product by id
     *
     * @param int $id
     * @param bool $purgeParentProducts
     * @param bool $purgeCategories
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Product
     */
    public function purgeById($id, $purgeParentProducts = false, $purgeCategories = false)
    {
        $product = Mage::getModel('catalog/product')->load($id);
        return $this->purge($product, $purgeParentProducts, $purgeCategories);
    }

    /**
     * Purge product by id
     *
     * @param int $id
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Product
     */
    protected function _purgeById($id)
    {
        $collection = $this->_getUrlRewriteCollection()
            ->filterAllByProductId($id);
        foreach ($collection as $urlRewriteRule) {
            $urlRegexp = '/' . $urlRewriteRule->getRequestPath();
            $this->_getCacheControl()
                ->clean($this->_getStoreDomainList(), $urlRegexp);
        }
        return $this;
    }

    /**
     * Get Category Cache Control model
     *
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Category
     */
    protected function _getCategoryCacheControl()
    {
        return Mage::getModel('varnishcache/control_catalog_category');
    }
}
