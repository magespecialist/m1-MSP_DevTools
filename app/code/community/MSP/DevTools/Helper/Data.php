<?php
/**
 * IDEALIAGroup srl
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@idealiagroup.com so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_DevTools
 * @copyright  Copyright (c) 2016 IDEALIAGroup srl (http://www.idealiagroup.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MSP_DevTools_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_GENERAL_ENABLED = 'msp_devtools/general/enabled';
    const XML_PATH_GENERAL_AUTHORIZED_IPS = 'msp_devtools/general/authorized_ranges';

    const XML_PATH_PHPSTORM_ENABLED = 'msp_devtools/phpstorm/enabled';
    const XML_PATH_PHPSTORM_PORT = 'msp_devtools/phpstorm/port';

    const PROTOCOL_VERSION = 3;

    protected $_scopeConfigInterface;
    protected $_remoteAddress;
    protected $_canInjectCode = null;
    protected $_isPaused = false;

    /**
     * Return true if phpstorm integration is enabled
     * @return boolean
     */
    public function getPhpStormEnabled()
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_PHPSTORM_ENABLED);
    }

    /**
     * Return true if phpstorm integration port
     * @return int
     */
    public function getPhpStormPort()
    {
        $port = intval(Mage::getStoreConfig(self::XML_PATH_PHPSTORM_PORT));
        if (!$port) {
            $port = 8091;
        }
        return $port;
    }

    /**
     * Get php storm URL
     * @param $file
     * @return string|null
     */
    public function getPhpStormUrl($file)
    {
        if (!$this->getPhpStormEnabled() || !$file) {
            return null;
        }

        return 'http://127.0.0.1:'.$this->getPhpStormPort().'?message='.urlencode($file);
    }

    /**
     * Return true if devtools are enabled
     * @return boolean
     */
    public function getEnabled()
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_GENERAL_ENABLED);
    }

    /**
     * Return true if IP is in range
     * @param $ip
     * @param $range
     * @return bool
     */
    public function getIpInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            $range .= '/32';
        }

        list($range, $netmask) = explode('/', $range, 2);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;

        return (bool) (($ipDecimal & $netmaskDecimal ) == ($rangeDecimal & $netmaskDecimal));
    }

    /**
     * Return true if IP is matched in a range list
     * @param $ip
     * @param array $ranges
     * @return bool
     */
    public function getIpIsMatched($ip, array $ranges)
    {
        foreach ($ranges as $range) {
            if ($this->getIpInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a list of allowed IPs
     * @return array
     */
    public function getAllowedRanges()
    {
        $ranges = Mage::getStoreConfig(self::XML_PATH_GENERAL_AUTHORIZED_IPS);
        return preg_split('/\s*[,;]+\s*/', $ranges);
    }

    /**
     * Return true if debugger is active
     * @return boolean
     */
    public function isActive()
    {
        if ($this->getEnabled()) {
            $ip = Mage::app()->getRequest()->getClientIp();

            $allowedRanges = $this->getAllowedRanges();

            if (count($allowedRanges)) {
                return $this->getIpIsMatched($ip, $allowedRanges);
            }
        }

        return false;
    }

    /**
     * Resolve a Magento class path by fail ename
     * @param string $className
     * @return string
     */
    public function resolveClassFile($className)
    {
        $varienIo = new Varien_Io_File();
        $classFile = str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $className)));

        $pools = ['local', 'community', 'core'];
        foreach ($pools as $pool) {
            $file = 'app' . DS . 'code' . DS . $pool . DS . $classFile . '.php';

            if ($varienIo->fileExists(BP . DS . $file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Pause MSP DevTools
     * @return $this
     */
    public function pause()
    {
        $this->_isPaused = true;
        return $this;
    }

    /**
     * Resume MSP DevTools
     * @return $this
     */
    public function resume()
    {
        $this->_isPaused = false;
        return $this;
    }

    /**
     * Return true if paused
     * @return bool
     */
    public function isPaused()
    {
        return $this->_isPaused;
    }

    /**
     * Return a list of actions that should be excluded
     * @return array
     */
    public function getBlackListedActions()
    {
        return array(
            'adminhtml/catalog_product_gallery/upload',
        );
    }

    /**
     * Return true if can inject devtools code
     * @return null
     */
    public function canInjectCode()
    {
        // This must be outside the next if because it can be temporary
        if ($this->isPaused()) {
            return false;
        }

        $request = Mage::app()->getRequest();
        $actionName = Mage::getDesign()->getArea() . '/' .
            $request->getControllerName() . '/' . $request->getActionName();

        if (in_array($actionName, $this->getBlackListedActions())) {
            return false;
        }

        if (is_null($this->_canInjectCode)) {
            $this->_canInjectCode = false;

            if ($this->isActive()) {
                $requestWith = strtolower($request->getHeader('x-requested-with'));
                $responseHeaders = Mage::app()->getResponse()->getHeaders();

                foreach ($responseHeaders as $responseHeader) {
                    if (
                        (strtolower($responseHeader['name']) == 'content-type') &&
                        (strpos($responseHeader['value'], 'text/html') !== false)
                    ) {
                        $this->_canInjectCode = true;
                    }
                }

                if ($this->_canInjectCode) {
                    if (($requestWith == 'xmlhttprequest') || (strpos($requestWith, 'shockwaveflash') !== false)) {
                        $this->_canInjectCode = false;
                    }
                }
            }
        }

        return $this->_canInjectCode;
    }
}
