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

class Phoenix_VarnishCache_Block_Cookie_Formkey extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        // set default cache lifetime and cache tags
        $this->addData(array(
            'cache_lifetime'    => false
       ));
    }


    /**
     * Return environment cookie name
     *
     * @return string
     */
    public function getCookieName()
    {
        return Phoenix_VarnishCache_Helper_Esi::FORMKEY_COOKIE;
    }

    /**
     * Return the form key esi tag
     *
     * @return string
     */
    public function getFormKeyValue()
    {
        // try to use form key from session
        $session = Mage::getSingleton('core/session');
        $formKey = $session->getData('_form_key');

        // or create new one via esi
        if(empty($formKey)) {
            $formKey = Mage::helper('varnishcache/esi')->getFormKeyEsiTag();
        }

        return $formKey;
    }

    /**
     * Return the cookie lifetime
     *
     * @return int
     */
    public function getCookieLifetime()
    {
        return Mage::getModel('core/cookie')->getLifetime() * 1000;
    }
}
