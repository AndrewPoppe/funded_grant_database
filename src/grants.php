<?php

/** Authors: Jon Scherdin, Scott Pearson, Andrew Poppe */


# verify user access
$user_id = $module->configuration["cas"]["use_cas"] ? $module->cas_authenticator->authenticate() : $userid;
if (!$user_id || !isset($_COOKIE['grant_repo'])) {
	header("Location: ".$module->getUrl("src/index.php", true));
}

# update user role
$role = $module->updateRole($user_id);

# make sure role is not empty
if ($role == "") {
	header("Location: ".$module->getUrl("src/index.php", true));
}

# log visit
$module->log("Visited Grants Page", array("user"=>$user_id, "role"=>$role));

# get metadata
$grantsProjectId = $module->configuration["projects"]["grants"]["projectId"];
$grantsMetadata = json_decode(\REDCap::getDataDictionary($grantsProjectId, "json"), true);
$grantsChoices = $module->getChoices($grantsMetadata);

$userProjectId = $module->configuration["projects"]["user"]["projectId"];
$userMetadata = json_decode(\REDCap::getDataDictionary($userProjectId, "json"), true);
$userChoices = $module->getChoices($userMetadata);

# get event_id
$eventId = $module->getEventId($grantsProjectId);

# get grants instrument name
$grantsInstrument = $module->getGrantsInstrument($grantsMetadata, 'grants_number');

// Pull all data in grants project
// Except do not include records not marked as "Complete" on grants instrument
$filterLogic = "[".$grantsInstrument."_complete] = 2";

// Also include grants only if they meet these conditions:
// 	1) Embargo date has been reached (today's date >= embargo date)
//  2) Expiration date has not passed (today's date < expiration date)
$filterLogic .= " and ([grant_visibility_embargo] = '' or datediff([grant_visibility_embargo], 'today', 'd', true) >= 0)";
$filterLogic .= " and ([grants_visibility_expiration] = '' or datediff([grants_visibility_expiration], 'today', 'd', true) < 0)";


// Also only include grants under these conditions
//  1) Grant visibility is not set to 0 (Not Visible)
// 	2) If Grant visibility is set to 1 (Admins only), user role is 3 (Admin)
//  3) If Grant visibility is set to 2 (Admins and PI/Author), user role is 3 (Admin) or user_id = pi_netid
//  4) Grant visibility is set to 3
$isAdmin = $role == 3 ? "true" : "false";
$filterLogic .= " and (([grant_visibility] = 1 and $isAdmin)";
$filterLogic .= " or ([grant_visibility] = 2 and ($isAdmin or [pi_netid] = '". $user_id ."'))";
$filterLogic .= " or [grant_visibility] = 3)";

// Also only include grants under these conditions
// If User is not admin, then [user_restrict_funding_type] setting includes the grant type
// TODO: Clean this up...
$fundingTypeUserAccess = json_decode(\REDCap::getData(array(
	"project_id"=>$userProjectId,
	"return_format"=>"json",
	"combine_checkbox_values"=>true,
	"exportAsLabels"=>false,
	"fields"=>"user_restrict_funding_type",
	"filterLogic"=>"[netid]='".$user_id."'")),true)[0]['user_restrict_funding_type'];
$fundingTypeUserAccessFilterLogic = " and (".
	implode(" or ", array_map(function($value) {
		$letter = $value[0];
		return "starts_with([nih_funding_type],'".$letter."')";
	}, explode(',', $fundingTypeUserAccess))).
	")";
$fundingTypeUserAccessFilterLogic = is_null($fundingTypeUserAccess) ? " and false" : $fundingTypeUserAccessFilterLogic;
$filterLogic .= $role == 3 ? "" : $fundingTypeUserAccessFilterLogic;


$grants = json_decode(\REDCap::getData(array(
	"project_id"=>$grantsProjectId, 
	"return_format"=>"json", 
	"combine_checkbox_values"=>true,
	"exportAsLabels"=>true,
	"filterLogic"=>$filterLogic
)), true);

// Add PI Name, replace "other" values with their specified values
$grants = array_map(function($record) {
	$record["pi"] = $record["pi_fname"]." ".$record["pi_lname"];
	$record["pi_title"] = $record["pi_title"] == "Other" ? $record["pi_title_other"] : $record["pi_title"];
	$record["pi_department"] = $record["pi_department"] == "Other" ? $record["pi_department_other"] : $record["pi_department"];
	$record["nih_funding_type"] = $record["nih_funding_type"] == "Other" ? $record["nih_funding_type_other"] : $record["nih_funding_type"];
	return $record;
}, $grants);

// get column orders
$defaultColumns = array(
	array("label"=>"PI", 				"field"=>"pi", 				 		"visible"=>true,	"default"=>true, "data"=>"pi"),
	array("label"=>"Grant Title", 		"field"=>"grants_title", 	 		"visible"=>true,	"default"=>true, "data"=>"title"),
	array("label"=>"Department", 		"field"=>"pi_department", 	 		"visible"=>true,	"default"=>true, "data"=>"department"),
	array("label"=>"Funding Agency", 	"field"=>"funding_agency",   		"visible"=>true,	"default"=>true, "data"=>"fundingAgency"),
	array("label"=>"Research Type", 	"field"=>"research_type",	 		"visible"=>true,	"default"=>true, "data"=>"researchType"),
	array("label"=>"Abstract",		 	"field"=>"grants_abstract",	 		"visible"=>true,	"default"=>true, "data"=>"abstract"),
	array("label"=>"Project Terms", 	"field"=>"project_terms", 	 		"visible"=>true,	"default"=>true, "data"=>"terms"),
	array("label"=>"Human Subjects", 	"field"=>"human_subjects_yn",		"visible"=>true,	"default"=>true, "data"=>"humanSubjects"),
	array("label"=>"Vertebrate Animals","field"=>"vertebrate_animals_yn",	"visible"=>true,	"default"=>true, "data"=>"animals"),
	array("label"=>"NIH Submission #",	"field"=>"nih_submission_number",	"visible"=>true,	"default"=>true, "data"=>"submissionNumber"),
	array("label"=>"NIH Funding Type", 	"field"=>"nih_funding_type", 		"visible"=>true,	"default"=>true, "data"=>"fundingType"),
	array("label"=>"Grant Award Date", 	"field"=>"grant_award_date", 		"visible"=>true,	"default"=>true, "data"=>"date"),
	array("label"=>"Grant Sections", 	"field"=>"grant_sections", 	 		"visible"=>true,	"default"=>true, "data"=>"grantSections", 	"type"=>"grantSections"),
	array("label"=>"Acquire", 			"field"=>"download", 		 		"visible"=>true,	"default"=>true, "data"=>"acquire", 		"searchable"=>false)	
);
$columnOrders = $module->getColumnOrders($module->configuration["customFields"]["fields"], $defaultColumns);
ksort($columnOrders);
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<title><?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?></title>
		<link rel="shortcut icon" type="image" href="<?php echo \REDCap::escapeHtml($module->configuration["files"]["faviconImage"]) ?>"/> 
		<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.css"/>
 		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.5.3/css/colReorder.dataTables.min.css">
		<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("css/basic.css") ?>">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
		<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.js"></script>
		<script type="text/javascript" src="https://cdn.datatables.net/colreorder/1.5.3/js/dataTables.colReorder.min.js"></script>
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
		<br/>
		<div id="container" style="padding-left:8%;  padding-right:10%; margin-left:auto; margin-right:auto; ">
			<div id="header">
				<?php $module->createHeaderAndTaskBar($role);?>
				<h3><?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?></h3>
				<em>You may download grant documents by clicking "download" links below. The use of the grants document database is strictly limited to authorized individuals, and you are not permitted to share files or any embedded content with other individuals. All file downloads are logged.</em>
				<hr/>
			</div>

			<div id="grants" class="dataTableParentHidden">
				<br/>
				<table id="grantsTable" class="dataTable">
				<thead>
					<tr>
						<?php
							foreach ($columnOrders as $column) {
								echo "<th>".$column["label"]."</th>";
							}
						?>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($grants as $id=>$row) {
						
						$url = $module->getUrl("src/download.php?p=$grantsProjectId&id=" .
							$row['grants_file'] . "&s=&page=register_grants&record=" . $row['record_id'] . "&event_id=" .
							$eventId . "&field_name=grants_file", true);
						$row['download'] = "<a href='".\REDCap::escapeHtml($url)."'>Download</a>";

						echo "<tr>";
							foreach ($columnOrders as $column) {
								if ($column["field"] == "download") {
									echo "<td style='text-align: center;'>".$row[$column["field"]]."</th>";
								} else {
									echo "<td style='text-align: center;'>".\REDCap::escapeHtml($row[$column["field"]])."</th>";
								}
							}
						echo "</tr>";
					}
					?>
				</tbody>
				</table>
			</div>
		</div>
		<script>
		(function($, window, document) {
			
			function createOption(option, column) {
				return {
					label: option,
					value: function(rowData, rowIdx) {
						return rowData[column].includes(option);
					}
				}
			}
			
			function createPane(options, column, header) {
				return {
					header: header,
					options: options.map(option => createOption(option, column))
				}
			}

			let columns = <?php
				echo '[';
				foreach ($columnOrders as $column) {
					echo '{';
					if (!isset($column['data'])) {
						echo '"data":"'.$column["field"].'-custom",';
					}
					foreach ($column as $field=>$value) {
						echo '"'.$field.'": "'.$value.'",';
					}
					echo '},';
				}
				echo ']';
			?>;

			let grantSections = <?php
				echo '[';
				foreach($grantsChoices["grant_sections"] as $grantSection) {
					echo '"' . $grantSection . '",';
				}
				echo ']';
			?>;
			console.log(grantSections);

			columns = columns.map(function(column) {
				if (column.field === "project_terms") {
					column.render = function(data,type,row) {
						if (type === 'display') {
							return data.replace(/,/g, '<br>');
						}
						return data;
					};
				}
				if (column.visible === "" || column.visible === "false" || column.visible == 0) {
					column.visible = false;
				} else {
					column.visible = true;
				}
				return column;
			});
				
			$(document).ready( function () {
				$('#grantsTable').DataTable({
					
					
					columns: columns,
					//pageLength: 1000,
					dom: 'lBfrtip',
					stateSave: true,
					colReorder: true,
					buttons: [
						{
							extend: 'searchPanes',
							config: {
								cascadePanes: true,
								panes: [
									createPane(grantSections, "grantSections", 'Grant Section')
								]
							}
							
						},
						{
							extend: 'searchBuilder',
							config: {
								conditions: {
									grantSections: {
										contains: {
											conditionName: 'Contains',
											init: function (that, fn, preDefined = null) {
												let el = $('<select/>').on('input', function() { fn(that, this) });
												grantSections.forEach(option => {
													el[0].options.add($(`<option value="${option}" label="${option}"></option`)[0]);
												});
												// If there is a preDefined value then add it
												if (preDefined !== null && preDefined.length > 0) {
													$(el).val(preDefined[0]);
												}
												return el;
											},
											inputValue: function (el) {
												return $(el[0]).val();
											},
											isInputValid: function (el, that) {
												return $(el[0]).val().length !== 0;
											},
											search: function(value, comparison) {
												return value.includes(comparison);
											}
										}
									}
								}
							}
						},
						'colvis',
						{
							text: 'Restore Default',
							action: function (e, dt, node, config) {
								dt.state.clear();
								window.location.reload();
							}
						},
						<?php if ($role == 3) { ?>
						{
							extend: 'csv',
							exportOptions: { columns: ':visible' }
						},
						{ 
							extend: 'excel',
							exportOptions: { columns: ':visible' }
						},
						{ 
							extend: 'pdf',
							exportOptions: { columns: ':visible' }
						}
						<?php } ?>
					]
				});

				$('#grants').removeClass('dataTableParentHidden');
				
				$('#grantsTable').DataTable().on( 'buttons-action', function ( e, buttonApi, dataTable, node, config ) {
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

