<?php

/** Authors: Jon Scherdin, Scott Pearson, Andrew Poppe */


$timestamp = date('Y-m-d');
$role = "";
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;

# query table to authenticate user
$result = $module->authenticate($user_id, $timestamp);

# get role
if (db_num_rows($result) > 0) {
	$role = db_result($result, 0, 1);
}

# log visit
$module->log("Visited Index Page", array("user"=>$user_id, "role"=>$role));

# if they have agreed to the terms, create the cookie and redirect them to the grants page
if (isset($_POST['submit'])) {
	setcookie('grant_repo', $role, ["httponly"=>true]);      
    header("Location: ".$module->getUrl("src/grants.php", true));
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("css/basic.css")?>">
    </head>
    <body style="background-color: #f9f9f9;">
        <br/>    
        <div style="padding-left:8%;  padding-right:10%; margin-left:auto; margin-right:auto; ">
            <div style="padding: 7.5px; background-color: <?=\REDCap::escapeHtml($module->configuration["colors"]["accentColor"])?>;"></div>
            <img src="<?php echo \REDCap::escapeHtml($module->configuration["files"]["logoImage"]) ?>" style="vertical-align:middle"/>
            <hr>
            <h3><?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?></h3>
            <br/>
            <?php if ($role != "") { ?>
                <p>
                    <strong>
                        NOTICE: You must agree to the following terms before using the <?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?>
                    </strong>
                </p>
                <ul> 
                    <li>I agree to keep the contents of the example grants confidential.</li>
                    <li>I will not share any part(s) of the grants in the database.</li>
                    <li>I agree not to utilize any text of the grant in my own grant.</li>
                    <li>I understand that the individuals who provided grants will be able to view a list of names of those who accessed their grants.</li>
                    <li>I agree to provide a copy of my grant after submission to be kept on file and reviewed for compliance to this agreement.</li>
                </ul>
                <form  method="post">
                    <input type="submit" value="I agree to all terms above" name="submit" style="cursor: pointer;">
                </form>
            <?php } else { ?>
                <span>
                    Please contact <?php echo \REDCap::escapeHtml($module->configuration["contact"]["name"]) ?> 
                    at <a href="mailto:<?php echo \REDCap::escapeHtml($module->configuration["contact"]["email"]) ?>"> <?php echo \REDCap::escapeHtml($module->configuration["contact"]["email"]) ?></a> 
                    to gain access to the <?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?>
                </span>
            <?php } ?>
        </div>
    </body>
</html>
