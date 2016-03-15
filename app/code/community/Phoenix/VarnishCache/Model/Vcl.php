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

class Phoenix_VarnishCache_Model_Vcl
{
    /**
     * Export VCL
     * @param string vclFile
     *
     * @return Phoenix_VarnishCache_Model_Vcl
     */
    public function export($vclFile)
    {
        $vcl = file_get_contents($vclFile);

        try {
            $newDesignExceptionSub = $this->_buildDesignExceptionSub($vclFile);
            $oldDesignExceptionSub = $this->_parseDesignExceptionSub('design_exception', $vcl);

            if ($oldDesignExceptionSub) {
                $vcl = str_ireplace($oldDesignExceptionSub, $newDesignExceptionSub, $vcl);
            } else {
                $vcl .= "\n" . $newDesignExceptionSub;
            }

            return $vcl;

        } catch (Exception $e) {
            $msg = 'Failed to prepare vcl: '.$e->getMessage();
            Mage::helper('varnishcache')->debug($msg);
            Mage::throwException($msg);
        }
        return $this;
    }

    /**
     * Generates design exceptions sub for current config
     * to be displayed after a design exception change in magento
     *
     * @return string
     */
    public function generateDesignExceptionSub()
    {
        // get versions of vcl files
        $vclFiles = Mage::getSingleton('varnishcache/admin_vcl')->toOptionArray();
        $versions = array();
        foreach($vclFiles as $vclFile) {
            $versions[] = $this->_getFileNameVersion($vclFile['value']);
        }
        $versions = array_unique($versions);

        // generate design exception for every version
        $out = '';
        foreach ($versions as $version) {
            $out .= Mage::helper('varnishcache')->__(
                'Use this snippet for version'
            ) . ' ' . $version . "\n";
            $out .= $this->_buildDesignExceptionSub('fake_' . $version);
            $out .= "\n\n";
        }

        return $out;
    }

    /**
     * Parse sub from given vcl string by given sub name
     *
     * @param string $name
     * @param string $vcl
     *
     * @return string
     */
    protected function _parseDesignExceptionSub($name, $vcl)
    {
        $sub = '';
        $lb = 0;
        $rb = 0;
        $inside = false;

        foreach (explode("\n", $vcl) as $line) {
            if ($inside && strpos(ltrim($line), '#') === 0) {
                $sub .= $line . "\n";
                continue;
            }

            if (preg_match('/sub\s' . $name . '\W*{/', $line)) {
                $inside = true;
            }

            if ($inside) {
                foreach (str_split($line) as $pos => $char) {
                    if ($char == '{') {
                        $lb++;
                    }

                    if ($char == '}') {
                        $rb++;
                        if ($lb && $rb >= $lb) {
                            $sub .= substr($line, 0, $pos + 1);
                            break 2;
                        }
                    }
                }

                $sub .= $line . "\n";
            }
        }

        return $sub;
    }

    /**
     * Prepare design exceptions vcl sub
     *
     * @param string vclFile
     *
     * @return string
     */
    protected function _buildDesignExceptionSub($vclFile)
    {
        $stores = Mage::app()->getStores();
        $configsPaths = array(
            'package'   => 'design/package/ua_regexp',
            'templates' => 'design/theme/template_ua_regexp',
            'skin'      => 'design/theme/skin_ua_regexp',
            'layout'    => 'design/theme/layout_ua_regexp',
            'theme'     => 'design/theme/default_ua_regexp'
        );

        $designExceptions = array();
        foreach ($stores as $storeId => $store) {
            // collect urls
            $urls = array();
            if ($store->getId() == $store->getGroup()->getDefaultStoreId()) {
                $urls[] = rtrim($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_DIRECT_LINK), '/');
            }
            $urls[] = rtrim($store->getBaseUrl(), '/');

            foreach ($urls as $url) {
                foreach ($configsPaths as $configType => $configPath) {
                    $config = Mage::getStoreConfig($configPath, $storeId);
                    if ($config) {
                        foreach (unserialize($config) as $exception) {
                            // get path
                            $pathInfo = parse_url($url);
                            $path = $pathInfo['path'];
                            if (!isset($path)) {
                                $path = '/';
                            }

                            // build data for hash
                            $data = array(
                                'configType' => $configType,
                                'host'       => $pathInfo['host'],
                                'path'       => $path,
                                'regexp'     => $exception['regexp'],
                                'value'      => $exception['value'],
                            );

                            // build hash rule
                            $hash = $this->_getVersionSpecificHash($vclFile, $data);
                            if ($hash !== false) {
                                $designException = array();
                                $designExceptionKey = $data['host'] . $data['path'] . $data['regexp'];

                                $designException[] = sprintf(
                                    '    if (req.http.host == "%s" && req.url ~ "^%s" && req.http.User-Agent ~ "%s") {',
                                    $data['host'], $data['path'], $data['regexp']
                                );
                                $designException[] = $hash;
                                $designException[] = '    }';

                                $designExceptions[$designExceptionKey] = implode("\n", $designException);
                            }
                        }
                    }
                }
            }
        }

        // build vcl sub
        $vclSub = array();
        $vclSub[] = "sub design_exception {";
        foreach($designExceptions as $designException) {
            $vclSub[] = $designException;
        }
        $vclSub[] = "}";

        return implode("\n", $vclSub);
    }

    /**
     * add version specific hashing code
     *
     * @param $vclFile
     * @param $data
     *
     * @return string|false
     */
    protected function _getVersionSpecificHash($vclFile, $data)
    {
        $majorVersion = $this->_getFileNameVersion($vclFile);

        // generate hash code based on major version
        switch ($majorVersion) {
            // varnish 3
            case 3:
                return sprintf(
                    '        hash_data("%s");',
                    sprintf(
                        '%s_%s',
                        $data['configType'], $data['value']
                    )
                );
                break;

            // varnish 4
            case 4:
                return sprintf(
                    '        hash_data("%s");',
                    sprintf(
                        '%s_%s',
                        $data['configType'], $data['value']
                    )
                );
                break;

            // not a supported version
            default:
                return false;
                break;
        }
    }

    /**
     * returns the major version from a filename
     *
     * @param $filename
     *
     * @return int
     */
    protected function _getFileNameVersion($filename) {
        $fileName = basename($filename);
        $fileParts = explode('_', $fileName);
        $versionParts = explode('.', array_pop($fileParts));
        $majorVersion = (int) $versionParts[0];

        return $majorVersion;
    }
}
