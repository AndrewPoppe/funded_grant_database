<?php

namespace YaleREDCap\FundedGrantDatabase;

$use_noauth = $module->configuration["cas"]["use_cas"];

# verify user access
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;
if ( !$user_id || !isset($_COOKIE['grant_repo']) ) {
	header("Location: " . $module->getUrl("src/index.php", $use_noauth));
}

// set configs
$module->get_config();
$grantsProjectId = $module->configuration["projects"]["grants"]["projectId"];

# update user role
$role = $module->updateRole($user_id);

# make sure role is not empty
if ( empty($role) ) {
	header("Location: " . $module->getUrl("src/index.php", $use_noauth));
}

// grant record id for logging purposes
if ( !isset($_GET['record']) ) {
	exit('No Grant Identified');
}
$grant = $_GET['record'];

// get grant info for logging purposes
$grantData = json_decode(\REDCap::getData(
	array(
		'project_id'    => $grantsProjectId,
		'filterLogic'   => "[record_id] = $grant",
		"return_format" => "json"
	)
), true)[0];

// log this download (accessing this page counts)
\REDCap::logEvent("Download uploaded document", "Funded Grant Database ($user_id)", NULL, $grant, NULL, $grantsProjectId);

// log visit
$module->log("Visited Download Page", array( "project_id" => $grantsProjectId, "record" => $grant, "user" => $user_id, "role" => $role, "pi_netid" => $grantData["pi_netid"] ));

// If ID is not in query_string, then return error
if ( !isset($_GET['id']) || !is_numeric($_GET['id']) ) {
	exit("{$lang['global_01']}!");
}

// need to set the project id since we are using a different variable name
if ( !isset($_GET['p']) || !is_numeric($_GET['p']) ) {
	exit("{$lang['global_01']}!");
}
$project_id = $_GET['p'];


// Download file from the "edocs" web server directory
$result                                     = $module->query("select * from redcap_edocs_metadata where project_id = ? and doc_id = ?", [ $project_id, $_GET['id'] ]);
$this_file                                  = $result->fetch_assoc();
$docId                                      = $this_file['doc_id'];
$storedName                                 = $this_file['stored_name'];
list( $mime_type, $filename, $file_contents ) = method_exists("REDCap", "getFile") ? \REDCap::getFile($docId) : \Files::getEdocContentsAttributes($docId);

$tmpfile      = tmpfile();
$tmpfile_path = stream_get_meta_data($tmpfile)['uri'];
file_put_contents($tmpfile_path, $file_contents);

$files = array();
if ( preg_match("/\.zip$/i", $this_file['stored_name']) || ($this_file['mime_type'] == "application/x-zip-compressed") ) {
	$zip = new \ZipArchive;
	$res = $zip->open($tmpfile_path);
	if ( $res ) {
		$nfiles = $zip->count();
		for ( $i = 0; $i < $nfiles; $i++ ) {
			$name    = $zip->getNameIndex($i);
			$content = $zip->getFromIndex($i);
			if ( $content != false ) {
				$files[] = [
					"name"    => basename($name),
					"content" => $content,
					"index"   => $i
				];
			}
		}
	}
} else {
	$files[] = [
		"name"    => $filename,
		"content" => $file_contents,
		"index"   => "NULL"
	];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<title>
		<?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?> - Document Download
	</title>
	<link rel="shortcut icon" type="image"
		href="<?php echo \REDCap::escapeHtml($module->configuration["files"]["faviconImage"]) ?>" />
	<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("css/basic.css") ?>">
</head>
<br />
<div id="container" style="padding-left:8%;  padding-right:10%; margin-left:auto; margin-right:auto; ">
	<div id="header">
		<?php $module->createHeaderAndTaskBar($role); ?>
		<h3>Download Grant Documents</h3>
		<hr />
	</div>
	<div id="downloads">
		<?php
		if ( !empty($files) ) {
			echo "<h1>All Files (" . count($files) . ")</h1>\n";
			foreach ( $files as $file ) {
				echo "<p><a href='" . \REDCap::escapeHtml($module->getUrl("src/downloadFile.php?p=" . urlencode($project_id) . "&i=" . urlencode($docId) . "&n=" . urlencode($storedName) . "&index=" . urlencode($file["index"]), $use_noauth)) . "'>" . \REDCap::escapeHtml($file["name"]) . "</a></p>\n";
			}
			exit();
		} else {
			echo "<p>No files have been provided.</p>";
		}
		?>

</html>