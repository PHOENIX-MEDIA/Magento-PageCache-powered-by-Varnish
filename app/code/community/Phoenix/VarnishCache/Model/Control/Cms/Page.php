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

class Phoenix_VarnishCache_Model_Control_Cms_Page
    extends Phoenix_VarnishCache_Model_Control_Abstract
{
    const XML_PATH_WEB_DEFAULT_CMS_HOME_PAGE = 'web/default/cms_home_page';

    protected $_helperName = 'varnishcache/control_cms_page';

    /**
     * Purge Cms Page
     *
     * @param Mage_Cms_Model_Page $page
     * @return Phoenix_VarnishCache_Model_Control_Cms_Page
     */
    public function purge(Mage_Cms_Model_Page $page)
    {
        if ($this->_canPurge()) {

            $storeIds = Mage::getResourceModel('varnishcache/cms_page_store_collection')
                ->addPageFilter($page->getId())
                ->getAllIds();

            if (count($storeIds) && current($storeIds) == 0) {
                $storeIds = Mage::getResourceModel('core/store_collection')
                    ->setWithoutDefaultFilter()
                    ->getAllIds();
            }

            foreach ($storeIds as $storeId) {
                $url = Mage::app()->getStore($storeId)
                    ->getUrl(null, array('_direct' => $page->getIdentifier()));
                extract(parse_url($url));
                $path = rtrim($path, '/');
                $this->_getCacheControl()->clean($host, '^' . $path . '/{0,1}$');

                // Purge if current page is a home page
                $homePageIdentifier
                    = Mage::getStoreConfig(self::XML_PATH_WEB_DEFAULT_CMS_HOME_PAGE, $storeId);
                if ($page->getIdentifier() == $homePageIdentifier) {
                    $url = Mage::app()->getStore($storeId)
                        ->getUrl();
                    extract(parse_url($url));
                    $path = rtrim($path, '/');
                    $this->_getCacheControl()->clean($host, '^' . $path . '/{0,1}$');
                    $this->_getCacheControl()->clean($host, '^/{0,1}$');
                }
            }

            $this->_getSession()->addSuccess(
            	Mage::helper('varnishcache')->__('Varnish cache for "%s" has been purged.', $page->getTitle())
            );

        }
        return $this;
    }

}
