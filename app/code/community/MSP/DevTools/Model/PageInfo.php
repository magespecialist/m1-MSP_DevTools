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

class MSP_DevTools_Model_PageInfo
{
    /**
     * Get profiler information
     * @return array
     */
    protected function _getProfilerInfo()
    {
        $profilerInfo = Varien_Profiler::getTimers();

        // Calculate proper timers
        foreach ($profilerInfo as $timerId => $timerInfo) {
            $parentPath = $timerInfo['path'];
            // @codingStandardsIgnoreStart
            if (count($parentPath) > 1) {
                // @codingStandardsIgnoreEnd
                array_pop($parentPath);
                $parentTimerId = md5(implode('/', $parentPath));

                $profilerInfo[$parentTimerId]['proper_time'] -= $timerInfo['time'];
            }
        }

        foreach ($profilerInfo as $timerId => $timerInfo) {
            $profilerInfo[$timerId]['proper_time'] = max(0, intval(1000 * $profilerInfo[$timerId]['proper_time']));
            $profilerInfo[$timerId]['time'] = max(0, intval(1000 * $profilerInfo[$timerId]['time']));
        }

        return $profilerInfo;
    }

    /**
     * Get page information
     * @return array
     */
    public function getPageInfo()
    {
        $layout = Mage::app()->getLayout();
        $request = Mage::app()->getRequest();
        $design = Mage::getDesign();

        $fullActionName = implode('_', array(
            $request->getRequestedRouteName(),
            $request->getRequestedControllerName(),
            $request->getRequestedActionName(),
        ));

        $info = array(
            'general' => array(
                array(
                    'id' => 'version',
                    'label' => 'Version',
                    'value' => Mage::getVersion(),
                ), array(
                    'id' => 'request',
                    'label' => 'Request',
                    'value' => $request->getParams(),
                    'type' => 'complex',
                ), array(
                    'id' => 'action',
                    'label' => 'Action',
                    'value' => $fullActionName,
                ), array(
                    'id' => 'module',
                    'label' => 'Module',
                    'value' => $request->getModuleName(),
                ), array(
                    'id' => 'path_info', 'label' => 'Path Info',
                    'value' => $request->getPathInfo(),
                ), array(
                    'id' => 'original_path_info', 'label' => 'Original Path Info',
                    'value' => $request->getOriginalPathInfo(),
                ), array(
                    'id' => 'locale', 'label' => 'Locale',
                    'value' => Mage::getStoreConfig('general/locale/code'),
                ),
            ),
            'design' => array(
                array(
                    'id' => 'handles',
                    'label' => 'Layout Handles',
                    'value' => $layout->getUpdate()->getHandles(),
                    'type' => 'complex'
                ), array(
                    'id' => 'package',
                    'label' => 'Package',
                    'value' => $design->getPackageName(),
                ), array(
                    'id' => 'theme_code',
                    'label' => 'Theme Code',
                    'value' => $design->getTheme('frontend')
                ),
            ),
            'blocks' => Mage::getSingleton('msp_devtools/elementRegistry')->getRegisteredOps(),
            'profiler' => $this->_getProfilerInfo(),
            'events' => Mage::getSingleton('msp_devtools/eventRegistry')->getRegisteredOps(),
            'version' => 1,
        );

        return $info;
    }
}
