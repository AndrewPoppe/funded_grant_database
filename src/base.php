<?php

/** Authors: Jon Scherdin, Andrew Poppe */

$module->get_config();

function getChoices($metadata) {
	$choicesStrs = array();
	$multis = array("checkbox", "dropdown", "radio");
	foreach ($metadata as $row) {
		if (in_array($row['field_type'], $multis) && $row['select_choices_or_calculations']) {
			$choicesStrs[$row['field_name']] = $row['select_choices_or_calculations'];
		} else if ($row['field_type'] == "yesno") {
			$choicesStrs[$row['field_name']] = "0,No|1,Yes";
		} else if ($row['field_type'] == "truefalse") {
			$choicesStrs[$row['field_name']] = "0,False|1,True";
		}
	}
	$choices = array();
	foreach ($choicesStrs as $fieldName => $choicesStr) {
		$choicePairs = preg_split("/\s*\|\s*/", $choicesStr);
		$choices[$fieldName] = array();
		foreach ($choicePairs as $pair) {
			$a = preg_split("/\s*,\s*/", $pair);
			if (count($a) == 2) {
				$choices[$fieldName][$a[0]] = $a[1];
			} else if (count($a) > 2) {
				$a = preg_split("/,/", $pair);
				$b = array();
				for ($i = 1; $i < count($a); $i++) {
					$b[] = $a[$i];
				}
				$choices[$fieldName][trim($a[0])] = implode(",", $b);
			}
		}
	}
	return $choices;
}

function authenticate($uid, $timestamp) {
	global $module, $userProjectId;
    $sql = "SELECT a.value as 'userid', a2.value as 'role'
		FROM redcap_data a
		JOIN redcap_data a2
		LEFT JOIN redcap_data a3 ON (a3.project_id =a.project_id AND a3.record = a.record AND a3.field_name = 'user_expiration')
		WHERE a.project_id = ?
			AND a.field_name = 'user_id'
			AND a.value = ?
			AND a2.project_id = a.project_id
			AND a2.record = a.record
			AND a2.field_name = 'user_role'
			AND (a3.value IS NULL OR a3.value > ?)";
	return $module->query($sql, [$userProjectId, $uid, $timestamp]);   
}

function updateRole($userid) {
	$timestamp = date('Y-m-d');
	$result = authenticate($userid, $timestamp);
	if (db_num_rows($result) > 0) {
		$user_id = db_result($result, 0, 0);
		$role = db_result($result, 0, 1);
	}
	setcookie('grant_repo', $role);
	return $role;
}

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


function getAllChoices($choices, $fields) {
	$result = array();
	foreach ($fields as $field) {
		$result = array_merge($result, $choices[$field]);
	}
	return array_unique($result);
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




// $fieldToTest is a field that appears on the grants instrument
function getGrantsInstrument($metadata, $fieldToTest) {
	foreach ($metadata as $row) {
		if ($row['field_name'] == $fieldToTest) {
			return $row['form_name'];
		}
	}
	return;
}