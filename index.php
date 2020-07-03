<?php
//SCMT
require_once 'lib/sbscmt.php';
$sbscmt = new SBSCMT;

// To send test request set $test to 1, otherwise to 0
$test = 0;

session_start();
$scmt = 9010;
$scm_body = [
    'login' => '666777666777',
    'auth_type' => 1,
    'auth_data' => ['pwd' => 'Simourg1']
];
$scm_sid = session_id();
$scm_uid = 0;
session_destroy();

switch($test){
    case 1:
        $sbscmt->set_data()->send_r();
        break;

    case 0:
        $sbscmt->set_data($scmt, $scm_body, $scm_sid, $scm_uid)->send_r();
        break;
}