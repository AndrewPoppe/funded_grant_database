<?php

/** Authors: Jon Scherdin, Andrew Poppe */

$module->get_config();



function createHeaderAndTaskBar($role) {
	global $module, $logoImage, $accentColor, $grantsProjectId, $userProjectId;
	echo '<div style="padding: 10px; background-color: '.\REDCap::escapeHtml($accentColor).';"></div><img src="'.\REDCap::escapeHtml($logoImage).'" style="vertical-align:middle"/>
			<hr>
			<a href="'.$module->getUrl("src/grants.php").'">Grants</a> | ';
	if ($role != 1) {
		echo '<a href="'.$module->getUrl("src/statistics.php").'">Use Statistics</a> | ';
	}
	if ($role == 3) {
		echo "<a href='".APP_PATH_WEBROOT."DataEntry/record_status_dashboard.php?pid=".\REDCap::escapeHtml($grantsProjectId)."' target='_blank'>Register Grants</a> | ";
		echo "<a href='".APP_PATH_WEBROOT."DataEntry/record_status_dashboard.php?pid=".\REDCap::escapeHtml($userProjectId)."' target='_blank'>Administer Users</a> | ";
	}
	echo '<a href ="http://projectreporter.nih.gov/reporter.cfm">NIH RePORTER</a> |
	<a href ="http://grants.nih.gov/grants/oer.htm">NIH-OER</a>';
}




// Combines values from the provided fields using the provided separator
// Takes data array and field array
// Returns array of values, one entry per record in data array
function combineValues($data, $fields) {
	$result = array();
	foreach ($data as $id=>$row) {
		$values = array();
		foreach ($fields as $field) {
			$values[$field] = '--'.implode('--', explode(',', $row[$field])).'--';
		}
		$result[$id] = implode('--', array_unique($values));
	}
	return $result;
}



