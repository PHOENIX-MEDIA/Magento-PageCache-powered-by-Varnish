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
            $idsToPurge = array();
            $categoryIdsToPurge = array();
            $idsToPurge[] = $product->getId();
            $this->_getSession()->addSuccess(
            	Mage::helper('varnishcache')->__('Varnish cache for "%s" has been purged.', $product->getName())
            );

            if ($purgeParentProducts) {
                // purge parent products
                $productRelationCollection = $this->_getProductRelationCollection()
                    ->filterByChildId($product->getId());
                foreach ($productRelationCollection as $productRelation) {
                    $idsToPurge[] = $productRelation->getParentId();
                }
                // purge categories of parent products
                if ($purgeCategories) {
                    $categoryProductCollection = $this->_getCategoryProductRelationCollection()
                        ->filterAllByProductIds($productRelationCollection->getAllIds());

                    foreach ($categoryProductCollection as $categoryProduct) {
                        $categoryIdsToPurge[] = $categoryProduct->getCategoryId();
                    }
                }
            }

            $this->_purgeByIds($idsToPurge);

            if ($purgeCategories) {
                foreach ($product->getCategoryCollection() as $category) {
                    $categoryIdsToPurge[] = $category->getId();
                }
                $this->_getSession()->addSuccess(
                	Mage::helper('varnishcache')->__('Varnish cache for the product\'s categories has been purged.')
                );
            }

            $this->_purgeCategoriesByIds($categoryIdsToPurge);
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
        $urlPaths = array();
        foreach ($collection as $urlRewriteRule) {
            $urlPaths[] = $urlRewriteRule->getRequestPath();
        }
        $urlRegexp = '/(' . implode('|', $urlPaths) . ')';
        $this->_getCacheControl()
            ->clean($this->_getStoreDomainList(), $urlRegexp);
        return $this;
    }

    /**
     * Purge product by ids
     *
     * @param $ids
     *
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Product
     */
    protected function _purgeCategoriesByIds($ids)
    {
        $idPaths = array();
        foreach ($ids as $id) {
            $idPaths[] = "category/$id";
        }

        $collection = $this->_getUrlRewriteCollection();
        $collection->getSelect()
            ->where('id_path IN ("' . implode('","', $idPaths) . '")');

        $urlPaths = array();
        foreach ($collection as $urlRewriteRule) {
            $urlPaths[] = $urlRewriteRule->getRequestPath();
        }

        $this->_getCacheControl()->cleanUrlPaths($this->_getStoreDomainList(), $urlPaths);
    }

    /**
     * Purge product by ids
     *
     * @param $ids
     *
     * @return Phoenix_VarnishCache_Model_Control_Catalog_Product
     */
    protected function _purgeByIds($ids)
    {
        $idPaths = array();
        foreach ($ids as $id) {
            $idPaths[] = "product/$id";
        }

        $collection = $this->_getUrlRewriteCollection();
        $collection->getSelect()
            ->where('id_path IN ("' . implode('","', $idPaths) . '")');

        foreach ($idPaths as $idPath) {
            $collection->getSelect()
                ->orWhere('id_path LIKE "' . $idPath . '/%"');
        }

        $urlPaths = array();
        foreach ($collection as $urlRewriteRule) {
            $urlPaths[] = $urlRewriteRule->getRequestPath();
        }

        $this->_getCacheControl()->cleanUrlPaths($this->_getStoreDomainList(), $urlPaths);

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
