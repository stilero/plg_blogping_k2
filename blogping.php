<?php

/**
 * Description of BlogPingK2
 *
 * @version  1.1
 * @author Daniel Eliasson <joomla at stilero.com>
 * @copyright  (C) 2012-jan-06 Stilero Webdesign http://www.stilero.com
 * @category Plugins
 * @license	GPLv2
 * 
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * 
 * This file is part of blogpingk2.
 * 
 * BlogPingK2 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * BlogPingK2 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with BlogPingK2.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

// Load the K2 Plugin API
JLoader::register('K2Plugin', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_k2'.DS.'lib'.DS.'k2plugin.php');

class plgK2Blogping extends JPlugin {

    var $config;
    var $errorOccured;
    var $inBackend;
    var $articleObj;
    var $CheckClass;
    var $PingClass;
    var $pingResults = false;
    var $pingServers = array();
    const E_NOTICE      = '1';
    const E_WARNING     = '2';
    const E_NOPRESPOND  = '3';
    const E_FATAL       = '4';
    const E_NOTFOUND    = '404';
    const E_REQTIMEOUT  = '408';

    function plgK2Blogping(&$subject, $config) {
        parent::__construct($subject, $config);
        $language = JFactory::getLanguage();
        $language->load('plg_k2_blogping', JPATH_ADMINISTRATOR, 'en-GB', true);
        $language->load('plg_k2_blogping', JPATH_ADMINISTRATOR, null, true);
        $this->errorOccured = FALSE;
        $this->config = array(
            'shareLogTableName'     =>      '#__blogpingk2_log',
            'shareDelay'            =>      $this->params->def('delay'),
            'pluginLangPrefix'      =>      "PLG_K2_BLOGPING_",
            'classfolder'           =>      'blogpingk2classes',
            'categoriesToShare'     =>      $this->params->def('catID'),
            'pingServers'           =>      $this->params->def('pingServers'),
            'timeout'               =>      $this->params->def('timeout'),
            'extendedPing'          =>      $this->params->def('extendedPing'),
            'rssurl'                =>      $this->params->def('rssurl')

        );        
    }

    public function onAfterK2Save( &$item, $isNew ) {
        $this->inBackend = true;
        $this->setupClasses();
        $articleObject = $this->getArticleObjectFromK2Item($item);
        $this->CheckClass->setArticleObject($articleObject);
        $this->doPing();
        $this->handleResults();
        return;
    }

    public function onK2AfterDisplayContent( & $item, & $params, $limitstart) {
        $this->inBackend = FALSE;
        $this->setupClasses();
        $articleObject = $this->getArticleObjectFromK2Item($item);
        $this->CheckClass->setArticleObject($articleObject);
        $this->doPing();
        $this->handleResults();
        return;
    }
    
    public function setupClasses() {
        //Load the classes
        $classFolder = $this->config['classfolder'];
        JLoader::register('stlK2BlogPingClass', dirname(__FILE__).DS.$classFolder.DS.'stlK2BlogPingClass.php');
        JLoader::register('stileroBPK2pingClass', dirname(__FILE__).DS.$classFolder.DS.'stileroBPK2pingClass.php');
        JLoader::register('stlBPK2ShareControllerClass', dirname(__FILE__).DS.$classFolder.DS.'stlBPK2ShareControllerClass.php');
        $this->CheckClass = new stlK2BlogPingClass ( 
            array(
                'shareLogTableName'     =>      $this->config['shareLogTableName'],
                'pluginLangPrefix'      =>      $this->config['pluginLangPrefix'],
                'categoriesToShare'     =>      $this->config['categoriesToShare'],
                'shareDelay'            =>      $this->config['shareDelay'],
                'pingServers'           =>      $this->config['pingServers'],
                'timeout'               =>      $this->config['timeout'],
                'tryBothMethods'          =>    $this->config['extendedPing']
            )
        );
    }
    
    public function doPing() {
        if( !$this->isInitialChecksOK() ) {
            $this->displayMessage(JText::_($this->CheckClass->error['message']) , $this->CheckClass->error['type']);
            return;
        }
        $this->pingResults = $this->CheckClass->ping();
    }
    
    public function handleResults() {
        if( empty ($this->pingResults)){
            return;
        }
        $servers = $this->CheckClass->getPingServersArray();
        $success=false;
        $i = 0;
        foreach ($this->pingResults as $value) {
            switch ($value[0]) {
                case '0':
                   $this->displayMessage(JText::_($this->config['pluginLangPrefix'].'PING_DONE').$value[1]." (".$servers[$i].")");
                    $success = true;
                    break;
                case self::E_NOTICE :
                    $this->showNotice( JText::_($this->config['pluginLangPrefix'].'SERVER_NOTICE').$value[1]." (".$servers[$i].")", 1 );
                    break;
                case self::E_WARNING :
                    $this->showNotice( JText::_($this->config['pluginLangPrefix'].'SERVER_WARNING').$value[1]." (".$servers[$i].")", 1);
                    break;
                case self::E_REQTIMEOUT :
                    $this->showNotice( JText::_($this->config['pluginLangPrefix'].'CLIENT_TIMEOUT').$value[1]." (".$servers[$i].")", 1);
                    break;
                case self::E_NOPRESPOND :
                    $this->displayMessage(JText::_($this->config['pluginLangPrefix'].'SERVER_NORESPOND')." (".$servers[$i].")", 'error');
                    break;
                case self::E_NOTFOUND :
                    $this->displayMessage(JText::_($this->config['pluginLangPrefix'].'SERVER_NOTFOUND')." (".$servers[$i].")", 'error');
                    break;
                default:
                    $this->displayMessage($value[0]." : ".JText::_($this->config['pluginLangPrefix'].'SERVER_NOT_WORKING')." (".$servers[$i].")", 'error');
            }
            $i++;
        }    
        if($success) {
            $this->CheckClass->saveLogToDB();
        }
    }
               
    public function getArticleObjectFromK2Item($k2Item) {
        $joomlaConfig =& JFactory::getConfig();
        $joomlaSiteName = $joomlaConfig->getValue( 'config.sitename' );
        $articleObject = new stdClass();
        $articleObject->id = $k2Item->id;
        $articleObject->link = $k2Item->link;
        $articleObject->full_url = $this->getFullURL($k2Item->id);
        $articleObject->title = $k2Item->title;
        $articleObject->sitename = $joomlaSiteName;
        $articleObject->siteurl = JURI::root();
        $articleObject->rssfeed = $this->getRssUrl();
        $articleObject->catid = $k2Item->catid;
        $articleObject->access = $k2Item->access;
        $articleObject->tags = $this->getK2ItemTags($k2Item);
        $articleObject->publish_up = $k2Item->publish_up;
        $articleObject->published = $k2Item->published; 
        $this->articleObj = $articleObject;
        return $articleObject;
    }

    public function displayMessage($msg, $messageType = "") {
        $isSetToDisplayMessages = ($this->params->def('displayMessages')==0)?false:true;
        if( ! $isSetToDisplayMessages || ! $this->inBackend ){
            return;
        }else{
            JFactory::getApplication()->enqueueMessage( $msg, $messageType);
        }
    }
    
    public function showNotice($msg, $errorCode=0) {
        $isSetToDisplayMessages = ($this->params->def('displayMessages')==0)?false:true;
        if( ! $isSetToDisplayMessages || ! $this->inBackend ){
            return;
        }else{
             JError::raiseNotice( $errorCode, $msg );
        }
    }
    
    public function showWarning($msg, $errorCode=0) {
        $isSetToDisplayMessages = ($this->params->def('displayMessages')==0)?false:true;
        if( ! $isSetToDisplayMessages || ! $this->inBackend ){
            return;
        }else{
             JError::raiseWarning( $errorCode, $msg );
        }
    }

    private function doInitialChecks() {
        $this->CheckClass->isServerSupportingRequiredFunctions();
        $this->CheckClass->isPingServersEntered();
        $this->CheckClass->isArticleObjectIncluded();
        $this->CheckClass->isItemAlreadyShared();
        $this->CheckClass->isItemActive();
        $this->CheckClass->isItemPublished();
        $this->CheckClass->isItemPublic();
        $this->CheckClass->isCategoryToShare();
        $this->CheckClass->prepareTables();
        $this->CheckClass->isItemNewerThanLastPing();
        $this->CheckClass->isSharingToEarly(); 
        return $this->CheckClass->error;
    }

    public function isInitialChecksOK() {
        $errorMessage = $this->doInitialChecks();
        if ( $errorMessage != FALSE ) {
            return FALSE;
        }
        return TRUE;
    }
    
    public function getK2ItemTags($k2Item) {
        $query;
        $db = &JFactory::getDbo();
        if( $this->CheckClass->isJoomla16() || $this->CheckClass->isJoomla17()) {
            $query = $db->getQuery(true);
            $query->select('name');
            $query->from('#__k2_tags AS t');
            $query->innerJoin('#__k2_tags_xref AS x ON x.tagID = t.id');
            $query->where('x.itemID = '.(int) $k2ItemID->id);
        }  elseif( $this->CheckClass->isJoomla15() ) {
            $query = "SELECT ".$db->nameQuote('name').
                " FROM ".$db->nameQuote('#__k2_tags')." AS t".
                " INNER JOIN " . $db->nameQuote('#__k2_tags_xref')." AS x".
                " ON  x.tagID = t.id".
                " WHERE x.itemID = " . $db->Quote($k2ItemID->id);
        }
        $db->setQuery($query);
        $k2ItemTags = $db->loadResultArray ();
        $tagsString = strtolower( implode(",", $k2ItemTags) );
        $tagsWithoutSpaces = str_replace(" ", "", $tagsString);
        return $tagsWithoutSpaces;
    }
    
    public function getFullURL($articleID) {
        $urlQuery = "?option=com_k2&view=item&id=".$articleID;
        $fullURL = JURI::root()."index.php".$urlQuery;
        return $fullURL;
    }
    
    public function getRssUrl(){
        if($this->config['rssurl'] != ""){
           return $this->config['rssurl']; 
        }
        $urlQuery = "?format=feed&type=rss";
        $fullURL = JURI::root()."index.php".$urlQuery;
        return $fullURL;
    }

}

//End Class