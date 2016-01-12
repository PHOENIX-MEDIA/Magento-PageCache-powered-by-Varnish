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
 * @copyright  Copyright (c) 2011-2015 PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * The class encapsulating the hash parameters
 *
 * Class Phoenix_VarnishCache_Model_Hash_Parameters
 */
class Phoenix_VarnishCache_Model_Hash_Parameters
{
    private $_domains = array();
    private $_type;
    private $_regexp;

    /**
     * @return array
     */
    public function getDomains()
    {
        return $this->_domains;
    }

    /**
     * @param array $domains
     *
     * @return $this
     */
    public function setDomains(array $domains)
    {
        $this->_domains = $domains;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return !empty($this->_type) ? $this->_type : '.*';
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getRegexp()
    {
        return !empty($this->_regexp) ? $this->_regexp : '.*';
    }

    /**
     * @param string $regexp
     *
     * @return $this
     */
    public function setRegexp($regexp)
    {
        $this->_regexp = $regexp;
        return $this;
    }

    public function isWildcard()
    {
        return empty($this->_domains) && empty($this->_regexp) && empty($this->_type);
    }
}
