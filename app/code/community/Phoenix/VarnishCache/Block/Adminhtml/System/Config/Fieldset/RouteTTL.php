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

class Phoenix_VarnishCache_Block_Adminhtml_System_Config_Fieldset_RouteTTL
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn(
            'regexp', array(
                'label' => Mage::helper('adminhtml')->__('Route'),
                'style' => 'width:120px')
        );
        $this->addColumn(
            'value', array(
                'label' => Mage::helper('adminhtml')->__('TTL'),
                'style' => 'width:120px')
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add route');
        parent::__construct();
    }

    protected function _toHtml()
    {
        return '<div id="varnishcache_general_routes_ttl">' . parent::_toHtml() . '</div>';
    }
}
