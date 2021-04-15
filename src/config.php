<?php

/** Author: Andrew Poppe */

// PIDs of REDCap Projects
$grantsProjectId        = $module->getSystemSetting("grants-project");
$userProjectId          = $module->getSystemSetting("users-project");

// Contact Person
$contactName            = $module->getSystemSetting("contact-name");
$contactEmail           = $module->getSystemSetting("contact-email"); 

// Aesthetics
$accentColor            = $module->getSystemSetting("accent-color");
$accentTextColor        = $module->getSystemSetting("text-color");
$secondaryAccentColor   = $module->getSystemSetting("secondary-accent-color");
$secondaryTextColor     = $module->getSystemSetting("secondary-text-color");
$logoFile               = $module->getSystemSetting("logo");
$favicon                = $module->getSystemSetting("favicon");
$databaseTitle          = $module->getSystemSetting("database-title");


// Check out provided PIDs
checkPID($grantsProjectId, 'Grants Project');
checkPID($userProjectId, 'Users Project');

// Check that contact person's deatils are set
if (is_null($contactName) | is_null($contactEmail)) die ("The contact person's information must be defined in the EM config. Contact your REDCap Administrator.");

// Make sure user project has requisite fields
$userTestFields = array('user_id', 'user_expiration', 'user_role');
$userFields = getFieldNames($userProjectId);
if (!verifyProjectMetadata($userFields, $userTestFields)) die ('The project (PID#'.$userProjectId.') is not a valid users project. Contact your REDCap Administrator.');

// Make sure grants project has requisite fields
$grantTestFields = array('grants_pi', 'grants_title', 'grants_type', 'grants_date', 'grants_number', 'grants_department', 'grants_thesaurus');
$grantFields = getFieldNames($grantsProjectId);
if (!verifyProjectMetadata($grantFields, $grantTestFields)) die('The project (PID#'.$grantsProjectId.') is not a valid grants project. Contact your REDCap Administrator.');

// Set default if any aesthetics are missing
$accentColor            = is_null($accentColor) ? "#00356b" : $accentColor;
$accentTextColor        = is_null($accentTextColor) ? "#f9f9f9" : $accentTextColor;
$secondaryAccentColor   = is_null($secondaryAccentColor) ? adjustBrightness($accentColor, 0.50) : $secondaryAccentColor;
$secondaryTextColor     = is_null($secondaryTextColor) ? adjustBrightness($accentTextColor, getBrightness($secondaryAccentColor) >= 0.70 ? -1 : 1) : $secondaryTextColor;
$logoImage              = is_null($logoFile) ? $module->getUrl("img/yu.png") : getFile($logoFile);
$faviconImage           = is_null($favicon) ? $module->getUrl("img/favicon.ico") : getFile($favicon);
$databaseTitle          = is_null($databaseTitle) ? "Yale University Funded Grant Database" : $databaseTitle;


// Get Custom Fields
$customFields           = getSystemSubSettings();
$customFields           = checkCustomFields($customFields);

function checkPID($pid, $label) {
    global $module;
    if (is_null($pid)) die ("A PID must be listed for the ".$label." in the system settings. Contact your REDCap Administrator.");

    $sql = 'select * from redcap_projects where project_id = ?';
    $result = $module->query($sql, $pid);
    $row = $result->fetch_assoc();
    $error = false;
    if (is_null($row)) {
        $error = true;
        $message = "The project does not exist.";
    } else if (!empty($row["date_deleted"])) {
        $error = true;
        $message = "The project must not be deleted.";
    } else if (!empty($row["completed_time"])) {
        $error = true;
        $message = "The project must not be in Completed status.";
    } else if ($row["status"] == 2) {
        $error = true;
        $message = "The project must not be in Analysis/Cleanup status.";
    }
    if ($error) {
        die ("<strong>There is a problem with PID".$pid." (".$label."):</strong><br>".$message."<br>Contact your REDCap Administrator.");
    }
}

function getFile($edocId) {
    global $module;
    $result = $module->query('SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ?', $edocId);
    $filename = $result->fetch_assoc()["stored_name"];
    return APP_PATH_WEBROOT_FULL."edocs/".$filename;
}

function getSystemSubSettings() {
    global $module;
    $result = array();
    $enabled = $module->getSystemSetting('use-custom-fields');
    if (!$enabled) return $result;
    $subSettings = array('field', 'label', 'visible', 'column-index');

    foreach ($subSettings as $subSetting) {
        $subSettingResults = $module->getSystemSetting($subSetting);
        foreach ($subSettingResults as $key=>$subSettingResult) {
            $result[$key][$subSetting] = $subSettingResult;
        }
    }
    return $result;
}

function checkCustomFields($customFields) {
    $result = array_filter($customFields, function($el) {
        global $grantFields;
        return in_array($el["field"], $grantFields);
    });
    return $result;
}

function getColumnOrders($customFields, $defaultColumns) {
    $nCustomFields = count($customFields);
    $nDefaultColumns = count($defaultColumns);
    $nTotal = $nCustomFields + $nDefaultColumns;

    // holds "taken" indices
    $unavailable = array();

    // holds results
    $result = array();

    // sort the customFields - descending
    usort($customFields, function($a, $b) {
        if ($a["column-index"] > $b["column-index"]) {
            return -1;
        } else {
            return 1;
        }
    });

    // If there are any indices greater than the total number of columns, 
    //      reassign their indices, pushing other indices lower if needed
    // This also removes "ties" in custom field indices
    $comparison = $nTotal - 1;
    foreach ($customFields as $customField) {
        $index = (int)$customField["column-index"];

        // index is too high or not a number
        if ($index > $comparison) {
            $index = $comparison;
            $comparison--;
        } 
        // index is too low
        else if ($index < 0) {
            $index = 0;
        }
        while (in_array($index, $unavailable)) {
            $index++;
        }
        
        $result[$index] = $customField;
        array_push($unavailable, $index);
    }

    // Now add in default columns
    $index = 0;
    foreach ($defaultColumns as $defaultColumn) {
        while (in_array($index, $unavailable)) {
            $index++;
        }

        $result[$index] = $defaultColumn;
        array_push($unavailable, $index);
    }

    return $result;
}