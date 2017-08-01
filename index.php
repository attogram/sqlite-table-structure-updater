<?php
// SQLite Table Structure Updater

use Attogram\SQLiteTableStructureUpdater;

set_time_limit(500);

require_once('SQLiteTableStructureUpdater.php');

?><!doctype html>
<html><head><title>SQLite Table Structure Updater</title>
<style>
body { background-color:white; color:black; font-family:monospace;
margin:5px 10px 5px 10px; padding:0px;  }
a { text-decoration:none; }
h1 { display:inline;  }
form { display:inline-block; border:1px solid blue; padding:10px; line-height:2; }
.debug { background-color:#ffffcc; border:1px solid #ffffaa; margin:0px; padding:2px; }
.notice { background-color:#ccffcc; border:1px solid #33ff33; margin:0px; padding:2px; }
.error { background-color:#ffcccc; border:1px solid #ff3333; margin:0px; padding:4px; }
</style>
</head><body><?php

$database_file = @$_GET['d'];

$structure_file = @$_GET['s'];
if( !$structure_file ) { $structure_file = 'new.tables.php'; }

print '<h1><a href="./">SQLite Table Structure Updater v' . __STSU__ .'</a></h1>';

print '<p><form action="" method="GET">'
. 'Database File : <input type="text" name="d" value="'
. urlencode($database_file) . '" size="30" />'
. '<br />Structure File: <input type="text" name="s" value="'
. urlencode($structure_file) . '" size="30" />'
. '<br /><input type="checkbox" name="debug" /> Debug Mode'
. '<br /><input type="submit" value="    Run SQLite Table Structure Updater    ">'
. '</form></p>';


if( !$database_file ) {
    print '<p class="error">Please select a SQLite Database File</p>';
    footer();
}

$updater = new SQLiteTableStructureUpdater();

if( isset($_GET['debug']) ) {
    $updater->debug = TRUE;
}

if( !file_exists($database_file) ) {
    $updater->error('Creating New Database: ' . htmlspecialchars($database_file));
} else {
    if( !is_readable($database_file) ) {
        $updater->debug('ERROR: database file is not readable');
        footer();
    }
    if( !is_writeable($database_file) ) {
        $updater->debug('ERROR: database file is not writeable');
        footer();
    }
}

if( !$updater->set_database_file($database_file) ) {
    $updater->error('ERROR: can not open database');
    footer();
}

if( !is_readable($structure_file) ) {
    $updater->error('ERROR: Structure File not readable');
    footer();
}
include($structure_file);
if( !isset($tables) ) {
    $updater->error('ERROR: No tables defined in Structure File');
    footer();
}

while( list($table_name,$table_sql) = each($tables) ) {
    $updater->set_new_structure( $table_name, $table_sql );
}

$updater->update();

$updater->debug('<pre>' . print_r($updater,1) . '</pre>');

footer();


/////////////////////////////////////
function footer() {
    print '<br /><br /><hr />'
    . gmdate('Y-m-d H:i:s')
    . ' UTC - <a target="github" href="https://github.com/attogram/sqlite-table-structure-updater">'
        . 'attogram/sqlite-table-structure-updater v' . __STSU__ . '</a>'
    . '</body></html>';
    exit;
}
