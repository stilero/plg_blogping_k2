<?php
/**
 * The Stilero Ping Class - Made for pinging update servers 
 *
 * @version 2.1
 * @author danieleliasson Stilero AB - http://www.stilero.com
 * @copyright 2011-dec-31 Stilero AB
 * @license	GPLv3
 * 
 * pingClass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or 
 * (at your option) any later version.
 * 
 * pingClass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with pingClass.  
 * If not, see <http://www.gnu.org/licenses/>.
 */

class stileroBPK2pingClass {
    var $servers = array();
    var $server = array();
    var $blog = array();
    var $reqHeader = array();
    var $reqXML;
    var $request;
    var $requestCache;
    var $xmlParser;
    var $config;
    var $responseMessArr = array();
    var $catchResponse = false;
    var $responseKey;
    var $responseVal;
    var $responseMessage;
    const ERROR_NOTICE  = '1';
    const ERROR_WARNING = '2';
    const ERROR_NORESPOND   = '3';
    const ERROR_FATAL   = '4';
    
    function __construct() {
         $this->config = array(
                'extendedPing'      =>      false,
                'userAgent'         =>      'BlogPing 1.0',
                'eol'               =>      '\n',
                'timeout'           =>      3,
                'debug'             =>      false,
                'tryBothMethods'    =>      true    //Makes the class first try extended Pings and the regular if they fail.
          );
    }
    /**
     * Main method that handles the pinging
     * @return  response    boolean false when the server fails to answer the ping
     * @return  response    Array with responsecode and responsemessage
     */
    public function ping() {
        if( empty ($this->blog) ) {
            return array(self::ERROR_WARNING,"Blog info missing");
        }
        foreach ($this->servers as $key => $value) {
            $pingResponse = 'norespond';
            //First try the extended Ping.
            if( $this->config['tryBothMethods'] && $this->isExtendedPingInfoSpecified() ){
                $this->config['extendedPing'] = true;
                $pingResponse = $this->sendPingRequest($value);
            }
            //Try regular ping if the extended fails.
            if( $pingResponse[0] != '0' || $pingResponse == 'norespond' ){
                $pingResponse[0] = false;
                $pingResponse[1] = "";
                $this->config['extendedPing'] = false;
                $pingResponse = $this->sendPingRequest($value);
            }
            //Both failed, create an error
            if( $pingResponse == 'norespond' ){
                $pingResponse = array(self::ERROR_NORESPOND, "Ping server not responding.");
            }
            $this->responseMessArr[] = $pingResponse;
        }
        return $this->responseMessArr;
    }
    
    public function setBlog($blogName, $blogURL, $postURL, $feedURL="", $tags="") {
        $this->blog['name'] = $blogName;
        $this->blog['url'] = $blogURL;
        $this->blog['postURL'] = $postURL;
        $this->blog['rss'] = $feedURL;
        $this->blog['tags'] = $tags;
    }
    
    private function getMethod() {
        $pingMethod = ($this->config['extendedPing']) ? "weblogUpdates.extendedPing" : "weblogUpdates.ping";
        return $pingMethod;
    }
    
    private function isExtendedPingInfoSpecified(){
        if( $this->blog['rss']=="" || $this->blog['postURL']=="" || $this->blog['tags']=="" ) {
            return false;
        }
        return true;
    }
    
    private function buildXML(){
        $params = array(
            'blogName'     =>      $this->blog['name'],
            'blogURL'       =>      $this->blog['url']
        );
       if( $this->config['extendedPing'] && $this->isExtendedPingInfoSpecified() ) {
            $params = array_merge($params, array(
                'updateUrl'     =>      $this->blog['postURL'],
                'rss'           =>      $this->blog['rss'],
                'tags'          =>      $this->blog['tags']              
            ));
        }
        foreach ($params as $value) {
            $xmlString .= 
                "<param><value><string>".htmlspecialchars($value)."</string></value></param>\n";
        }
        $this->reqXML = $xmlString;
    }  

    private function buildHeader() {
        $contentLength  = strlen($this->request);
        $headerArr = array(
            'User-Agent'        =>  $this->config['userAgent'],
            'Host'              =>  $this->server['host'],
            'Content-Type'      =>  'text/xml',
            'Content-length'    =>  $contentLength
        );
        $newLine = "\r\n";
        $header  = "POST ".$this->server['path']." HTTP/1.0".$newLine;
        foreach ($headerArr as $key => $value) {
            $header .= $key.": ".$value.$newLine;
        }
        $this->reqHeader = $header;
    }
    
    private function buildRequest(){
        $methodName = $this->getMethod();
        $cachedRequest = $this->requestCache[$methodName];
        $newLine = "\r\n";
        //Check if a stored version of the request is found
        if ( $cachedRequest != "" ) {
            $this->request = $cachedRequest;
        }else{
        $this->buildXML();
        $this->request = <<<EOD
<?xml version="1.0"?>
<methodCall>
<methodName>{$methodName}</methodName>
<params>
{$this->reqXML}</params>
</methodCall>
EOD;
        $this->requestCache[$methodName] = $this->request;
        }
        $this->buildHeader();
        $this->request = $this->reqHeader .$newLine.  $this->request;
    }
    
    private function parseServerURL($serverURL) {
        $correctedServerURL = trim($serverURL);
        if ( ! preg_match("/^http?:\/\//", $correctedServerURL) ) {
            $correctedServerURL = 'http://'.$correctedServerURL;
        }
        $this->server = parse_url($correctedServerURL);
        $this->server['port'] = isset($this->server['port'])?$this->server['port']:80;
        $this->server['path'] = isset($this->server['path'])?$this->server['path']:'/';
    }
    
    private function isServerURLOK(){
        $allOK = true;
        $partsToCheck = array ('host','path','port','scheme');
        foreach ($partsToCheck as $value) {
            if($this->server[$value] == "" ){
                $allOK = false;
            }
        }
        return $allOK;
    }
    
    private function sendPingRequest($serverURL) {
        $this->parseServerURL($serverURL);
        if( ! $this->isServerURLOK() ) {
            return array(self::ERROR_FATAL,"Bad server adress.");
        }
        $this->buildRequest();
        
        $filePointer = @fsockopen( $this->server['host'], $this->server['port'] , $errorNo, $errorMessage, $this->config['timeout'] );
        if(!$filePointer){
            $errorNo = ($errorNo == 0)?self::ERROR_NORESPOND : $errorNo;
            return array($errorNo,$errorMessage);	
            //return array(1,"Failed connecting to server: $serverURL");	
            //return false;
        }
        fwrite( $filePointer, $this->request );
        
        $response = '';
        while( ! feof($filePointer) ) {
            $response .= fgets($filePointer, 128);
        }
        fclose($filePointer);
        if($this->config['debug']) {
            //print "<pre>".htmlentities($this->request)."</pre>";
            print "<pre>".htmlentities($response)."</pre><br />";
        }
        return $this->handleXMLResponse($response);
    }
    
    private function isRequestAccepted($httpStatus){
        $accepted = false;
        switch ($httpStatus[1]) {
            case '200':
                $accepted = true;
                break;
            case '302':
                $accepted = true;
                break;
            default:
                break;
        }
        return $accepted;
    }
    
    private function getHTTPStatus($response) {
        //Response on all ok
        //HTTP/1.0 200 OK
        $responseLines = explode("\r\n", $response);
        $responseString = $responseLines[0];
        preg_match("/([1-5][0-9][0-9]+) ([\w\s]+$)/", $responseString, $httpStatus);
        return $httpStatus;
    }
    public function extractXMLFromResponse($response=""){
        $responseArray = preg_split( '/<\?xml.*?\?'.'>/', $response);
        return trim($responseArray[1]);  
    }
    
    public function handleXMLResponse($response) {
        $httpStatus = $this->getHTTPStatus($response);
        if(!$this->isRequestAccepted($httpStatus)){
            return array($httpStatus[1], $httpStatus[2]);
            //return false;
        }
        $response = $this->extractXMLFromResponse($response);
        $this->xmlParser = xml_parser_create();
        
        xml_set_object($this->xmlParser, $this);
        xml_set_element_handler($this->xmlParser, "xmlStartTag", "xmlEndTag");
        xml_set_character_data_handler($this->xmlParser, "xmlContentBetweenTags");
        $final = false;
        $chunk_size = 262144;
        do {
            if (strlen($response) <= $chunk_size) {
                $final = true;
            }
            $part = substr($response, 0, $chunk_size);
            $response = substr($response, $chunk_size);
            if (!xml_parse($this->xmlParser, $part, $final)) {
                return false;
            }
            if ($final) {
                break;
            }
        } while (true);
        xml_parser_free($this->xmlParser);
        
        return $this->getResponse();
    }
    
    private function xmlStartTag($parser, $data){
        $tagname = strtolower($data);
        switch ($tagname) {
            case 'name':
                $this->catchResponse = true;
                break;
            case 'boolean':
                $this->catchResponse = true;
                break;
            case 'int':
                $this->catchResponse = true;
                break;
            case 'string':
                $this->catchResponse = true;
                break;
            case 'value':
                $this->catchResponse = ($this->responseKey != "") ? true:false;
                break;
            default:
                break;
        }
    }
    private function xmlEndTag($parser, $data) {
        return;
    }
    private function xmlContentBetweenTags($parser, $data){
        if( ! $this->catchResponse ) {
            return;
        }
        switch (strtolower($data)) {
            case "flerror":
                $this->responseKey = 'flerror';
                $this->catchResponse = false;
                break;
            case "faultcode":
                $this->responseKey = 'flerror';
                $this->catchResponse = false;
                break;
            case "message":
                $this->responseKey = 'message';
                 $this->catchResponse = false;
                break;
            case "faultstring":
                $this->responseKey = 'message';
                 $this->catchResponse = false;
                break;
            default:
                break;
        }
        if( $this->catchResponse && $this->responseKey != "" ) {
            $this->responseMessage[$this->responseKey] = $data;
            $this->catchResponse = false;
        }
    }
    
    public function getResponse() {
        return array($this->responseMessage['flerror'], $this->responseMessage['message']);
    }
    
    public function addPingServer($pingServerURL) {
        $this->servers[] = $pingServerURL;
    }
    
    public function setTimeout($timeoutInSeconds) {
        $this->config['timeout'] = intval($timeoutInSeconds);
    }
    
    public function setTryBothMethods($boolTry) {
        $this->config['tryBothMethods'] = intval($boolTry);
    }
}

