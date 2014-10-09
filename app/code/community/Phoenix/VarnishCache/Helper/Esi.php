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

class Phoenix_VarnishCache_Helper_Esi extends Mage_Core_Helper_Abstract
{
    const ESI_FORMKEY_URL   = 'varnishcache/getformkey/';
    const FORMKEY_COOKIE    = 'PAGECACHE_FORMKEY';
    const ESI_INCLUDE_OPEN  = '<esi:include src="';
    const ESI_INCLUDE_CLOSE = '" />';

    /**
     * return if used magento version uses form keys
     *
     * @return bool
     */
    public function hasFormKey()
    {
        $session = Mage::getSingleton('core/session');

        return method_exists($session, 'getFormKey');
    }

    /**
     * generate esi tag for form keys
     *
     * @return string
     */
    public function getFormKeyEsiTag()
    {
        $url = Mage::getUrl(
            self::ESI_FORMKEY_URL,
            array(
                '_nosid'  => true,
                '_secure' => false
            )
        );
        $esiTag = self::ESI_INCLUDE_OPEN . $url . self::ESI_INCLUDE_CLOSE;

        return $esiTag;
    }

    /**
     * Replace form key with esi tag
     *
     * @param string $content
     * @return string
     */
    public function replaceFormKey($content)
    {
        /** @var $session Mage_Core_Model_Session */
        $session = Mage::getSingleton('core/session');

        // replace all occurrences of form key with esi tag
        $content = str_replace(
            $session->getFormKey(),
            $this->getFormKeyEsiTag(),
            $content
        );

        return $content;
    }

    /**
     * Return the form key value stored in a cookie
     * or false if it is not set
     *
     * @return string|false
     */
    public function getCookieFormKey()
    {
        return Mage::getSingleton('core/cookie')->get(self::FORMKEY_COOKIE);
    }
}
