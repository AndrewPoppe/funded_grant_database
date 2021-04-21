<?php

/** Authors: Jon Scherdin, Scott Pearson, Andrew Poppe */


# verify user access
if (!isset($_COOKIE['grant_repo'])) {
	header("Location: ".$module->getUrl("src/index.php"));
}

// set configs
//$module->get_config();

# update user role
$role = $module->updateRole($userid);

# make sure role is not empty
if ($role == "") {
	header("Location: ".$module->getUrl("src/index.php"));
}

# log visit
$module->log("Visited Grants Page", array("user"=>$userid, "role"=>$role));

$awards = array(
	"k_awards" => "K Awards",
	"r_awards" => "R Awards",
	"misc_awards" => "Misc. Awards",
	"lrp_awards" => "LRP Awards",
	"va_merit_awards" => "VA Merit Awards",
	"f_awards" => "F Awards",
);

# get metadata
$grantsProjectId = $module->configuration["projects"]["grants"]["projectId"];
$metadata = json_decode(\REDCap::getDataDictionary($grantsProjectId, "json"), true);
$choices = $module->getChoices($metadata);

# get event_id
$eventId = $module->getEventId($grantsProjectId);

# get grants instrument name
$grantsInstrument = $module->getGrantsInstrument($metadata, 'grants_number');

// Pull all data in grants project
// Except do not include records not marked as "Complete" on grants instrument
$grants = json_decode(\REDCap::getData(array(
	"project_id"=>$grantsProjectId, 
	"return_format"=>"json", 
	"combine_checkbox_values"=>true,
	"exportAsLabels"=>true,
	"filterLogic"=>"[".$grantsInstrument."_complete] = 2"
)), true);

// get award options
$awardOptions = $module->getAllChoices($choices, array_keys($awards));

// get award option values
$awardOptionValues = $module->combineValues($grants, array_keys($awards));

// get column orders
$defaultColumns = array(
	array("label"=>"PI", 			"field"=>"grants_pi", "default"=>true, "data"=>"pi"),
	array("label"=>"Grant Title", 	"field"=>"grants_title", "default"=>true,"data"=>"title"),
	array("label"=>"Award Type", 	"field"=>"grants_type", "visible"=>false, "default"=>true, "data"=>"awardType"),
	array("label"=>"Award Option", 	"field"=>"award_option_value", "default"=>true, "visible"=>false, "data"=>"awardOption","type"=>"awardOption"),
	array("label"=>"Grant Date", 	"field"=>"grants_date", "default"=>true, "data"=>"date"),
	array("label"=>"Grant", 		"field"=>"grants_number", "default"=>true, "data"=>"number"),
	array("label"=>"Department", 	"field"=>"grants_department", "visible"=>false, "default"=>true, "data"=>"department"),
	array("label"=>"Acquire", 		"field"=>"download", "default"=>true, "searchable"=>false, "data"=>"acquire"),
	array("label"=>"Thesaurus", 	"field"=>"grants_thesaurus", "visible"=>false, "default"=>true, "data"=>"thesaurus")
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
		<br/>
		<div id="container" style="padding-left:8%;  padding-right:10%; margin-left:auto; margin-right:auto; ">
			<div id="header">
				<?php $module->createHeaderAndTaskBar($role);?>
				<h3><?php echo \REDCap::escapeHtml($module->configuration["text"]["databaseTitle"]) ?></h3>
				<em>You may download grant documents by clicking "download" links below. The use of the grants document database is strictly limited to authorized individuals and you are not permitted to share files or any embedded content with other individuals. All file downloads are logged.</em>
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
							$eventId . "&field_name=grants_file");
						$row['download'] = "<a href='".\REDCap::escapeHtml($url)."'>Download</a>";
						$row['award_option_value'] = $awardOptionValues[$id];

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
					value: function(rowData, rowIdx) {return rowData[column].includes(`--${option}--`);}
				}
			}
			function createPane(options, column, header) {
				return {
					header: header,
					options: options.map(option => createOption(option, column))
				}
			}
			let awardOptions = <?php 
				echo '[';
				foreach ($awardOptions as $awardOption) {
					echo '"'.$awardOption.'",';
				}
				echo ']'; ?>;
			let awardOptionValues = <?php
				echo '[';
				foreach ($awardOptionValues as $awardOptionValue) {
					echo '"'.$awardOptionValue.'",';
				}
				echo ']'; ?>;
			let awardOptionsCombined = awardOptionValues.reduce((acc, val)=> acc+val, "");
			let awardOptionDropdownValues = awardOptions.filter(option => awardOptionsCombined.includes(`--${option}--`));

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
			columns = columns.map(function(column) {
				if (column.field === "award_option_value") {
					column.render = function(data,type,row) {if (type === 'display') {return data.replace(/--/g, ', ').replace(/^(, )(, )*|(, )*(, )$/g, '');}return data;};
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
					buttons: [
						{
							extend: 'searchPanes',
							config: {
								cascadePanes: true,
								panes: [
									createPane(awardOptions, "awardOption", 'Award Option')
								]
							}
							
						},
						{
							extend: 'searchBuilder',
							config: {
								conditions: {
									awardOption: {
										contains: {
											conditionName: 'Contains',
											init: function (that, fn, preDefined = null) {
												let el = $('<select/>').on('input', function() { fn(that, this) });
												awardOptionDropdownValues.forEach(option => {
													el[0].options.add($(`<option value="${option}" label="${option}"></option`)[0]);
												});

												if (preDefined !== null) {
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
												console.log('value:', value, '; comparison: ', comparison );
												return value.includes(`--${comparison}--`);
											}
										}
									}
								}
							}
						},
						'colvis',
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

