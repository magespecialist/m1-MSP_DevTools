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

class MSP_DevTools_Model_Observer
{
    /**
     * Inject data-* attribute into html document
     * @param $html
     * @param $blockId
     * @param $name
     * @return string
     */
    protected function _injectHtmlAttribute($html, $blockId, $name)
    {
        if (!Mage::helper('msp_devtools')->canInjectCode() || !$html) {
            return $html;
        }

        $html = '<!-- START_MSPDEV[' . $blockId . ']: ' . $name . ' -->' . $html
            . '<!-- /END_MSPDEV[' . $blockId . ']: ' . $name . ' -->';

        return $html;
    }
    
    public function coreBlockAbstractToHtmlBefore($event)
    {
        $elementRegistry = Mage::getSingleton('msp_devtools/elementRegistry');

        /** @var Mage_Core_Block_Abstract $block */
        $block = $event->getEvent()->getBlock();

        $name = $block->getNameInLayout();
        $elementRegistry->start($name);
    }

    public function coreBlockAbstractToHtmlAfter($event)
    {
        if (!Mage::helper('msp_devtools')->canInjectCode()) {
            return;
        }

        $elementRegistry = Mage::getSingleton('msp_devtools/elementRegistry');

        /** @var Mage_Core_Block_Abstract $block */
        $block = $event->getEvent()->getBlock();
        $name = $block->getNameInLayout();
        $transport = $event->getEvent()->getTransport();

        $templateFile = null;
        if ($block->getTemplateFile()) {
            $templateFile = 'app/design/' . $block->getTemplateFile();
        }

        $payload = array(
            'class' => get_class($block),
            'template' => $block->getTemplate(),
            'cache_key' => $block->getCacheKey(),
            'cache_key_info' => $block->getCacheKeyInfo(),
            'module' => $block->getModuleName(),
            'phpstorm_links' => [],
        );

        if ($templateFile) {
            $payload['template_file'] = $templateFile;

            $phpStormUrl = Mage::helper('msp_devtools')->getPhpStormUrl($templateFile);
            if ($phpStormUrl) {
                $payload['phpstorm_url'] = $phpStormUrl;

                $payload['phpstorm_links'][] = [
                    'key' => 'Template File',
                    'file' => $templateFile,
                    'link' => $phpStormUrl,
                ];
            }
        }

        $classFile = Mage::helper('msp_devtools')->resolveClassFile(get_class($block));
        $phpStormUrl = Mage::helper('msp_devtools')->getPhpStormUrl($classFile);
        if ($classFile) {
            $payload['phpstorm_links'][] = [
                'key' => 'Block Class',
                'file' => $classFile,
                'link' => $phpStormUrl,
            ];
        }

        $blockId = $elementRegistry->getOpId();
        $payload['id'] = $blockId;
        $elementRegistry->stop($name, $payload);

        $html = trim($transport->getHtml());
        $transport->setHtml($this->_injectHtmlAttribute($html, $blockId, $name));
    }

    public function httpResponseSendBefore($event)
    {
        if (!Mage::helper('msp_devtools')->canInjectCode()) {
            return;
        }

        Mage::getSingleton('msp_devtools/elementRegistry')->calcTimers();
        Mage::getSingleton('msp_devtools/eventRegistry')->calcTimers();

        Varien_Profiler::stop('DISPATCH EVENT:http_response_send_before');

        $pageInfo = Mage::getSingleton('msp_devtools/pageInfo')->getPageInfo();

        /** @var $response Mage_Core_Controller_Response_Http */
        $response = $event->getEvent()->getResponse();

        $pageInfoHtml = '<script type="text/javascript">';
        $pageInfoHtml.= 'if (!window.mspDevTools) { window.mspDevTools = {}; }';
        foreach ($pageInfo as $key => $info) {
            $pageInfoHtml.='window.mspDevTools["' . $key . '"] = ' . Mage::helper('core')->jsonEncode($info) . ';';
        }
        $pageInfoHtml.='window.mspDevTools["_protocol"] = ' . MSP_DevTools_Helper_Data::PROTOCOL_VERSION . ';';
        $pageInfoHtml.= '</script>';

        $response->appendBody($pageInfoHtml);
    }
}
