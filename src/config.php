<?php

/** Author: Andrew Poppe */


$module->get_config();



// Aesthetics







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