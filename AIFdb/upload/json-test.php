<?php

// Quick test file for uploading and parsing JSON
// To test, redirect JSON upload inside upload/index.php by replacing line:
// <form enctype="multipart/form-data" action="ul.php" method="POST">
// with line:
// <form enctype="multipart/form-data" action="json-test.php" method="POST">

//$host = 'www.aifdb.org';
$host = 'localhost';
$db = '/';

//$target_path = "tmp/";
//$fname = basename($_FILES['uploadedfile']['name']);
//$target_path = $target_path . $fname;

define('INSTALLDIR', dirname(__FILE__));

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uploaddir = INSTALLDIR.'/tmp/';
    $json_file = $uploaddir . basename($_FILES['uploadedfile']['name']);

    if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $json_file)){
        $jsondata = file_get_contents($json_file);
        $json = json_decode($jsondata);
        $a = 5;
    }
    else {
        echo "Error";
    }
}
