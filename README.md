# PHP SQLite Table Structure Updater

* ALPHA RELEASE
* Semi-automated updating of SQLite database table structures.
* Github: https://github.com/attogram/sqlite-table-structure-updater

# Usage:


* Update via Web Form:
  * Enter Location of your SQLite Database File
  * Enter Location of your New Table Structures File
    * must be a PHP file that sets array `$tables` in format: `$tables['name'] = "CREATE TABLE 'name' ( ... )";`
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
$tables['c_table'] = "CREATE TABLE 'c_table' ( 'id' INTEGER PRIMARY KEY, 'foobar' TEXT )";

if( !$updater->set_new_structures( $tables ) ) {
    // handle error
}

$updater->update();

```

# License

* MIT License

# TODO
- [ ] option to delete or keep backup tables
- [ ] silent option: no debug(), notice() nor error()
- [ ] normalize_sql() - remove any trailing semi-colons ;

