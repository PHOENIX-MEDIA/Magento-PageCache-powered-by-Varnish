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

/**
 * Varnish cache control model
 *
 * @category    Phoenix
 * @package     Phoenix_VarnishCache
 */
class Phoenix_VarnishCache_Model_Control
{
    const XML_PATH_VARNISH_SERVERS = 'varnishcache/general/servers';
    const XML_PATH_VARNISH_PORT    = 'varnishcache/general/port';

    const CONTENT_TYPE_HTML = 'text/html';
    const CONTENT_TYPE_CSS = 'text/css';
    const CONTENT_TYPE_JS = 'javascript';
    const CONTENT_TYPE_IMAGE = 'image/';

    const VARNISH_HEADER_REGEX = 'X-Purge-Regex';
    const VARNISH_HEADER_HOST = 'X-Purge-Host';
    const VARNISH_HEADER_CONTENT_TYPE = 'X-Purge-Content-Type';

    const MAX_URL_PATHS_PER_REQUEST = 50;

    /**
     * Get content types as option array
     */
    public function getContentTypes()
    {
        $contentTypes = array(
            self::CONTENT_TYPE_HTML     => Mage::helper('varnishcache')->__('HTML'),
            self::CONTENT_TYPE_CSS      => Mage::helper('varnishcache')->__('CSS'),
            self::CONTENT_TYPE_JS       => Mage::helper('varnishcache')->__('JavaScript'),
            self::CONTENT_TYPE_IMAGE    => Mage::helper('varnishcache')->__('Images')
        );

        return $contentTypes;
    }


    /**
     * Clean Varnish cache
     *
     * @param   string  domain names for cleaning
     * @param   string  RegEx pattern for url matching
     * @param   string  content type to clean
     * @return  void
     */
    public function clean($domains, $urlRegEx = '.*', $contentType = '.*')
    {
        try {
            $headers = array(
                            self::VARNISH_HEADER_HOST   =>  '^('.str_replace('.', '.', $domains).')$',
                            self::VARNISH_HEADER_REGEX  =>  (empty($urlRegEx) ? '.*' : $urlRegEx),
                            self::VARNISH_HEADER_CONTENT_TYPE => (empty($contentType) ? '.*' : $contentType)
                        );

            $this->_purgeCacheServers($headers);

            Mage::helper('varnishcache')->debug('Purged Varnish items with parameters '.var_export($headers, true));
        } catch (Exception $e) {
            Mage::helper('varnishcache')->debug('Error during purging: '.$e->getMessage());
            return false;
        }

        return true;
    }

    public function cleanUrlPaths($domains, $urlPaths)
    {
        $urlPaths = array_chunk($urlPaths, self::MAX_URL_PATHS_PER_REQUEST);
        foreach ($urlPaths as $urlPathsChunk) {
            $urlRegexp = '/(' . implode('|', $urlPathsChunk) . ')';
            $this->clean($domains, $urlRegexp);
        }
    }

    /**
     * Process all servers
     */
    protected function _purgeCacheServers(Array $headers)
    {
        $servers = $this->_getVarnishServers();
        if (empty($servers)) {
            return;
        }

        // process all servers
        foreach ($servers as $server) {
            // compile url string with scheme, domain/server and port
            $uri = 'http://'.$server;
            if ($port = trim(Mage::getStoreConfig(self::XML_PATH_VARNISH_PORT))) {
                $uri .= ':'.$port;
            }
            $uri .= '/';

            try {
                // create HTTP client
                $client = new Zend_Http_Client();
                $client->setUri($uri)
                    ->setHeaders($headers)
                    ->setConfig(array('timeout'=>15));

                // send PURGE request
                $response = $client->request('PURGE');

                // check response
                if ($response->getStatus() != '200') {
                    throw new Exception('Return status '.$response->getStatus());
                }
            } catch (Exception $e) {
                Mage::helper('varnishcache')->debug('Purging on server '.$server.' failed ('.$e->getMessage().').');
            }
        }
    }

    /**
     * Get Varnish servers for purge
     *
     * @return string
     */
    protected function _getVarnishServers()
    {
        return explode(';', Mage::getStoreConfig(self::XML_PATH_VARNISH_SERVERS));
    }
}
