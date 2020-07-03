<?php

/** Details
 * 2019
 * Lib Name: SimBASE SCMT
 * Description: Lib for sending System Core Messages to SimBASE
 * Version: 1
 * Author: Vyacheslav Odinokov
 * Author contact: kagayakashi.vo@gmail.com | @kagayakashi
 **/

/** Changelogs
 * First version created.
 **/

/** HowTo
 * Open config.ini and edit parameters;
 * Include library and create new object:

require_once 'lib/sbscmt.php';
$sbscmt = new SBSCMT;

 * To send request use SBAPI methods with 4 arguments:

$scmt = 9000;
$scm_body = ['echo' => 'test'];
$scm_sid = session_id();
$scm_uid = 0;

$sbscmt->set_data($scmt, $scm_body, $scm_sid, $scm_uid)->send_r();

 * Also, you can run this methods without arguments:

$sbapi->set_data()->send_r();

 * In this case scmt = 9000, body = ['echo' => 'test'] and scm_sid = session id
 that is created in object;
 * This example is very useful for connection test;
 **/

/** Debug
 * Open config.ini and edit debut parameter to 1 and scm_stm to 1-6;
 * After this scm will do self test and all primary methods results will be
saved to log directory;
 **/

define("SBSCMT_DIR", dirname(__FILE__));

class SBSCMT{

    private $config;
    private $scmt;
    private $scm_body;
    private $scm_sid;
    private $scm_uid;
    private $scm;

    public function __construct(){
        $this->set_config();
    }

/** set_data:
 * Method to save scmt, scm body, session id into object;
 * By default scmt = 9000, scm_body = {'echo':'test'}, sid = random session id;
 **/
    public function set_data($scmt = 9000, $scm_body = NULL, $scm_sid = NULL, $scm_uid = 0){
        $this->scmt = (is_null($scmt) || $scmt == '') ? 9000 : $scmt;
        $this->scm_body = (is_null($scm_body) || $scm_body == '') ? ['echo' => 'test'] : $scm_body;
        if(is_null($scm_sid) || $scm_sid == ''){
            session_start();
            $this->scm_sid = session_id();
            session_destroy();
        }
        else{$this->scm_sid = $scm_sid;}
        $this->scm_uid = $scm_uid;

        $this->debug_file(__METHOD__.'::scmt', $this->scmt);
        $this->debug_file(__METHOD__.'::scm_body', json_encode($this->scm_body));
        $this->debug_file(__METHOD__.'::scm_sid', $this->scm_sid);
        return $this;
    }

/** send_r:
 * Method to send selected SCM to SimBASE and put saved data into it;
 * After sending it checks errors of Curl and errors from SimBASE core;
 * If Curl error found it will show error by stopping the script;
 **/
    public function send_r(){
        $this->set_scm();
        $body = serialize($this->scm);
        $header = array(
            "Content-type: text/xml",
            "Content-length: ".strlen($body),
            "Connection: close",
        );

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $this->config['sys_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        if($errno){die("Curl error! Error code: $errno; See more at https://curl.haxx.se/libcurl/c/libcurl-errors.html");}
        $this->check_response($response);
        curl_close($ch);

        $this->debug_file(__METHOD__.'::body', $body);
        return NULL;
    }

/** set_scm:
 * Method to form scm_header and scm_body together;
 **/
    private function set_scm(){
        $iid = $this->config['sys_iid'];
        $cid = substr($iid, 0, 4);
        $time = time();
        $scm = [
            'header' => [
                'scm_ver'       => 0+$this->config['scm_ver'],
                'scm_sender'    => hexdec($iid),
                'scm_recipient' => hexdec($cid),
                'scm_type'      => $this->scmt,
                'scm_type_ver'  => 1,
                'scm_stm'       => 0+$this->config['scm_stm'],
                'scm_error'     => 0,
                'scm_created'   => $time,
                'scm_expires'   => $this->config['sys_tmo']+$time,
                'scm_uid'       => $this->scm_uid,
                'scm_sid'       => $this->scm_sid,
                'scm_crc'       => '',
            ],
            'body' => $this->scm_body,
        ];
        $scm = $this->set_scm_crc($scm);
        $this->scm = $scm;

        $this->debug_file(__METHOD__.'::scm', json_encode($scm));
        return NULL;
    }

/** check_response:
 * Method to check errors from response;
 **/
    private function check_response($response){
        $scm_response = unserialize($response);
        $scm_body   = $scm_response['body'];
        $scm_header = $scm_response['header'];

        if($scm_header['scm_error'] > 0){
            // ERROR
        }
        $this->debug_file(__METHOD__.'::'.$scm_header['scm_error'].'', json_encode($scm_response));
        return NULL;
    }

/** set_scm_crc:
 * Method to get scm values and hash them into md5;
 * After, put hashed values into header->scm_crc
 **/
    private function set_scm_crc($scm){
        $scm['header']['scm_crc'] = '';
        $scm_nohash = $this->get_scm_crc($scm);
        $scm_hash = md5($scm_nohash);
        $scm['header']['scm_crc'] = $scm_hash;

        $this->debug_file(__METHOD__.'::scm_nohash', $scm_nohash);
        $this->debug_file(__METHOD__.'::scm_hash', $scm_hash);
        return $scm;
    }

/** get_scm_crc:
 * Method to get all values from keys in SCM array and put them together;
 **/
    private function get_scm_crc($scm){
        $res  = '';
        $keys = array_keys($scm);
        $ci = count($keys);
        for ($i = 0; $i < $ci; $i++){
            if(is_array( $scm[$keys[$i]])){
                $buf = $scm[$keys[$i]];
                ksort($buf);
                $res .= $this->get_scm_crc($buf);
            }
            else{
                $res .= $scm[$keys[$i]];
            }
        }
        return $res;
    }

/** debug_file:
 * Method to debugging library. If its enabled it will save all primary method
 results into log directory;
 **/
    private function debug_file($file_name, $content){
        if($this->debug == 1){
            $file = fopen(SBSCMT_DIR."/log/$file_name.txt", "w")
            or die("SBSCMT_LIB: dev_mode 1; Unable to open file $file_name!");
            fwrite($file, $content);
            fclose($file);
        }
        return NULL;
    }

/** check_config:
 * Method to check config file if any parameter is empty;
 **/
    private function check_config(){
        foreach($this->config as $cfg => $val){
            $error = $val == '' ? true : false;
            if($error)die("Invalid configuration - $cfg");
        }
    }

/** set_config:
 * Method to save config parameters to object;
 **/
    private function set_config(){
        $this->config = parse_ini_file('config.ini');
        $this->debug = $this->config['debug'];
        $this->check_config();
        return NULL;
    }
}