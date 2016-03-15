<?php
/**
 * PageCache powered by Varnish
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the PageCache powered by Varnish License
 * that is bundled with this package in the file LICENSE_VARNISH_CACHE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.phoenix-media.eu/license/license_varnish_cache.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@phoenix-media.eu so we can send you a copy immediately.
 *
 * @category   Phoenix
 * @package    Phoenix_VarnishCacheEnterprise
 * @copyright  Copyright (c) 2011 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://www.phoenix-media.eu/license/license_varnish_cache.txt
 */

class Phoenix_VarnishCache_Block_Adminhtml_System_Config_Form_Field_Exportvcl
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * set export button template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('varnishcache/system/config/exportVclGroup.phtml');
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getSelectHtml()
    {
        $select = $this->getLayout()->createBlock('core/html_select');
        $options = Mage::getSingleton('varnishcache/admin_vcl')->toOptionArray();

        $select->setId('varnishcache_export_vcl_select');
        $select->setOptions($options);

        return $select->getHtml();
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => 'varnishcache_export_vcl_button',
                'label'     => Mage::helper('varnishcache')->__('Export'),
                'onclick'   => 'javascript:exportVcl(); return false;'
            ));

        return $button->toHtml();
    }

    /**
     * returns the export URL
     *
     * @return string
     */
    public function getExportUrl()
    {
        return Mage::helper('adminhtml')->getUrl('*/varnishCache/exportVcl', array());
    }
}

