# PHP SQLite Table Structure Updater

* ALPHA RELEASE
* Semi-automated updating of SQLite database table structures.

# Usage:


* Create a New Table Structures File:

 * PHP file
 * sets an array $table in format: 
 * $table['name'] = "CREATE TABLE 'name' ( ... )";

* Update via Web Form:

 * Enter SQLite Database file location
 * Enter New table structure file location
 * Click Run Updater
 
* Update via Code:

    use Attogram\SQLiteTableStructureUpdater;
    require_once('SQLiteTableStructureUpdater.php');
	$updater = new SQLiteTableStructureUpdater();
    //$updater->debug = TRUE; // debug mode
	$updater->set_database_file('./your-database-file.sqlite');	
	if( !$updater->database_loaded() ) {
		// handle error 
	}
	include('./your-new-tabe-structure-file.php'); // sets array $table
	if( !isset($tables) || !is_array($tables) ) {
		// handle error
	}
	while( list($table_name,$table_sql) = each($tables) ) {
		$updater->set_new_structure($table_name, $table_sql);
	}	
	$updater->update();

# License

* MIT License

