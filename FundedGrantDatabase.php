<?php

namespace YaleREDCap\FundedGrantDatabase;

/**
 * Main EM class
 * 
 * @author Andrew Poppe
 */
class FundedGrantDatabase extends \ExternalModules\AbstractExternalModule {

    ################################
    ###  CONFIGURATION SETTINGS  ###
    ################################

    public $config = array(
        "projects"      => array(),
        "contact"       => array(),
        "colors"        => array(),
        "files"         => array(),
        "text"          => array(),
        "emailUsers"    => array(),
        "customFields"  => array()
    );
    

    /**
     * Get all configuration settings for the module.
     * @return void
     */
    public function get_config() {
        $this->get_project_config();
        $this->get_contact_config();
        $this->get_color_config();
        $this->get_file_config();
        $this->get_text_config();
        $this->get_email_config();
        $this->get_custom_field_config();
    }


    ###############################
    ###  CONFIGURATION METHODS  ###
    ###############################

    /***************\ 
    |  PROJECT IDS  |
    \***************/

    /**
     * Get and verify project IDs from system settings.
     * @return void
     */
    private function get_project_config() {
        $grantsProjectId    = $this->getSystemSetting("grants-project");
        $userProjectId      = $this->getSystemSetting("users-project");
        $this->checkPID($grantsProjectId, 'Grants Project');
        $this->checkPID($userProjectId, 'User Project');
        $this->get_metadata("grants", $grantsProjectId);
        $this->get_metadata("user", $userProjectId);

        // Make sure grants project has requisite fields
        $grantTestFields = array('grants_pi', 'grants_title', 'grants_type', 'grants_date', 'grants_number', 'grants_department', 'grants_thesaurus');
        if (!$this->verifyProjectMetadata($this->config["projects"]["grants"]["metadata"], $grantTestFields)) {
            die('The project (PID#'.$grantsProjectId.') is not a valid grants project. Contact your REDCap Administrator.');
        }


        // Make sure user project has requisite fields
        $userTestFields = array('user_id', 'user_expiration', 'user_role');
        if (!$this->verifyProjectMetadata($this->config["projects"]["user"]["metadata"], $userTestFields)) {
            die ('The project (PID#'.$userProjectId.') is not a valid users project. Contact your REDCap Administrator.');
        }

        $this->config["projects"]["grants"]["projectId"] = $grantsProjectId;
        $this->config["projects"]["user"]["projectId"]   = $userProjectId;
    }


    /**
     * Checks whether the provided PID corresponds with an active project.
     * 
     * @param string $pid A PID to test
     * @param string $label A label to use in error messages if necessary
     * 
     * @return void
     */
    private function checkPID(string $pid, string $label) {
        if (is_null($pid)) die ("A PID must be listed for the ".$label." in the system settings. Contact your REDCap Administrator.");
        $sql = 'select * from redcap_projects where project_id = ?';
        $result = $this->query($sql, $pid);
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


    /**
     * Make sure project has requisite fields.
     * 
     * @param string[] $projectFields fields in the project
     * @param string[] $fieldsToTest fields the project should have
     * 
     * @return boolean Whether the project is verified or not
     */
    private function verifyProjectMetadata(array $projectFields, array $fieldsToTest) {
        foreach ($fieldsToTest as $testField) {
            if (!in_array($testField, $projectFields, true)) return false;
        }
        return true;
    }
    

    /**
     * Gets field names from a project
     * 
     * @param string $pid The project's ID
     * 
     * @return string[] field names
     */
    private function getFieldNames(string $pid) {
        global $module;
        $sql = "SELECT field_name FROM redcap_metadata WHERE project_id = ?";
        $query = $module->query($sql, $pid);
        $result = array();
        while ($row = $query->fetch_row()) {
            array_push($result, $row[0]);
        }
        return $result;
    }
    

    /**
     * Gets field list for the given project type.
     * 
     * This sets the metadata configuration for the given project type.
     * 
     * @param string $projectType "grants" or "user"
     * @param string $projectId the PID for the corresponding type
     * 
     * @return void
     */
    private function get_metadata(string $projectType, string $projectId) {
        $this->config["projects"][$projectType]["metadata"] = $this->getFieldNames($projectId);
    } 


    /***********\
    |  CONTACT  |
    \***********/

    /**
     * Get contact person info from system settings.
     * 
     * @return void
     */
    private function get_contact_config() {
        $contactName    = $this->getSystemSetting("contact-name");
        $contactEmail   = $this->getSystemSetting("contact-email"); 
        if (is_null($contactName) | is_null($contactEmail)) {
            die ("The contact person's information must be defined in the EM config. Contact your REDCap Administrator.");
        }
        $this->config["contact"] = array(
            "name"=>$contactName,
            "email"=>$contactEmail
        );
    }


    /**********\
    |  COLORS  |
    \**********/

    /**
     * Get settings for aesthetics and set defaults if necessary.
     * 
     * @return void
     */
    private function get_color_config() {
        $accentColor            = $this->getSystemSetting("accent-color");
        $accentTextColor        = $this->getSystemSetting("text-color");
        $secondaryAccentColor   = $this->getSystemSetting("secondary-accent-color");
        $secondaryTextColor     = $this->getSystemSetting("secondary-text-color");

        $accentColor            = is_null($accentColor) ? "#00356b" : $accentColor;
        $accentTextColor        = is_null($accentTextColor) ? "#f9f9f9" : $accentTextColor;
        $secondaryAccentColor   = is_null($secondaryAccentColor) ? $this->adjustBrightness($accentColor, 0.50) : $secondaryAccentColor;
        $secondaryAccentBright  = $this->getBrightness($secondaryAccentColor);
        $newSecondaryTextColor  = $this->adjustBrightness($accentTextColor, $secondaryAccentBright >= 0.70 ? -0.9 : 0.9);
        $secondaryTextColor     = is_null($secondaryTextColor) ? $newSecondaryTextColor : $secondaryTextColor;

        $this->config["colors"] = array(
            "accentColor"           => $accentColor,
            "accentTextColor"       => $accentTextColor,
            "secondaryAccentColor"  => $secondaryAccentColor,
            "secondaryTextColor"    => $secondaryTextColor,
        );
    }


    /**
     * Lighten or darken a color by the provided percent.
     * 
     * @param string $hexCode
     * @param float $adjustPercent
     * 
     * @return string new color's hex code
     */
    private function adjustBrightness(string $hexCode, float $adjustPercent) {
        $hexCode = ltrim($hexCode, '#');

        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }

        $hexCode = array_map('hexdec', str_split($hexCode, 2));

        foreach ($hexCode as & $color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = ceil($adjustableLimit * $adjustPercent);

            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }
        return '#' . implode($hexCode);
    }


    /**
     * Get how bright the provided color is.
     * 
     * @param string $hexCode the color
     * 
     * @return float brightness of the color from 0 to 1
     */
    private function getBrightness(string $hexCode) {
        $hexCode = ltrim($hexCode, '#');
        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }
        $hexCode = array_map('hexdec', str_split($hexCode, 2));
        $sum = 0;
        foreach ($hexCode as $color) {
            $sum += $color;
        }
        return $sum / (255*3);
    }


    /*********\
    |  FILES  |
    \*********/

    /**
     * Get paths to image files used in the EM
     * 
     * @return void
     */
    private function get_file_config() {
        $logoFile       = $this->getSystemSetting("logo");
        $favicon        = $this->getSystemSetting("favicon");

        $logoImage      = is_null($logoFile) ? $this->getUrl("img/yu.png") : $this->getFile($logoFile);
        $faviconImage   = is_null($favicon) ? $this->getUrl("img/favicon.ico") : $this->getFile($favicon);

        $this->config["files"] = array(
            "logoImage"     => $logoImage,
            "faviconImage"  => $faviconImage
        );
    }


    /**
     * Get url to file with provided edoc ID.
     * 
     * @param string $edocId ID of the file to find
     * 
     * @return void
     */
    private function getFile(string $edocId) {
        $result = $this->query('SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ?', $edocId);
        $filename = $result->fetch_assoc()["stored_name"];
        return APP_PATH_WEBROOT_FULL."edocs/".$filename;
    }


    /********\
    |  TEXT  |
    \********/

    /**
     * Get settings for text used throughout the EM
     * 
     * @return void
     */
    private function get_text_config() {
        $databaseTitle  = $this->getSystemSetting("database-title");
        $databaseTitle  = is_null($databaseTitle) ? "Yale University Funded Grant Database" : $databaseTitle;

        $this->config["text"] = array(
            "databaseTitle" => $databaseTitle
        );
    }


    /*********\
    |  EMAIL  |
    \*********/

    /**
     * Grabs system settings related to emailing users upon downloading files
     * @return void
     */
    private function get_email_config() {
        $enabled  = $this->getSystemSetting('email-users');
        $from     = $this->getSystemSetting('email-from-address');
        $subject  = $this->getSystemSetting('email-subject');
        $body     = $this->getSystemSetting('email-body');

        if (is_null($subject)) $subject = "Funded Grant Database Document Downloads";
        if (is_null($body)) $body = "<br>Hello [full-name],<br><br>This message is a notification that you have downloaded grant documents from the following grants using the <strong>[database-title]</strong>:<br><br>[download-table]<br><br>Questions? Contact [contact-name] (<a href=\"mailto:[contact-email]\">[contact-email]</a>)";        

        $this->config["emailUsers"] = array(
            "enabled"   => $enabled,
            "from"      => $from,
            "subject"   => $subject,
            "body"      => $body
        );
    }
    

    /*****************\
    |  CUSTOM FIELDS  |
    \*****************/

    /**
     * Grab all settings regarding custom fields and put in class config
     * 
     * @return void
     */
    private function get_custom_field_config() {
        $customFields           = $this->get_custom_field_subsettings();
        $customFields["fields"] = $this->check_custom_fields($customFields["fields"]);

        $this->config["customFields"] = $customFields;
    }


    /**
     * Get subsettings about custom fields
     * 
     * @return array[]
     */
    private function get_custom_field_subsettings() {
        $result = array(
            "enabled" => $this->getSystemSetting('use-custom-fields'),
            "fields" => array()
        );
        if (!$result["enabled"]) return $result;
        $subSettings = array('field', 'label', 'visible', 'column-index');
    
        foreach ($subSettings as $subSetting) {
            $subSettingResults = $this->getSystemSetting($subSetting);
            foreach ($subSettingResults as $key=>$subSettingResult) {
                $result["fields"][$key][$subSetting] = $subSettingResult;
            }
        }
        return $result;
    }

    
    /**
     * Filters custom fields, only returns fields whose names are in grants project metadata
     * 
     * @param array $customFields
     * 
     * @return array[]
     */
    private function check_custom_fields(array $customFields) {
        $result = array_filter($customFields, function($el) {
            return in_array($el["field"], $this->config["projects"]["grants"]["metadata"]);
        });
        return $result;
    }


    ##################################
    ###  AUTHENTICATION AND ROLES  ###
    ##################################

    /**
     * Checks authentication status of user.
     * 
     * @param string $userid
     * @param mixed $timestamp
     * 
     * @return object db query object
     */
    public function authenticate(string $userid, $timestamp) {
        $userProjectId = $this->config["projects"]["user"]["projectId"];
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
        return $this->query($sql, [$userProjectId, $userid, $timestamp]);   
    }


    /**
     * Updates authentication status, returns role.
     * 
     * @param string $userid
     * 
     * @return string role of user:
     *      "":     not in users
     *      "1":    basic user
     *      "2":    basic contributor
     *      "3":    admin
     */
    public function updateRole($userid) {
        $timestamp = date('Y-m-d');
        $result = $this->authenticate($userid, $timestamp);
        if (db_num_rows($result) > 0) {
            $user_id = db_result($result, 0, 0);
            $role = db_result($result, 0, 1);
        }
        setcookie('grant_repo', $role);
        return $role;
    }


    #######################
    ###  CUSTOM FIELDS  ###
    #######################

    /**
     * Creates the order for columns including custom and default
     * 
     * @param array $customFields
     * @param array $defaultColumns
     * 
     * @return array The column objects in order
     */
    public function getColumnOrders(array $customFields, array $defaultColumns) {
        $nCustomFields = count($customFields);
        $nDefaultColumns = count($defaultColumns);
        $nTotalColumns = $nCustomFields + $nDefaultColumns;
    
        // sort the customFields - descending
        usort($customFields, $this->custom_field_sorter);
    
        // assign indices to columns
        $columnResults = $this->custom_field_assign_indices($customFields, $nTotalColumns);
    
        // Now add in default columns
        $columns = $this->default_field_assign_indices($defaultColumns, $columnResults);

        return $columns;
    }


    /**
     * Sorting function for columns.
     * 
     * @param array $a
     * @param array $b
     * 
     * @return int
     */
    private function custom_field_sorter(array $a, array $b) {
        if ($a["column-index"] > $b["column-index"]) {
            return -1;
        } else {
            return 1;
        }
    }


    /**
     * Assign indices for custom fields.
     * 
     * @param array[] $customFields 
     * @param int $nTotalColumns count of all columns including custom and default
     * 
     * @return array[] Associative array with 
     *      takenIndices: array of indices that are spoken for
     *      columns: array of custom columns with indices set
     */
    private function custom_field_assign_indices(array $customFields, int $nTotalColumns) {
        $orderResults = array();
        $takenIndices = array();
        $higherBound = $nTotalColumns - 1;
        $lowerBound  = 0;
        // If there are any indices greater than the total number of columns, 
        //      reassign their indices, pushing other indices lower if needed
        // This also removes "ties" in custom field indices
        foreach ($customFields as $customField) {
            $index = (int)$customField["column-index"];
    
            // index is too high
            if ($index > $higherBound) {
                $index = $higherBound;
                $higherBound--;
            } 
            // index is too low
            else if ($index < $lowerBound) {
                $index = $lowerBound;
                $lowerBound++;
            }
            /*// if index is taken, increment
            while (in_array($index, $takenIndices)) {
                $index++;
            }*/
            
            // Update $orderResults and $takenIndices
            $orderResults[$index] = $customField;
            array_push($takenIndices, $index);
        }
        return array(
            "takenIndices" => $takenIndices,
            "columns" => $orderResults
        );
    }


    /**
     * Given indices taken by custom fields, fit in the default fields.
     * 
     * @param array $defaultColumns
     * @param array $columnResults
     * 
     * @return array columns (without the takenIndices)
     */
    private function default_field_assign_indices(array $defaultColumns, array $columnResults) {
        $index = 0;
        foreach ($defaultColumns as $defaultColumn) {
            while (in_array($index, $columnResults["takenIndices"])) {
                $index++;
            }
    
            $columnResults["columns"][$index] = $defaultColumn;
            array_push($columnResults["takenIndices"], $index);
        }
        return $columnResults["columns"];
    }


    ###################################################
    ###   THIS SECTION DEALS WITH EMAILING USERS    ###
    ###  WHEN THEY HAVE DOWNLOADED GRANT DOCUMENTS  ###
    ###################################################


    /**
     * Grabs download information for all grants in the last 24 hours
     * @return array array with one entry per user, containing an array of timestamps and grant ids 
     */
    private function get_todays_downloads($grantsProjectId, $userProjectId) {

        $logEventTable = \REDCap::getLogEventTable($grantsProjectId);
        $downloads = $this->query("SELECT e.ts, e.user, e.pk 
            FROM $logEventTable e 
            WHERE e.project_id = ?
            AND e.description = 'Download uploaded document'
            AND e.ts  >= now() - INTERVAL 1 DAY", 
            $grantsProjectId);
        
        $grants = json_decode(\REDCap::getData(array(
            "project_id"=>$grantsProjectId, 
            "return_format"=>"json", 
            "combine_checkbox_values"=>true,
            "fields"=>array("record_id", "grants_title", "grants_number", "grants_pi", "pi_netid"),
            "exportAsLabels"=>true
        )), true);
        
        $result = array();
        while ($download = $downloads->fetch_assoc()) {
            $grant = $grants[array_search($download["pk"], array_column($grants, "record_id"))];
            if ($download["user"] == $grant["pi_netid"]) continue;
            if (is_null($result[$download["user"]])) $result[$download["user"]] = array();
            array_push($result[$download["user"]], array(
                "ts" => $download["ts"],
                "time" => date('Y-m-d H:i:s', strtotime($download["ts"])),
                "grant_id" => $download["pk"],
                "grant_number" => $grant["grants_number"],
                "grant_title" => $grant["grants_title"],
                "pi" => $grant["grants_pi"],
                "pi_id" => $grant["pi_netid"]
            ));
        }
        return $result;
    }


    /**
     * Grabs user info for a user id
     * @param string $user_id The id of the user
     * @return array array with first_name, last_name, user_role, and email_address 
     */
    private function get_user_info($user_id, $userProjectId) {
        return json_decode(\REDCap::getData(array(
            "project_id"=>$userProjectId, 
            "return_format"=>"json",
            "records"=>$user_id,
            "fields"=>array("first_name", "last_name", "email_address", "user_role")
        )), true)[0];
    }


    /**
     * Replaces keywords in the text with values
     * @param array $values assoc array with key = one of (table, first_name, last_name), value = respective value
     * @return string formatted body
     */
    private function formatBody($body, $values) {
        $values["[full-name]"] = $values["[first-name]"] . " " . $values["[last-name]"];
        foreach ($values as $keyword=>$value) {
            $body = str_replace($keyword, $value, $body);
        }
        return $body;
    }


    /**
     * @param array $cronAttributes A copy of the cron's configuration block from config.json.
     */
    public function send_download_emails($cronAttributes){
        $grantsProjectId    = $this->getSystemSetting("grants-project");
        $userProjectId      = $this->getSystemSetting("users-project");
        $contactName        = $this->getSystemSetting("contact-name");
        $contactEmail       = $this->getSystemSetting("contact-email"); 
        $databaseTitle      = $this->getSystemSetting("database-title");
        $databaseTitle      = is_null($databaseTitle) ? "Yale University Funded Grant Database" : $databaseTitle;
        
        // Check if emails are enabled in EM
        $settings = $this->get_email_settings();
        if (!$settings["enabled"]) return;

        // Get all downloads in the last 24 hours
        $allDownloads = $this->get_todays_downloads($grantsProjectId, $userProjectId);

        // Loop over users
        foreach ($allDownloads as $user_id=>$userDownloads) {

            // get user info
            $user = $this->get_user_info($user_id, $userProjectId);

            // don't bother admins with emails
            if ($user["user_role"] == 3) continue;
            
            // create download table
            $table = "<table><tr><th>Time</th><th>Grant Number</th><th>Grant Title</th><th>PI</th></tr>";
            foreach ($userDownloads as $download) {
                $table .= "<tr><td>".$download["time"]."</td>" 
                    . "<td>".$download["grant_number"]."</td>"    
                    . "<td>".$download["grant_title"]."</td>"    
                    . "<td>".$download["pi"]."</td>"    
                . "</tr>";
            }
            $table .= "</table>";

            // format the body to insert download table
            $formattedBody = $this->formatBody($settings["body"], array(
                "[download-table]"=>$table, 
                "[first-name]"=>$user["first_name"],
                "[last-name]"=>$user["last_name"],
                "[database-title]"=>$databaseTitle,
                "[contact-name]"=>$contactName, 
                "[contact-email]"=>$contactEmail
            ));
            
            // Send the email
            \REDCap::email($user["email_address"], $settings["from"], $settings["subject"], $formattedBody);

        }
    }


    #############################
    ###  GRANTS PAGE METHODS  ###
    #############################

    /**
     * Get choices of multiple choice, checkbox, radio items
     * 
     * @param array[] $metadata json-decoded array from call to REDCap::getDataDictionary
     * 
     * @return array[] arrays of choices per item
     */
    public function getChoices(array $metadata) {
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


    /**
     * Get name of grant instrument given a field on that instrument.
     * 
     * @param array[] $metadata json-decoded array from call to REDCap::getDataDictionary
     * @param string $fieldToTest a field that appears on the grants instrument
     * 
     * @return string grants instrument name
     */
    public function getGrantsInstrument(array $metadata, string $fieldToTest) {
        foreach ($metadata as $row) {
            if ($row['field_name'] == $fieldToTest) {
                return $row['form_name'];
            }
        }
        return;
    }


    /**
     * Get all choice options from the provided $fields combined into one unique array
     * 
     * @param array $choices array of choices from $this-getChoices()
     * @param array $fields fields to grab choices from
     * 
     * @return array all choices combined in one array
     */
    public function getAllChoices(array $choices, array $fields) {
        $result = array();
        foreach ($fields as $field) {
            $result = array_merge($result, $choices[$field]);
        }
        return array_unique($result);
    }
    
}