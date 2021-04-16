<?php

/**Author: Andrew Poppe */

namespace YaleREDCap\FundedGrantDatabase;

class FundedGrantDatabase extends \ExternalModules\AbstractExternalModule {

    /**************************\
     * CONFIGURATION SETTINGS *
    \**************************/

    public $config = array(
        "projects"      =>array(),
        "contact"       =>array(),
        "emailUsers"    =>array(),
        "colors"        =>array(),
        "files"         =>array(),
        "title"         =>array(),
        "customFields"  =>array()
    );

    /**
     * Get all configuration settings for the module.
     * @return void
     */
    public function get_config() {
        $this->get_projects();
        $this->get_contact();
        
        var_dump($this->config);
        die();
    }


    /*************************\
     * CONFIGURATION METHODS *
    \*************************/

    /////  PROJECTS  /////

    /**
     * Get and verify project IDs from system settings.
     * @return void
     */
    private function get_projects() {
        $grantsProjectId    = $this->getSystemSetting("grants-project");
        $userProjectId      = $this->getSystemSetting("users-project");
        $this->checkPID($grantsProjectId, 'Grants Project');
        $this->checkPID($userProjectId, 'User Project');

        // Make sure grants project has requisite fields
        $grantTestFields = array('grants_pi', 'grants_title', 'grants_type', 'grants_date', 'grants_number', 'grants_department', 'grants_thesaurus');
        $grantFields = $this->getFieldNames($grantsProjectId);
        if (!$this->verifyProjectMetadata($grantFields, $grantTestFields)) {
            die('The project (PID#'.$grantsProjectId.') is not a valid grants project. Contact your REDCap Administrator.');
        }

        // Make sure user project has requisite fields
        $userTestFields = array('user_id', 'user_expiration', 'user_role');
        $userFields = $this->getFieldNames($userProjectId);
        if (!$this->verifyProjectMetadata($userFields, $userTestFields)) {
            die ('The project (PID#'.$userProjectId.') is not a valid users project. Contact your REDCap Administrator.');
        }

        $this->config["projects"] = array(
            "grants"=>$grantsProjectId, 
            "user"=>$userProjectId
        );
    }

    /**
     * Checks whether the provided PID corresponds with an active project.
     * @param string $pid A PID to test
     * @param string $label A label to use in error messages if necessary
     * @return void
     */
    private function checkPID($pid, $label) {
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
     * @param string[] $projectFields fields in the project
     * @param string[] $fieldsToTest fields the project should have
     * @return boolean Whether the project is verified or not
     */
    private function verifyProjectMetadata($projectFields, $fieldsToTest) {
        foreach ($fieldsToTest as $testField) {
            if (!in_array($testField, $projectFields, true)) return false;
        }
        return true;
    }
    
    /**
     * Gets field names from a project
     * @param string $pid The project's ID
     * @return string[] field names
     */
    private function getFieldNames($pid) {
        global $module;
        $sql = "SELECT field_name FROM redcap_metadata WHERE project_id = ?";
        $query = $module->query($sql, $pid);
        $result = array();
        while ($row = $query->fetch_row()) {
            array_push($result, $row[0]);
        }
        return $result;
    }


    /////  CONTACT  /////

    /**
     * Get contact person info from system settings.
     * @return void
     */
    private function get_contact() {
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



    /*********************************************\
     * THIS SECTION DEALS WITH EMAILING USERS    *
     * WHEN THEY HAVE DOWNLOADED GRANT DOCUMENTS *
    \*********************************************/

    /**
     * Grabs system settings related to emailing users
     * @return array Contains enabled status ("enabled", from address ("from"), subject ("subject"), and body text ("body")
     */
    function get_email_settings() {
        $result = array();
        $result["enabled"] = $this->getSystemSetting('email-users');
        $result["from"] = $this->getSystemSetting('email-from-address');
        $result["subject"] = $this->getSystemSetting('email-subject');
        $result["body"] = $this->getSystemSetting('email-body');

        if (is_null($result["subject"])) $result["subject"] = "Funded Grant Database Document Downloads";
        if (is_null($result["body"])) $result["body"] = "<br>Hello [full-name],<br><br>This message is a notification that you have downloaded grant documents from the following grants using the <strong>[database-title]</strong>:<br><br>[download-table]<br><br>Questions? Contact [contact-name] (<a href=\"mailto:[contact-email]\">[contact-email]</a>)";        

        return $result;
    }

    /**
     * Grabs download information for all grants in the last 24 hours
     * @return array array with one entry per user, containing an array of timestamps and grant ids 
     */
    function get_todays_downloads($grantsProjectId, $userProjectId) {

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
    function get_user_info($user_id, $userProjectId) {
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
    function formatBody($body, $values) {
        $values["[full-name]"] = $values["[first-name]"] . " " . $values["[last-name]"];
        foreach ($values as $keyword=>$value) {
            $body = str_replace($keyword, $value, $body);
        }
        return $body;
    }

    /**
     * @param array $cronAttributes A copy of the cron's configuration block from config.json.
     */
    function send_download_emails($cronAttributes){
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
}