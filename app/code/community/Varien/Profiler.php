<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Varien
 * @package     Varien_Profiler
 * @copyright  Copyright (c) 2006-2016 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


// Based on Magento original profiler

class Varien_Profiler
{
    // MSP HACK: Start
    static private $_stack = array();
    static private $_eventRegistry = null;
    // MSP HACK: End

    /**
     * Timers for code profiling
     *
     * @var array
     */
    static private $_timers = array();
    static private $_enabled = false;
    static private $_memory_get_usage = false;

    public static function enable()
    {
        self::$_enabled = true;
        self::$_memory_get_usage = function_exists('memory_get_usage');
    }

    public static function disable()
    {
        self::$_enabled = false;
    }

    public static function reset($timerName)
    {
        self::$_timers[$timerName] = array(
            'start'=>false,
            'count'=>0,
            'sum'=>0,
            'realmem'=>0,
            'emalloc'=>0,
        );
    }

    // MSP HACK: Start
    public static function getTimerId($stack = null)
    {
        if (is_null($stack)) {
            $stack = self::$_stack;
        }

        return md5(implode('/', $stack));
    }
    // MSP HACK: End

    protected static function _getObservers($area, $eventName)
    {
        if (!Mage::app()->getConfig()->getNode($area)) {
            return array();
        }

        $observers = array();
        $observersConfig = Mage::app()->getConfig()->getEventConfig($area, $eventName);

        if ($observersConfig) {
            foreach ($observersConfig->observers->children() as $obsName => $obsConfig) {
                $class = $obsConfig->class ? (string)$obsConfig->class : $obsConfig->getClassName();
                $observers[$obsName] = $class.'::'.(string)$obsConfig->method;
            }
        }

        return $observers;
    }

    // MSP HACK: Start
    protected static function _getEventRegistry()
    {
        if (!self::$_eventRegistry) {
            $singletonKey = '_singleton/msp_devtools/eventRegistry';
            if (!Mage::registry($singletonKey)) {
                self::$_eventRegistry = new MSP_DevTools_Model_EventRegistry();
                Mage::register($singletonKey, self::$_eventRegistry);
            }
        }

        return self::$_eventRegistry;
    }
    // MSP HACK: End

    public static function resume($timerName)
    {
        if (preg_match('/DISPATCH EVENT:(.+)/', $timerName, $matches)) {
            $eventName = strtolower($matches[1]);
            self::_getEventRegistry()->start($eventName);
        }

        if (!self::$_enabled) {
            return;
        }

        self::$_stack[] = $timerName;
        $originalName = $timerName;
        $timerName = self::getTimerId();
        // MSP HACK: End

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (self::$_memory_get_usage) {
            self::$_timers[$timerName]['realmem_start'] = memory_get_usage(true);
            self::$_timers[$timerName]['emalloc_start'] = memory_get_usage();
        }
        self::$_timers[$timerName]['start'] = microtime(true);
        self::$_timers[$timerName]['count'] ++;

        // MSP HACK: Start
        self::$_timers[$timerName]['name'] = $originalName;
        // MSP HACK: End
    }

    public static function start($timerName)
    {
        self::resume($timerName);
    }

    public static function pause($timerName)
    {
        // MSP HACK: Start
        if (preg_match('/DISPATCH EVENT:(.+)/', $timerName, $matches)) {
            $eventName = strtolower($matches[1]);
            $area = 'frontend';

            // Retrieve called observer
            $observers = self::_getObservers($area, $eventName);
            $observers = array_merge($observers, self::_getObservers('global', $eventName));

            self::_getEventRegistry()->stop($matches[1], array(
                'observers' => $observers,
            ));
        }

        if (!self::$_enabled) {
            return;
        }
        // MSP HACK: End

        if ((count(self::$_stack) > 1) && ($timerName != self::$_stack[count(self::$_stack) - 1])) {
            return;
        }

        // MSP HACK: Start
        $timerName = self::getTimerId();
        // MSP HACK: End

        $time = microtime(true); // Get current time as quick as possible to make more accurate calculations

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (false!==self::$_timers[$timerName]['start']) {
            self::$_timers[$timerName]['sum'] += $time-self::$_timers[$timerName]['start'];
            self::$_timers[$timerName]['start'] = false;
            if (self::$_memory_get_usage) {
                self::$_timers[$timerName]['realmem'] += memory_get_usage(true)-self::$_timers[$timerName]['realmem_start'];
                self::$_timers[$timerName]['emalloc'] += memory_get_usage()-self::$_timers[$timerName]['emalloc_start'];
            }
        }

        // MSP HACK: Start
        self::$_timers[$timerName]['path'] = self::$_stack;
        self::$_timers[$timerName]['time'] = self::$_timers[$timerName]['sum'];
        self::$_timers[$timerName]['proper_time'] = self::$_timers[$timerName]['sum'];
        array_pop(self::$_stack);
        // MSP HACK: End
    }

    public static function stop($timerName)
    {
        self::pause($timerName);
    }

    public static function fetch($timerName, $key = 'sum')
    {
        if (empty(self::$_timers[$timerName])) {
            return false;
        } elseif (empty($key)) {
            return self::$_timers[$timerName];
        }
        switch ($key) {
            case 'sum':
                $sum = self::$_timers[$timerName]['sum'];
                if (self::$_timers[$timerName]['start']!==false) {
                    $sum += microtime(true)-self::$_timers[$timerName]['start'];
                }
                return $sum;

            case 'count':
                $count = self::$_timers[$timerName]['count'];
                return $count;

            case 'realmem':
                if (!isset(self::$_timers[$timerName]['realmem'])) {
                    self::$_timers[$timerName]['realmem'] = -1;
                }
                return self::$_timers[$timerName]['realmem'];

            case 'emalloc':
                if (!isset(self::$_timers[$timerName]['emalloc'])) {
                    self::$_timers[$timerName]['emalloc'] = -1;
                }
                return self::$_timers[$timerName]['emalloc'];

            default:
                if (!empty(self::$_timers[$timerName][$key])) {
                    return self::$_timers[$timerName][$key];
                }
        }
        return false;
    }

    public static function getTimers()
    {
        return self::$_timers;
    }

    /**
     * Output SQl Zend_Db_Profiler
     *
     */
    public static function getSqlProfiler($res)
    {
        if (!$res) {
            return '';
        }
        $out = '';
        $profiler = $res->getProfiler();
        if ($profiler->getEnabled()) {
            $totalTime    = $profiler->getTotalElapsedSecs();
            $queryCount   = $profiler->getTotalNumQueries();
            $longestTime  = 0;
            $longestQuery = null;

            foreach ($profiler->getQueryProfiles() as $query) {
                if ($query->getElapsedSecs() > $longestTime) {
                    $longestTime  = $query->getElapsedSecs();
                    $longestQuery = $query->getQuery();
                }
            }

            $out .= 'Executed ' . $queryCount . ' queries in ' . $totalTime . ' seconds' . "<br>";
            $out .= 'Average query length: ' . $totalTime / $queryCount . ' seconds' . "<br>";
            $out .= 'Queries per second: ' . $queryCount / $totalTime . "<br>";
            $out .= 'Longest query length: ' . $longestTime . "<br>";
            $out .= 'Longest query: <br>' . $longestQuery . "<hr>";
        }
        return $out;
    }
}
