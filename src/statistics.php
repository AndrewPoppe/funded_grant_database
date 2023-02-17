<?php

namespace YaleREDCap\FundedGrantDatabase;

/** Authors: Jon Scherdin, Scott Pearson, Andrew Poppe */

# verify user access
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;
if (!$user_id || !isset($_COOKIE['grant_repo'])) {
	header("Location: " . $module->getUrl("src/index.php"));
}

// set project ids
$grantsProjectId = $module->configuration["projects"]["grants"]["projectId"];
$userProjectId = $module->configuration["projects"]["user"]["projectId"];

$role = $module->updateRole($user_id);
if ($role == 1 | empty($role)) {
	header("Location: " . $module->getUrl("src/index.php"));
}

# log visit
$module->log("Visited Statistics Page", array("user" => $user_id, "role" => $role));

# get metadata
$metadataJSON = \REDCap::getDataDictionary($grantsProjectId, "json");
$choices = $module->getChoices(json_decode($metadataJSON, true));

# get grant data records
$filterLogic = $role == 2 ? '[pi_netid] = "' . $user_id . '"' : NULL;
$grants_result = json_decode(\REDCap::getData(array(
	'project_id' => $grantsProjectId,
	'filterLogic' => $filterLogic,
	"return_format" => "json"
)), true);

# funding types
$funding_types = $choices['nih_funding_type'];


# create array to hold downloads
$downloads = array();
foreach ($grants_result as $row) {
	$downloads[$row['record_id']]['title'] = $row['grants_title'];
	$downloads[$row['record_id']]['number'] = $row['grants_number'];
	$downloads[$row['record_id']]['pi'] = $row['pi_fname'] . " " . $row['pi_lname'];
	$downloads[$row['record_id']]['type'] = $funding_types[$row['nih_funding_type']];
	$downloads[$row['record_id']]['department'] = $row['pi_department'] == 99 ? $row['pi_department_other'] : $choices["pi_department"][$row['pi_department']];
}


$user_result = json_decode(\REDCap::getData(array('project_id' => $userProjectId, "return_format" => "json")), true);

$netIds = array();
foreach ($user_result as $row) {
	$netIds[$row['netid']] = array($row['first_name'], $row['last_name']);
}

// Grab download logs 
$SQL = "SELECT timestamp ts, user, record 
	WHERE project_id = ? 
	AND message = 'Visited Download Page'";
$PARAMS = [$grantsProjectId];
// Restrict to grants where the user is the PI if role is not admin
if ($role == 2) {
	$SQL .= "AND pi_netid = ?";
	array_push($PARAMS, $user_id);
}
$result = $module->queryLogs($SQL, $PARAMS);

while ($row = $result->fetch_array()) {
	if ($netIds[$row['user']] && $netIds[$row['user']][0]) {
		$name = $netIds[$row['user']][0] . " " . $netIds[$row['user']][1];
		$username = $row['user'];
	} else if ($netIds[$row['user']]) {
		$name = '';
		$username = $row['user'];
	}

	$downloads[$row['record']]['hits'][] = array('ts' => $row['ts'], 'user' => $name, 'username' => $username);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<title><?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?> - Document Download Information</title>
	<link rel="shortcut icon" type="image" href="<?php echo \REDCap::escapeHtml($module->configuration["files"]["faviconImage"]) ?>" />
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("css/basic.css") ?>">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<style>
		table.dataTable tr.dtrg-group.dtrg-level-0 td {
			background-color: <?php echo \REDCap::escapeHtml($module->configuration["colors"]["accentColor"]); ?>;
			color: <?php echo \REDCap::escapeHtml($module->configuration["colors"]["accentTextColor"]); ?>;
		}

		div.dtsp-panesContainer tr.selected {
			background-color: <?php echo \REDCap::escapeHtml($module->configuration["colors"]["secondaryAccentColor"]); ?> !important;
			color: <?php echo \REDCap::escapeHtml($module->configuration["colors"]["secondaryTextColor"]); ?>;
		}

		div.dtsp-panesContainer tr.selected:hover {
			background-color: <?php echo $module->configuration["colors"]["secondaryHoverColor"]; ?> !important;
			color: <?php echo $module->configuration["colors"]["secondaryHoverTextColor"]; ?>;
			cursor: pointer;
		}
	</style>
</head>

<body>
	<br />
	<div style="padding-left:8%;  padding-right:10%; margin-left:auto; margin-right:auto;">
		<div id="header">
			<?php $module->createHeaderAndTaskBar($role); ?>
			<h3><?php echo $module->configuration["text"]["databaseTitle"] ?> - Usage Statistics</h3>
			<em>This page shows who has downloaded grant documents and when they did so.</em>
			<hr><br />
		</div>
		<div id="stats" class="dataTableParentHidden">
			<br />
			<table id="statsTable" class="dataTable" style="display:hidden;">
				<thead>
					<tr>
						<th>PI</th>
						<th>Grant Title</th>
						<th>Grant #</th>
						<th>NIH Funding Type</th>
						<th>PI Department</th>
						<th>User</th>
						<th>Username</th>
						<th>Access Datetime</th>
					</tr>
				</thead>
				<tbody>
					<?php
					# loop through each grant
					foreach ($downloads as $id => $value) {
						# loop through array of files download and display
						foreach ($value['hits'] as $log) {
							$timestamp = strtotime($log['ts']);
							echo "<tr>";
							echo "<td style='white-space:nowrap;'>" . \REDCap::escapeHtml($value['pi']) . "</td>";
							echo "<td>" . \REDCap::escapeHtml($value['title']) . "</td>";
							echo "<td style='text-align: center;'>" . \REDCap::escapeHtml($value['number']) . "</td>";
							echo "<td style='text-align: center;'>" . \REDCap::escapeHtml($value['type']) . "</td>";
							echo "<td style='text-align: center;'>" . \REDCap::escapeHtml($value['department']) . "</td>";
							echo "<td style='text-align: center;'>" . \REDCap::escapeHtml($log['user']) . "</td>";
							echo "<td style='text-align: center;'>" . \REDCap::escapeHtml($log['username']) . "</td>";
							echo "<td style='text-align: center;'>" . date('Y-m-d H:i:s', $timestamp) . "</td>";
							echo "</tr>";
						}
					}
					?>
				</tbody>
		</div>
		<script>
			(function($, window, document) {
				$(document).ready(function() {
					$('#statsTable').DataTable({
						order: [
							[0, 'asc'],
							[1, 'asc']
						],
						rowGroup: {
							dataSrc: [
								0,
								function(row) {
									return `${row[1]} (${row[2]})`;
								}
							]
						},
						columnDefs: [{
							targets: [0, 1, 2, 3, 4],
							visible: false,
							searchable: true,
						}],

						//pageLength: 1000,
						dom: 'lBfrtip',
						stateSave: true,
						buttons: [{
								extend: 'searchPanes',
								config: {
									cascadePanes: true
								}

							},
							{
								extend: 'searchBuilder'
							},
							'colvis',
							{
								text: 'Restore Default',
								action: function(e, dt, node, config) {
									dt.state.clear();
									window.location.reload();
								}
							},
							{
								extend: 'csv',
								exportOptions: {
									columns: [0, 1, 2, ':visible']
								}
							},
							{
								extend: 'excel',
								exportOptions: {
									columns: [0, 1, 2, ':visible']
								}
							},
							{
								extend: 'pdf',
								exportOptions: {
									columns: [0, 1, 2, ':visible']
								}
							}
						]

					});

					$('#stats').removeClass('dataTableParentHidden');

					$('#statsTable').DataTable().on('buttons-action', function(e, buttonApi, dataTable, node, config) {
						const text = buttonApi.text();
						if (text.search(/Panes|Builder/)) {
							$('.dt-button-collection').draggable();
						}
					});
				});
			}(window.jQuery, window, document));
		</script>
</body>

</html>