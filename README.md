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

// Set new table structures via file, example:
include('./your-new-table-structure-file.php'); // sets array $table
if( !isset($tables) || !is_array($tables) ) {
    // handle error
}

// Or set new table structures directly, examples:
// $tables = array(
//  'example' => "CREATE TABLE 'example' ( 'id' INTEGER PRIMARY KEY, 'foo' TEXT )",
// );
// $tables['table2'] = "CREATE TABLE IF NOT EXISTS 'table2' ( 'id' INTEGER PRIMARY KEY, 'bar' TEXT )";
// ...

while( list($table_name,$table_sql) = each($tables) ) {
    $updater->set_new_structure($table_name, $table_sql);
}

$updater->update();

```

# License

* MIT License

# TODO
- [ ] set_new_structure( (array)$tables ) instead of multi calls of set_new_structure($table_name, $table_sql)
- [ ] silent option: no debug(), notice() nor error()
- [ ] add timers
- [ ] option to delete or keep backup tables
