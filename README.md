# PHP SQLite Table Structure Updater

* ALPHA RELEASE
* Semi-automated updating of SQLite database table structures.
* Github: https://github.com/attogram/sqlite-table-structure-updater

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
```php

use Attogram\SQLiteTableStructureUpdater;

require_once('SQLiteTableStructureUpdater.php');

$updater = new SQLiteTableStructureUpdater();
//$updater->debug = TRUE; // debug mode

if( !$updater->set_database_file('./your-database-file.sqlite') ) {
    // handle error
}

// Set new table structures:
// Set array $tables in format: $tables[TABLE_NAME] = TABLE_SQL;

// Set new table structures via external file:
include('./your-new-table-structure-file.php');

// Or Set new table structures directly:
$tables = array(
    'a_table' => "CREATE TABLE 'a_table' ( 'id' INTEGER PRIMARY KEY, 'foo' TEXT )",
    'b_table' => "CREATE TABLE 'b_table' ( 'id' INTEGER PRIMARY KEY, 'bar' TEXT )",
);
$tables['c_table'] = "CREATE TABLE 'mytable' ( 'id' INTEGER PRIMARY KEY, 'foo' TEXT )";

if( !$updater->set_new_structures( $tables ) ) {
    // handle error
}

$updater->update();

```

# License

* MIT License

# TODO
- [ ] silent option: no debug(), notice() nor error()
- [ ] add timers
- [ ] option to delete or keep backup tables
