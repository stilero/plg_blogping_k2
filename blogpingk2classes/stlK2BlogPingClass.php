<?php
/* BlogPingClass
 * 
 * This class handles all BlogPing specific controls and databasecommunication.
 * 
 * @version     $Id: stlBlogPingClass.php 2 2012-01-10 22:27:45Z webbochsant@gmail.com $
 * @author      Daniel Eliasson Stilero AB - http://www.stilero.com
 * @copyright	Copyright (c) 2011 Stilero AB. All rights reserved.
 * @license	GPLv3
* 	Joomla! is free software. This version may have been modified pursuant
* 	to the GNU General Public License, and as distributed it includes or
* 	is derivative of works licensed under the GNU General Public License or
* 	other free or open source software licenses.
 *
 *  This file is part of BlogPingClass. 
 * 
 *     BlogPingClass is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BlogPingClass is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with BlogPingClass.  If not, see <http://www.gnu.org/licenses/>.
 */

class stlK2BlogPingClass extends stlBPK2ShareControllerClass{
    var $PingClass;
    var $pingServers; 
    
    function __construct($config) {
        parent::__construct($config);
        $this->config = array_merge(  
            array(
                'pingServers'     =>      '',
            ),
        $config
        );
    }
    
    public function ping() {
        $this->initializeClasses();
        $results = $this->PingClass->ping();
        return $results;
    }
        
    public function initializeClasses() {
        if($this->error != FALSE ){
            return;
        }
        $this->PingClass = new stileroBPK2pingClass();
        $this->PingClass->setBlog(
            $this->articleObject->sitename, 
            $this->articleObject->siteurl,
            $this->articleObject->full_url,
            $this->articleObject->rssfeed,
            $this->articleObject->tags     
        );
        $timeout = ( intval($this->config['timeout']) == 0 )? 3 : intval($this->config['timeout']);
        $this->PingClass->setTimeout($timeout);
        $this->PingClass->setTryBothMethods($this->config['tryBothMethods']);
        foreach ( $this->getPingServersArray() as $value) {
            $this->PingClass->addPingServer($value);
        }
    }
    
    public function isItemNewerThanLastPing(){
        if($this->error != FALSE ){
            return FALSE;
        }
        $articlePublishDate=$this->articleObject->publish_up;
        $query;
        $db		= &JFactory::getDbo();
        if( $this->isJoomla16() || $this->isJoomla17() ) {
            $query	= $db->getQuery(true);
            $query->select('id');
            $query->from( $this->config['shareLogTableName'] );
            $query->where("date > '".$articlePublishDate."')");
        }  elseif($this->isJoomla15()) {
            $query = "SELECT "
                .$db->nameQuote('id').
                " FROM ".$db->nameQuote( $this->config['shareLogTableName'] ).
                " WHERE date > '".$articlePublishDate."'";
        }
        $db->setQuery($query);
        $newerPingFound = $db->loadObject();
        if($newerPingFound){
            $this->error['message'] = $this->config['pluginLangPrefix']."NEWERPING";
            $this->error['type'] = '';
            return false;
        }
        return true;
    }
    
    public function isServerSupportingRequiredFunctions(){
        if($this->error != FALSE ){
            return FALSE;
        }
        if( ! function_exists( fwrite ) || ! function_exists(fsockopen) ){
            $this->error['message'] = $this->config['pluginLangPrefix'].'NO_FUNCTION_SUPPORT';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }
    
    public function isServerSafeModeDisabled (){
        if(ini_get('safe_mode')){
            $this->error['message'] = $this->config['pluginLangPrefix'].'SERVER_IN_SAFE_MODE';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }

    public function isPingServersEntered() {
        if($this->error != FALSE ){
            return FALSE;
        }
        if($this->config['pingServers']==""){
            $this->error['message'] = $this->config['pluginLangPrefix'].'NOPINGSERVER';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }

     public function getPingServersArray(){
        $this->pingServers = explode("\n", trim($this->config['pingServers']));
        return $this->pingServers;
    }
}