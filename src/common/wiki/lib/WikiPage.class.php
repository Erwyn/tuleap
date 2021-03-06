<?php
/* 
 * Copyright 2005, STMicroelectronics
 *
 * Originally written by Manuel Vacelet
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
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */
require_once('WikiPageWrapper.class.php');
require_once('www/project/admin/permissions.php');

/**
 * Codendi manipulation of WikiPages
 *
 * This class is Codendi representation of wiki_page table in database.
 *
 * @package WikiService
 * @copyright STMicroelectronics, 2005
 * @author Manuel Vacelet <manuel.vacelet-abecedaire@st.com>
 * @license http://opensource.org/licenses/gpl-license.php GPL
 */
class WikiPage {
  /* private int */   var $id;       /* wiki_page.id */
  /* private string*/ var $pagename; /* wiki_page.pagename */
  /* private int */   var $gid;      /* wiki_page.group_id */
  /* private bool */  var $empty;    /* */

  /*
   * Constructor
   */
  function WikiPage($id=0, $pagename='') {
    $this->empty=null;

    if($id != 0) {
      if(empty($pagename)) {
	//Given number is the WikiPage id from wiki_page table
	$this->id = (int) $id;
	$this->_initFromDb();
      }
      else {
	//Given number is group_id from wiki_page table
	$this->gid      = (int) $id;
	$this->pagename = $pagename;
	$this->_findPageId();
      }
    }
    else {
      $this->id=0;
      $this->pagename='';
      $this->gid=0;
    }
  }
  
  /**
   * @access private
   */
  function _findPageId() {
    $res = db_query(' SELECT id FROM wiki_page'.
		    ' WHERE group_id="'.$this->gid.'"'.
		    ' AND pagename="'.addslashes($this->pagename).'"');
    if(db_numrows($res) > 1) {
        exit_error($GLOBALS['Language']->getText('global','error'), 
                   $GLOBALS['Language']->getText('wiki_lib_wikipage', 
                                                 'notunique_err'));
    }
    $row = db_fetch_array($res);
    $this->id =  $row['id'];
  } 


  /**
   * @access private
   */
  function _initFromDb() {
    $res = db_query(' SELECT id, pagename, group_id FROM wiki_page'.
		    ' WHERE id="'.$this->id.'"');
    if(db_numrows($res) > 1) {
        exit_error($GLOBALS['Language']->getText('global','error'), 
                   $GLOBALS['Language']->getText('wiki_lib_wikipage', 
                                                 'notunique_err'));
    }
    $row = db_fetch_array($res);

    $this->gid =  $row['group_id'];
    $this->pagename =  $row['pagename'];
  }


  /**
   * @todo transfer to Wrapper
   */
  function isEmpty() {
    // If this value is already computed, return now !
    if($this->empty != null) {
      return $this->empty;
    }
  
    // Else compute
    $this->empty=true;
    if($this->exist()) {
      $res = db_query(' SELECT wiki_page.id'
		      .' FROM wiki_page, wiki_nonempty'
		      .' WHERE wiki_page.group_id="'.$this->gid.'"'
		      .' AND wiki_page.id="'.$this->id.'"'
		      .' AND wiki_nonempty.id=wiki_page.id');
      if(db_numrows($res) == 1) {
	$this->empty = false;
      }
    }

    return $this->empty;
  }


  /**
   * @access public
   */
  function permissionExist() {
    if (permission_exist('WIKIPAGE_READ', $this->id))
      return true;
    else
      return false;
  }


  /**
   * @access public
   */
    function isAutorized($uid) {            
        //Check for Docman Perms 
        $eM =& EventManager::instance();
        $referenced = false;
        $eM->processEvent('isWikiPageReferenced', array(
                        'referenced' => &$referenced,
                        'wiki_page'  => $this->pagename,
                        'group_id' => $this->gid
                        ));
        if($referenced == true) {
            $userCanAccess = false;
            $eM->processEvent('userCanAccessWikiDocument', array(
                            'canAccess' => &$userCanAccess,
                            'wiki_page'  => $this->pagename,
                            'group_id' => $this->gid
                            ));
            if(!$userCanAccess) {
                return false;
            }
        } else {
            // Check if user is authorized.
            if($this->permissionExist()) {
                if (!permission_is_authorized('WIKIPAGE_READ', $this->id, $uid, $this->gid)) {
                    return false;
                }
            }
        }
        return true;
    }

  
  /**
   *@access public
   */
  function setPermissions($groups) {
    global $feedback;

    list ($ret, $feedback) = permission_process_selection_form($this->gid, 
							       'WIKIPAGE_READ', 
							       $this->id, 
							       $groups);
    return $ret;
  }
  

  /**
   *@access public
   */
  function resetPermissions() {
    return permission_clear_all($this->gid, 
                                'WIKIPAGE_READ', 
                                $this->id);
  }
  

  /**
   * @todo transfer to Wrapper
   */
  function exist() {
    return($this->id != 0);
  }


  /**
   * @access public
   */
  function log($user_id) {
    $sql = "INSERT INTO wiki_log(user_id,group_id,pagename,time) "
          ."VALUES ('".$user_id."','".$this->gid."','".$this->pagename."','".time()."')";
    db_query($sql);
  }


  /**
   * @access public
   */
  function render($lite=false, $full_screen=false) {
    $wpw = new WikiPageWrapper($this->gid);
    $wpw->render($lite, $full_screen);
  }


  /**
   * @access public
   * @return int Page identifier
   */
  function getId() { 
    return $this->id; 
  }

  /**
   * @access public
   * @return string Page name
   */
  function getPagename() { 
    return $this->pagename; 
  }

  /**
   * @access public
   * @return int Group Identifier
   */
  function getGid() { 
    return $this->gid; 
  }

  /**
   * @access public
   * @return string[] List of pagename 
   */
  function &getAllAdminPages() {
    $WikiPageAdminPages = WikiPage::getAdminPages();
    
    $allPages = array();
    
    $res = db_query(' SELECT pagename'
		    .' FROM wiki_page, wiki_nonempty'
		    .' WHERE wiki_page.group_id="'.$this->gid.'"'
		    .' AND wiki_nonempty.id=wiki_page.id'
		    .' AND wiki_page.pagename IN ("'.implode('","', $WikiPageAdminPages).'")');
    while($row = db_fetch_array($res)) {
      $allPages[]=$row[0];
    }
    
    return $allPages;
  }


  /**
   * @access public
   * @return string[] List of pagename 
   */
  function &getAllInternalPages() {
    $WikiPageDefaultPages = WikiPage::getDefaultPages();
    
    $allPages = array();
    
    $res = db_query(' SELECT pagename'
		    .' FROM wiki_page, wiki_nonempty'
		    .' WHERE wiki_page.group_id="'.$this->gid.'"'
		    .' AND wiki_nonempty.id=wiki_page.id'
		    .' AND wiki_page.pagename IN ("'.implode('","', $WikiPageDefaultPages).'")');
    while($row = db_fetch_array($res)) {
      $allPages[]=$row[0];
    }
    
    return $allPages;
  }


  /**
   * @access public
   * @return string[] List of pagename 
   */
  function &getAllUserPages() {
    $WikiPageAdminPages = WikiPage::getAdminPages();
    $WikiPageDefaultPages = WikiPage::getDefaultPages();

    $allPages = array();
    
    $res = db_query(' SELECT pagename'
		    .' FROM wiki_page, wiki_nonempty'
		    .' WHERE wiki_page.group_id="'.$this->gid.'"'
		    .' AND wiki_nonempty.id=wiki_page.id'
		    .' AND wiki_page.pagename NOT IN ("'.implode('","', $WikiPageDefaultPages).'",
                                                      "'.implode('","', $WikiPageAdminPages).'")');
    while($row = db_fetch_array($res)) {
      $allPages[]=$row[0];
    }

    return $allPages;
  }

  /**
   * List all default PhpWiki pages
   *
   * Following list include all pages (excepted Admin pages) created by PhpWiki
   * out-of-the-box during initialisation.
   *
   * @static
   * @access public
   * @return string[] List of pagename 
   */
    function getDefaultPages() {
        return array
            ( // Plugin documentation pages
             "AddCommentPlugin","AppendTextPlugin","AuthorHistoryPlugin"
             ,"CalendarListPlugin","CommentPlugin","CreatePagePlugin"
             ,"CreateTocPlugin","EditMetaDataPlugin","FrameIncludePlugin"
             ,"HelloWorldPlugin","IncludePagePlugin","ListPagesPlugin"
             ,"PhotoAlbumPlugin","PhpHighlightPlugin","RedirectToPlugin"
             ,"RichTablePlugin","RssFeedPlugin","SearchHighlightPlugin"
             ,"SyntaxHighlighterPlugin","TemplateExample","TemplatePlugin"
             ,"TranscludePlugin","UnfoldSubpagesPlugin","WikiBlogPlugin"
             ,"CalendarPlugin","PhpWikiPoll"
             
             // Wiki doc page
             ,"WikiPlugin","OldStyleTablePlugin","OldTextFormattingRules"
             ,"PhpWikiDocumentation","TextFormattingRules"

             // Action Pages
             ,"DebugInfo","AppendText","CreatePage","EditMetaData","LikePages"
             ,"PluginManager","SearchHighlight","UpLoad","AllPages","BackLinks"
             ,"FindPage","FullRecentChanges","FullTextSearch","FuzzyPages"
             ,"InterWikiMap","InterWikiSearch","MostPopular","OrphanedPages"
             ,"PageDump","PageHistory","PageInfo","RandomPage","RecentChanges"
             ,"RecentComments","RecentEdits","RecentReleases","RelatedChanges"
             ,"TitleSearch","UserPreferences","WantedPages"

             // Collection Pages
             ,"CategoryCategory","CategoryGroup"

             // French pages
             ,"PluginCommenter" ,"CréerUnePage" ,"DéposerUnFichier" ,"DernièresModifsComplètes"
             ,"AjouterDesCommentaires" ,"AjouterDesPages" ,"AliasAccueil"
             ,"AnciennesRèglesDeFormatage" ,"ÉditerLeContenu" ,"ÉditionsRécentes"
             ,"CarteInterWiki" ,"CatégorieCatégorie " ,"CatégoriePagesAccueil"
             ,"ChangementsLiés" ,"ChercherUnePage" ,"ClassezLa" ,"CommentairesRécents" 
             ,"CommentUtiliserUnWiki" ,"DerniersVisiteurs" ,"DocumentationDePhpWiki" 
             ,"EditerLesMetaDonnées" ,"GestionDesPlugins" ,"HistoriqueDeLaPage" 
             ,"IcônesDeLien" ,"InfosAuthentification" ,"InfosDeDéboguage" ,"InfosSurLaPage"
             ,"InterWiki" ,"JoindreUnFichier" ,"LesPlusVisitées" ,"LienGoogle" 
             ,"ListeDePages" ,"ModifsRécentesPhpWiki" ,"NotesDeVersion" ,"PageAléatoire" 
             ,"PagesRecherchées" ,"PagesSemblables" ,"PhpWiki" ,"PierrickMeignen" ,"PluginAlbumPhotos"
             ,"PluginBeauTableau" ,"PluginBonjourLeMonde" ,"PluginÉditerMetaData" 
             ,"PluginCalendrier" ,"PluginColorationPhp" ,"PluginCréerUnePage" 
             ,"PluginCréerUneTdm" ,"PluginHistoriqueAuteur" ,"PluginHtmlPur" 
             ,"PluginInclureUnCadre" ,"PluginInclureUnePage" ,"PluginListeDesSousPages" 
             ,"PluginListeDuCalendrier" ,"PluginMétéoPhp" ,"PluginRechercheExterne"
             ,"PluginRedirection" ,"PluginRessourcesRss" ,"PluginTableauAncienStyle" 
             ,"PluginTeX2png" ,"PluginWiki" ,"PluginWikiBlog" ,"PréférencesUtilisateurs" 
             ,"QuiEstEnLigne" ,"RèglesDeFormatageDesTextes" ,"RécupérationDeLaPage"
             ,"RétroLiens" ,"RechercheEnTexteIntégral" ,"RechercheInterWiki"
             ,"RechercheParTitre" ,"SommaireDuProjet" ,"TestDeCache" ,"TestGroupeDePages"
             ,"TestGroupeDePages/Deux" ,"TestGroupeDePages/Trois" ,"TestGroupeDePages/Un" 
             ,"TousLesUtilisateurs" ,"ToutesLesPages" ,"TraduireUnTexte" 
             ,"URLMagiquesPhpWiki" ,"VersionsRécentes" ,"VisiteursRécents"
             ,"WabiSabi" ,"WikiWikiWeb" ,"DernièresModifs" ,"CatégorieGroupes" 
             ,"SteveWainstead" ,"PluginInsérer" ,"StyleCorrect" ,"DétailsTechniques" 
             ,"PagesFloues" ,"PluginInfosSystème", "PagesOrphelines" ,"SondagePhpWiki"
             
             // Old projects initialised their wiki with the old set of internal pages (pgsrc folder)
             // In the current version of PHPWiki, we initialise wiki with a different folder. 
             // The following pages are added in order not to consider them as user pages.
             ,"AddingPages", "AllUsers","TranslateText","WhoIsOnline"
             ,"_AuthInfo","CategoryHomePages","EditText","ExternalSearchPlugin"
             ,"GoodStyle","GoogleLink","HowToUseWiki","LinkIcons"
             ,"MagicPhpWikiURLs","MoreAboutMechanics","NewMarkupTestPage"
             ,"OldMarkupTestPage","PageGroupTest","PageGroupTest/One"
             ,"PageGroupTest/Two","PageGroupTest/Three","PageGroupTest/Four"
             ,"PgsrcRefactoring","PgsrcTranslation","PhpWikiRecentChanges"
             ,"ProjectSummary","RecentVisitors","ReleaseNotes","SystemInfoPlugin"
             ,"HomePageAlias","PhpWeatherPlugin","RateIt","RawHtmlPlugin"
             
             );
  }

  /**
   * List all PhpWiki Admin pages 
   *
   * @see getDefaultPages
   * @static
   * @access public
   * @return string[] List of pagename 
   */
  function getAdminPages() {
    return array
      ("HomePage" ,"PhpWikiAdministration","WikiAdminSelect"
       ,"PhpWikiAdministration/Remove"
       ,"PhpWikiAdministration/Rename", "PhpWikiAdministration/Replace"
       ,"PhpWikiAdministration/Chmod","PhpWikiAdministration/Chown"
       ,"PhpWikiAdministration/SetAcl" ,"SandBox", "ProjectWantedPages",
       
       "PageAccueil" ,"AdministrationDePhpWiki","AdministrationDePhpWiki/Supprimer"
       ,"AdministrationDePhpWiki/Remplacer"
       ,"AdministrationDePhpWiki/Renommer", "AdministrationDePhpWiki/Droits"
       ,"BacÀSable",);
  }
}
?>