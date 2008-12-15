<?php

/*
 * Copyright (c) Xerox, 2008. All Rights Reserved.
 *
 * Originally written by Marc Nazarian, 2008. Xerox Codendi Team.
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CodeX; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once('common/mvc/Views.class.php');
require_once('common/include/HTTPRequest.class.php');
require_once('common/user/UserManager.class.php');

require_once('HudsonJob.class.php');

/**
 * hudsonViews
 */
class hudsonViews extends Views {
    
    function hudsonViews(&$controler, $view=null) {
        $this->View($controler, $view);
    }
    
    function header() {
        $request =& HTTPRequest::instance();
        $GLOBALS['HTML']->header(array('title'=>$this->_getTitle(),'group' => $request->get('group_id'), 'toptab' => 'hudson'));
        echo '<h2>'.$this->_getTitle().'</h2>';
    }
    function _getTitle() {
        return $GLOBALS['Language']->getText('plugin_hudson','title');
    }
    function footer() {
        $GLOBALS['HTML']->footer(array());
    }
    
    // {{{ Views
    function projectOverview() {
        $request =& HTTPRequest::instance();
        $group_id = $request->get('group_id');
        $user = UserManager::instance()->getCurrentUser();
        
        $job_dao = new PluginHudsonJobDao(CodexDataAccess::instance());
        $dar = $job_dao->searchByGroupID($group_id);
        
        if ($dar && $dar->valid()) {

            echo '<table>';
            echo ' <tr>';
            echo '  <th>&nbsp;</th>';
            echo '  <th>'.$GLOBALS['Language']->getText('plugin_hudson','header_table_job').'</th>';
            echo '  <th>'.$GLOBALS['Language']->getText('plugin_hudson','header_table_lastsuccess').'</th>';
            echo '  <th>'.$GLOBALS['Language']->getText('plugin_hudson','header_table_lastfailure').'</th>';
            echo '  <th>'.$GLOBALS['Language']->getText('plugin_hudson','header_table_rss').'</th>';
            if ($user->isMember($request->get('group_id'), 'A')) {
                echo '  <th>'.$GLOBALS['Language']->getText('plugin_hudson','header_table_actions').'</th>';
            }
            echo ' </tr>';
        
            while ($dar->valid()) {
                $row = $dar->current();

                $job = new HudsonJob($row['job_url']);
                $job->buildJobObject();
                
                echo ' <tr>';
                echo '  <td><img src="'.$job->getStatusIcon().'" alt="'.$job->getStatus().'" title="'.$job->getStatus().'" /></td>';
                echo '  <td><a href="'.$job->getUrl().'">'.$job->getName().'</a></td>';
                if ($job->getLastSuccessfulBuildNumber() != '') {
                    echo '  <td><a href="'.$job->getLastSuccessfulBuildUrl().'">build #'.$job->getLastSuccessfulBuildNumber().'</a></td>';
                } else {
                    echo '  <td>&nbsp;</td>';
                }
                if ($job->getLastFailedBuildNumber() != '') {
                    echo '  <td><a href="'.$job->getLastFailedBuildUrl().'">build #'.$job->getLastFailedBuildNumber().'</a></td>';
                } else {
                    echo '  <td>&nbsp;</td>';
                }
                echo '  <td><a href="'.$job->getUrl().'/rssAll"><img src="'.$this->getControler()->getIconsPath().'rss_feed.png" alt="" title=""></a></td>';
                if ($user->isMember($request->get('group_id'), 'A')) {
                    echo '  <td><a href="?action=edit_job&group_id='.$group_id.'&job_id='.$row['job_id'].'">'.$GLOBALS['HTML']->getimage('ic/edit.png').'</a><a href="?action=delete_job&group_id='.$group_id.'&job_id='.$row['job_id'].'">'.$GLOBALS['HTML']->getimage('ic/cross.png').'</a></td>';
                }
                echo ' </tr>';
                
                $dar->next();
            }
            
            echo '</table>';
            
        }
        
        if ($user->isMember($request->get('group_id'), 'A')) {
            echo '<a href="#" onclick="';
            echo "Effect.toggle('hudson_add_job', 'slide'); return false;";
            echo '">' . $GLOBALS["HTML"]->getimage("ic/add.png") . ' Add job</a>';
            echo '<div id="hudson_add_job">';
            echo ' <form>';
            echo '  <p>';
            echo '   <label for="hudson_job_url">Job URL:</label>';
            echo '   <input id="hudson_job_url" name="hudson_job_url" type="text" />';
            echo '  </p>';
            echo '  <p>';
            echo '   <input type="hidden" name="group_id" value="'.$group_id.'" />';
            echo '   <input type="hidden" name="action" value="add_job" />';
            echo '   <input type="submit" value="Add job" />';
            echo '  </p>';
            echo ' </form>';
            echo '</div>';
            echo "<script>Element.toggle('hudson_add_job', 'slide');</script>";
        }
    }
    // }}}
}


?>