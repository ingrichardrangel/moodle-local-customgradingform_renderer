<?php

$string['pluginname'] = 'Rubric Importer from CSV';
$string['importfromcsv'] = 'Import rubric from CSV';
$string['choosecsvfile'] = 'Choose CSV file';
$string['submitcsv'] = 'Import rubric';
$string['csvrequired'] = 'You must select a valid CSV file.';
$string['importsuccess'] = 'Rubric imported successfully.';
$string['importerror'] = 'An error occurred while importing the rubric.';

// Maximum score validation
$string['maxlevelscore'] = 'Maximum score per level';
$string['maxlevelscore_desc'] = 'Defines the maximum allowed score for each evaluation level within a criterion. If any level in the CSV file exceeds this value, an error will be shown during import.';
$string['enablemaxlevelscore'] = 'Enable maximum score validation';
$string['enablemaxlevelscore_desc'] = 'If enabled, the system will validate that no level exceeds the configured maximum value.';

// Minimum score validation
$string['enableminlevelscore'] = 'Enable minimum score validation';
$string['enableminlevelscore_desc'] = 'If enabled, the system will validate that at least one level has the configured minimum score.';
$string['minlevelscore'] = 'Minimum score per level';
$string['minlevelscore_desc'] = 'Minimum allowed score for a level. Typically this is 0.';
