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

class Phoenix_VarnishCache_Model_Processor
{
    /**
     * This is only a dummy function at the moment to  sanitize Cache-
     * Control headers on FPC hits. It doesn't do what might be expected
     * (retrieve cached content without ramping up the whole application
     * stack), but it is the only way to hook in our logic.
     * 
     * This method is called at the very beginning of Magento from
     * Mage_Corel_Model_App::run() ->
     * Mage_Core_Model_Cache::processRequest().
     * 
     * @param string $content
     * @return string | false
     */
    public function extractContent($content)
    {
        /**
         * if content has been fetched from cache (FPC had a cache hit) the
         * HTTP headers have been already set by the FPC. However if a
         * NO_CACHE cookie is present we need to make sure the TTL is 0 as
         * it might cache with a TTL > 0 which is a logical constraint.
         */ 
        if (!empty($content)) {
        	Phoenix_VarnishCache_Helper_Cache::sanitizeCacheControlHeader();
        }
        return $content;
    }
}
