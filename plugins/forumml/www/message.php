<?php
/**
 * Copyright (c) STMicroelectronics, 2005. All Rights Reserved.
 *
 * Originally written by Jean-Philippe Giola, 2005
 *
 * This file is a part of codendi.
 *
 * codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with codendi; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * $Id$
 */

/*
 * ForumML Archives Browsing page
 *  
 */

require_once('pre.php');
require_once('forumml_utils.php');
require_once('www/mail/mail_utils.php');
require_once('common/mail/Mail.class.php');
require_once('common/plugin/PluginManager.class.php');
require_once(dirname(__FILE__).'/../include/ForumML_FileStorage.class.php');
require_once(dirname(__FILE__).'/../include/ForumML_HTMLPurifier.class.php');

$plugin_manager =& PluginManager::instance();
$p =& $plugin_manager->getPluginByName('forumml');
if ($p && $plugin_manager->isPluginAvailable($p) && $p->isAllowed()) {

	$request =& HTTPRequest::instance();
	
	$vGrp = new Valid_UInt('group_id');
	$vGrp->required();
	if ($request->valid($vGrp)) {		
		$group_id = $request->get('group_id');
	} else {
		$group_id = "";
	}
	
	$vTopic = new Valid_UInt('topic');
	$vTopic->required();
	if ($request->valid($vTopic)) {
		$topic = $request->get('topic');
	} else {
		$topic = 0;
	}
	
	$vOff = new Valid_UInt('offset');
	$vOff->required();
	if ($request->valid($vOff)) {
		$offset = $request->get('offset');
	} else {
		$offset = 0;
	}
			
	// Checks 'list' parameter
	$vList = new Valid_UInt('list');
	$vList->required();
	if (! $request->valid($vList)) {
		exit_error($GLOBALS["Language"]->getText('global','error'),$GLOBALS["Language"]->getText('plugin_forumml','specify_list'));
	} else {
		$list_id = $request->get('list');
		if (!user_isloggedin() || (!mail_is_list_public($list_id) && !user_ismember($group_id))) {
			exit_error($GLOBALS["Language"]->getText('global','error'),$GLOBALS["Language"]->getText('include_exit','no_perm'));
		}		
		if (!mail_is_list_active($list_id)) {
			exit_error($GLOBALS["Language"]->getText('global','error'),$GLOBALS["Language"]->getText('plugin_forumml','wrong_list'));
		}
	}

	// If the list is private, search if the current user is a member of that list. If not, permission denied
	$list_name = mail_get_listname_from_list_id($list_id);
	if (!mail_is_list_public($list_id)) {
 		exec("{$GLOBALS['mailman_bin_dir']}/list_members ".$list_name,$members);
 		$user = user_getemail(user_getid());
 		if (! in_array($user,$members)) {
 			exit_permission_denied();
 		}
	}

	// Build the mail to be sent
	$vSrep = new Valid_WhiteList('send_reply',array('Submit'));
	$vSrep->required();
	if ($request->valid($vSrep)) {
		// process the mail
		$ret = process_mail($p,true);
		if ($ret) {
			$GLOBALS['Response']->addFeedback('warning', $GLOBALS['Language']->getText('plugin_forumml','delay_redirection',array($p->getThemePath()."/images/ic/spinner-greenie.gif",$group_id,$list_id,$topic)), CODENDI_PURIFIER_DISABLED);
		}
	}
	$vRep = new Valid_WhiteList('reply',array('1'));
	$vRep->required();
	if ($request->valid($vRep)) {	
		$GLOBALS['Response']->addFeedback('warning', $GLOBALS['Language']->getText('plugin_forumml','warn_post_without_confirm'));
	}
	$params['title'] = util_get_group_name_from_id($group_id).' - ForumML';
	$params['group'] = $group_id;
	$params['toptab']='mail';
	$params['help'] = "CommunicationServices.html#MailingLists";
	if ($request->valid(new Valid_Pv('pv'))) {
		$params['pv'] = $request->get('pv');
	}
	mail_header($params);

	if ($request->valid($vSrep) && $request->valid($vTopic)) {
		if (isset($ret) && $ret) {
			// wait few seconds before redirecting to archives page
			echo "<script> setTimeout('window.location=\"/plugins/forumml/message.php?group_id=".$group_id."&list=".$list_id."&topic=".$topic."\"',3000) </script>";
		}		
	}

	$list_link = '<a href="/plugins/forumml/message.php?group_id='.$group_id.'&list='.$list_id.'">'.$list_name.'</a>';
	echo '<H2><b>'.$GLOBALS['Language']->getText('plugin_forumml','list_arch',array($list_link)).'</b></H2>';

	if (! $request->exist('pv') || ($request->exist('pv') && $request->get('pv') == 0)) {
		echo "<table border=0 width=100%>
		<tr>
			<td align='left'>
				<a href='/plugins/forumml/index.php?group_id=".$group_id."&list=".$list_id."'>
					[".$GLOBALS['Language']->getText('plugin_forumml','post_thread')."]
				</a>
			</td>
			<td align='right'>
				(<a href='/plugins/forumml/message.php?group_id=".$group_id."&list=".$list_id."&topic=".$topic."&offset=".$offset."&search=".($request->exist('search') ? $request->get('search') : "")."&pv=1'>
					<img src='".util_get_image_theme("msg.png")."' border='0'>&nbsp;".$GLOBALS['Language']->getText('global','printer_version')."
				</a>)
			</td>
		</tr>
		</table><br>";
	}

	$vSrch = new Valid_String('search');
	$vSrch->required();
	if (! $request->valid($vSrch)) {
		// Check if there are archives to browse
		$qry = sprintf('SELECT NULL'.
						' FROM plugin_forumml_message'.
						' WHERE id_list = %d',
						db_ei($list_id));
		$res = db_query($qry);
		if (db_numrows($res) > 0) {
			// Call to show_thread() function to display the archives			
			if (isset($topic) && $topic != 0) {
				// specific thread
				$child_array = get_thread_children($topic);
				$qry = sprintf('SELECT id_message, id_parent, body'.
					 			' FROM plugin_forumml_message'.
	 							' WHERE id_list = %d'.
								' AND (id_parent IN (%s) OR id_message = %d)'.
								' ORDER BY id_message',
								db_ei($list_id),db_es(implode(",",$child_array)),db_ei($topic));
				$result = db_query($qry);				
				show_thread($p,$list_id,$topic,$result,$offset);
			} else {
				// all threads
				$qry = sprintf('SELECT id_message, body'.
			 					' FROM plugin_forumml_message'.
	 							' WHERE id_list = %d'.
								' AND id_parent = 0'.
								' ORDER BY id_message DESC',
								db_ei($list_id));
				$result = db_query($qry);								
				show_thread($p,$list_id,$topic,$result,$offset);
			}	
		} else {
			echo "<H2>".$GLOBALS["Language"]->getText('plugin_forumml','empty_archives')."</H2>";
		}
	} else {
		// search archives		
		$pattern = "%".$request->get('search')."%";
		$sql = sprintf('SELECT mh.id_message, mh.value'.
						' FROM plugin_forumml_message m, plugin_forumml_messageheader mh'.
						' WHERE mh.id_header = %s'.
						' AND m.id_list = %d'.
						' AND m.id_parent = 0'.
						' AND m.id_message = mh.id_message'.
						' AND mh.value LIKE "%s"',
						FORUMML_SUBJECT,db_ei($list_id),db_es($pattern));
		$result = db_query($sql);
		echo "<H3>".$GLOBALS['Language']->getText('plugin_forumml','search_result',$request->get('search'))." (".db_numrows($result)." ".$GLOBALS["Language"]->getText('plugin_forumml','found').")</H3>";
		if (db_numrows($result) > 0) {
			show_search_results($p,$result,$group_id,$list_id);
		}
	}

	mail_footer($params);

} else {
	header('Location: '.get_server_url());
}

?>