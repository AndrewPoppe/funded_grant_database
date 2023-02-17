<?php

/** Authors: Jon Scherdin, Andrew Poppe */

# verify user access
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;
if (!$user_id || !isset($_COOKIE['grant_repo'])) {
	header("Location: " . $module->getUrl("src/index.php"));
}

$project_id = $_GET["p"];
$doc_id = $_GET["i"];
$stored_name = $_GET["n"];
$index = $_GET["index"];

// Download file from the "edocs" web server directory
$result = $module->query("select * from redcap_edocs_metadata where project_id = ? and doc_id = ? and stored_name = ?", [$project_id, $doc_id, $stored_name]);
$this_file = $result->fetch_assoc();

if (empty($this_file)) {
	die("NO FILE FOUND");
}

list($mime_type, $filename, $file_contents) = method_exists("REDCap", "getFile") ? \REDCap::getFile($doc_id) : \Files::getEdocContentsAttributes($doc_id);
$tmpfile = tmpfile();
$tmpfile_path = stream_get_meta_data($tmpfile)['uri'];
file_put_contents($tmpfile_path, $file_contents);

$name = "";
$content = "";
if (preg_match("/\.zip$/i", $stored_name) || ($this_file['mime_type'] == "application/x-zip-compressed")) {
	$zip = new \ZipArchive;
	$res = $zip->open($tmpfile_path);
	if ($res) {
		$filename = $zip->getNameIndex($index);
		$file_contents = $zip->getFromIndex($index);
	}
}


// JUST DOWNLOAD THE ORIGINAL FILE

header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Type: ' . $mime_type);
header("Pragma: no-cache");
header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0");
header('Content-Length: ' . strlen($file_contents));

//Clear system output buffer
flush();

echo $file_contents;

//Terminate from the script
exit();
