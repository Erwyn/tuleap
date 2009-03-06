<?php
/**
 * @copyright Copyright (c) Xerox Corporation, CodeX, Codendi 2007-2008.
 *
 * This file is licensed under the GNU General Public License version 2. See the file COPYING.
 * 
 * @author Marc Nazarian <marc.nazarian@xrce.xerox.com>
 *
 * HudsonTestResult
 */

require_once('hudson.class.php');
require_once('HudsonJobURLMalformedException.class.php');
require_once('HudsonJobURLFileException.class.php');
require_once('HudsonJobURLFileNotFoundException.class.php');
 
class HudsonTestResult {

    protected $hudson_test_result_url;
    protected $dom_job;
    
    private $context;
    
    /**
     * Construct an Hudson job from a job URL
     */
    function HudsonTestResult($hudson_job_url) {
        $parsed_url = parse_url($hudson_job_url);
        
        if ( ! $parsed_url || ! array_key_exists('scheme', $parsed_url) ) {
            throw new HudsonJobURLMalformedException($GLOBALS['Language']->getText('plugin_hudson','wrong_job_url', array($hudson_job_url)));
        }
                
        $this->hudson_test_result_url = $hudson_job_url . "/lastBuild/testReport/api/xml/";
        
        $controler = $this->getHudsonControler(); 
        
        $this->_setStreamContext();
        
        $this->buildJobObject();
        
    }
    function getHudsonControler() {
        return new hudson();
    }
    
    public function buildJobObject() {
        $this->dom_job = $this->_getXMLObject($this->hudson_test_result_url);
    }
    
    protected function _getXMLObject($hudson_test_result_url) {
        $xmlstr = @file_get_contents($hudson_test_result_url, false, $this->context);
        if ($xmlstr !== false) {
            $xmlobj = simplexml_load_string($xmlstr);
            if ($xmlobj !== false) {
                return $xmlobj;
            } else {
                throw new HudsonJobURLFileException($GLOBALS['Language']->getText('plugin_hudson','job_url_file_error', array($hudson_test_result_url)));
            }
        } else {
            throw new HudsonJobURLFileNotFoundException($GLOBALS['Language']->getText('plugin_hudson','job_url_file_not_found', array($hudson_test_result_url))); 
        }
    }
    
    private function _setStreamContext() {
        if (array_key_exists('sys_proxy', $GLOBALS) && $GLOBALS['sys_proxy']) {
            $context_opt = array(
                'http' => array(
                    'method' => 'GET',
                    'proxy' => $GLOBALS['sys_proxy'],
                    'request_fulluri' => True,
                    'timeout' => 5.0,
                ),
            );
            $this->context = stream_context_create($context_opt);
        } else {
            $this->context = null;
        }
    }
    
    function getFailCount() {
        return $this->dom_job->failCount;
    }
    function getPassCount() {
        return $this->dom_job->passCount;
    }
    function getSkipCount() {
        return $this->dom_job->skipCount;
    }
    function getTotalCount() {
        return $this->getFailCount() + $this->getPassCount() + $this->getSkipCount();
    }
    
    function getTestResultPieChart() {
        return '<img class="test_result_pie_chart" src="/plugins/hudson/test_result_pie_chart.php?p='.$this->getPassCount().'&f='.$this->getFailCount().'&s='.$this->getSkipCount().'" alt="Test result: '.$this->getPassCount().'/'.$this->getTotalCount().'" title="Test result: '.$this->getPassCount().'/'.$this->getTotalCount().'" />';
    }
        
}

?>