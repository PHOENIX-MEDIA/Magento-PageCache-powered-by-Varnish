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
 * @copyright  Copyright (c) 2016 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://www.phoenix-media.eu/license/license_varnish_cache.txt
 */

class Phoenix_VarnishCache_Model_Admin_Vcl
{
    public function toOptionArray()
    {
        $moduleDir = Mage::getModuleDir('etc', 'Phoenix_VarnishCache');
        $searchPattern = $moduleDir . DS . '*.vcl';

        $vclFiles = array();
        foreach(glob($searchPattern) as $filename) {
            $vclFiles[] = array(
                'value' => $filename,
                'label' => basename($filename)
            );
        }

        return $vclFiles;
    }
}
