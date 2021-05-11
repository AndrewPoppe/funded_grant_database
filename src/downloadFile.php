<?php

/** Authors: Jon Scherdin, Andrew Poppe */

# verify user access
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;
if (!$user_id || !isset($_COOKIE['grant_repo'])) {
	header("Location: ".$module->getUrl("src/index.php"));
}

$filename = $module->getSafePath($_GET['f'], APP_PATH_TEMP);
$dieMssg = "Improper filename ".$filename;
if (!isset($_GET['f']) || preg_match("/\.\./", $_GET['f']) || preg_match("/^\//", $_GET['f'])) {
	die($dieMssg);
}

if (!file_exists($filename)) {
	die($dieMssg);
}

// JUST DOWNLOAD THE ORIGINAL FILE
displayFile($filename);

function displayFile($filename) {
	header('Content-Disposition: attachment; filename="'.basename($filename).'"');
    header('Content-Type: application/octet-stream');
	header("Pragma: no-cache");
	header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0");
	header('Content-Length: ' . filesize($filename));
    
	//Clear system output buffer
	flush();

    // read the file from disk
    readfile($filename);
	//Terminate from the script
	die();
}
