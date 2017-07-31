<?php
// SQLite Table Structure Updater

use Attogram\SQLiteTableStructureUpdater;

set_time_limit(500);

require_once('SQLiteTableStructureUpdater.php');

?><!doctype html>
<html><head><title>SQLite Table Structure Updater</title>
<style>
body { background-color:white; color:black; margin:0px; padding:10px; font-family:monospace; }
a { text-decoration:none; }
.debug { background-color:lightyellow; border:1px solid yellow; margin:0px; padding:0px; }
.notice { background-color:#ccffcc; border:1px solid #33ff33; margin:0px; padding:0px; }
.error { background-color:#ffcccc; border:1px solid #ff3333; margin:0px; padding:0px; }
</style>
</head><body><?php

$database_file = @$_GET['d'];

$structure_file = @$_GET['s'];
if( !$structure_file ) { $structure_file = 'new.tables.php'; }

print '<h1><a href="./">SQLite Table Structure Updater v' . __STSU__ .'</a></h1>';

print '<form action="" method="GET">'
. 'Database File: <input type="text" name="d" value="' 
. urlencode($database_file) . '" size="30" />'
. '<br />New Structure File: <input type="text" name="s" value="' 
. urlencode($structure_file) . '" size="30" />'
. '<br /><input type="checkbox" name="debug" /> Debug Mode'
. '<br /><input type="submit" value="    Run Updater    ">'
. '</form><hr />';


if( !$database_file ) {
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

$updater->set_database_file($database_file);
if( !$updater->database_loaded() ) {
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
    print '<br /><br /><hr />STSU ' . gmdate('Y-m-d H:i:s') . '</body></html>';
    exit;
}
