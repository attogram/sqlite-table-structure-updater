<?php
// SQLite Table Structure Updater

namespace Attogram;

define('__STSU__','0.0.2');

//////////////////////////////////////////////////////////
class stsu_utils {

    public $debug;

    public function debug($msg) {
        if( !$this->debug ) { return; }
        print '<p class="debug">' . print_r($msg,1) . '</p>';
    }

    public function notice($msg) {
        print '<p class="notice">' . print_r($msg,1) . '</p>';
    }

    public function error($msg) {
        print '<p class="error">' . print_r($msg,1) . '</p>';
    }

}

//////////////////////////////////////////////////////////
class stsu_database_utils EXTENDS stsu_utils  {

    protected $db;
    protected $database_file;
    protected $last_insert_id;
    protected $last_error;

    public function database_loaded() {
        if( !$this->db ) { return FALSE; }
        return TRUE;
    }

    protected function normalize_sql($sql) {
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = str_replace('"', "'", $sql);
        return trim($sql);
    }

    protected function open_database() {
        $this->debug('open_database: ' . $this->database_file);
        if( !in_array('sqlite', \PDO::getAvailableDrivers() ) ) {
            $this->error('open_database: ERROR: no sqlite Driver');
            return $this->db = FALSE;
        }
        try {
            return $this->db = new \PDO('sqlite:'. $this->database_file);
        } catch(\PDOException $e) {
            $this->error('open_database: ' . $this->database_file . '  ERROR: '. $e->getMessage());
            return $this->db = FALSE;
        }
    }

    protected function query_as_array( $sql, $bind=array() ) {
        $this->debug( $this->normalize_sql($sql) );

        if( !$this->db ) { $this->open_database(); }
        if( !$this->db ) { return FALSE; }

        $statement = $this->db->prepare($sql);
        if( !$statement ) {
            $this->error('query_as_array(): ERROR PREPARE');
            return array();
        }
        while( $xbind = each($bind) ) {
            $this->debug('query_as_array(): bindParam '. $xbind[0] .' = ' . $xbind[1]);
            $statement->bindParam( $xbind[0], $xbind[1]);
        }
        if( !$statement->execute() ) {
            $this->error('ERROR EXECUTE: '.print_r($this->db->errorInfo(),1));
            return array();
        }

        $response = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if( !$response && $this->db->errorCode() != '00000') {
            $this->error('query_as_array(): ERROR FETCH: '.print_r($this->db->errorInfo(),1));
            $response = array();
        }

        return $response;
    }

    protected function query_as_bool( $sql, $bind=array() ) {
        $this->debug( $this->normalize_sql($sql) );
        if( $bind ) {
            $this->debug('query_as_bool: BIND: <pre>' . htmlentities(print_r($bind,1)) . '</pre>' );
        }
        if( !$this->db ) { $this->open_database(); }
        if( !$this->db ) { return FALSE; }
        $this->last_error = FALSE;
        $statement = $this->db->prepare($sql);
        if( !$statement ) {
            $this->last_error = $this->db->errorInfo();
            $this->error('ERROR: ' . print_r($this->last_error,1) );
            return FALSE;
        }
        while( $xbind = each($bind) ) {
            $statement->bindParam( $xbind[0], $xbind[1] );
        }
        if( !$statement->execute() ) {
            $this->last_error = $this->db->errorInfo();
            if( $this->last_error[0] == '00000' ) {
                //$this->debug('NULL EVENT: ' . trim($sql));
                return TRUE;
            }
            $this->error('query_as_bool: prepare failed: ' . print_r($this->last_error,1) );
            return FALSE;
        }
        $this->last_error = $this->db->errorInfo();
        return TRUE;
    } // end function query_as_bool()

    protected function vacuum() {
        if( $this->query_as_bool('VACUUM') ) {
            return TRUE;
        }
        $this->error('FAILED to VACUUM');
        return FALSE;
    }

    protected function begin_transaction() {
        if( $this->query_as_bool('BEGIN TRANSACTION') ) {
            return TRUE;
        }
        $this->error('FAILED to BEGIN TRANSACTION');
        return FALSE;
    }

    protected function commit() {
        if( $this->query_as_bool('COMMIT') ) {
            return TRUE;
        }
        $this->error('FAILED to COMMIT');
        return FALSE;
    }

    protected function get_table_size($table_name) {
        $size = $this->query_as_array('SELECT count(rowid) AS count FROM ' . $table_name);
        if( isset($size[0]['count']) ) {
            return $size[0]['count'];
        }
        $this->error('Can not get table size: ' . $table_name);
        return 0;
    }

} // end class database_utils

//////////////////////////////////////////////////////////
class SQLiteTableStructureUpdater extends stsu_database_utils {

    protected $tables_current;
    protected $tables_new;
    protected $sql_current;
    protected $sql_new;

    public function __construct() {
        $this->debug('__construct()');
    }

    public function set_database_file($file) {
        $this->debug("set_database_file($file)");
        $this->database_file = $file;
        $this->tables_current = array();
        $this->sql_current = array();
        $this->set_table_info();
    }

    public function set_new_structure($table_name, $sql) {
        $this->debug("set_new_structure($table_name, $sql)");
        $sql = $this->normalize_sql($sql);
        $sql = str_ireplace('CREATE TABLE IF NOT EXISTS','CREATE TABLE',$sql);
        $this->sql_new[$table_name] = $sql;
        $this->tables_new[$table_name] = array();
    }

    public function update() {
        $this->debug("update()");
        $to_update = array();
        foreach( array_keys($this->tables_new) as $name ) {
            $old = $this->normalize_sql( @$this->sql_current[$name] );
            $new = $this->normalize_sql( @$this->sql_new[$name] );
            $this->debug("$name: OLD: $old");
            $this->debug("$name: NEW: $new");
            if( $old == $new ) {
                continue;
            }
            $this->debug('Needs updating: ' . $name);
            $to_update[] = $name;
        }
        if( !$to_update ) {
            $this->notice(
                'Nothing to update: '
                . sizeof($this->tables_new) . ' tables checked OK'
            );
            return TRUE;
        }
        $this->notice(
            sizeof($to_update) . ' tables to update: '
            . implode($to_update,', ')
        );
        foreach( $to_update as $table_name ) {
            $this->update_table($table_name);
        }
    } // end function update()

    protected function update_table($table_name) {
        $this->debug("update_table($table_name)");
        $tmp_name = '_STSU_TMP_' . $table_name;
        $backup_name = '_STSU_BACKUP_' . $table_name;

        $this->query_as_bool("DROP TABLE IF EXISTS '$tmp_name'");
        $this->query_as_bool("DROP TABLE IF EXISTS '$backup_name'");

        $this->begin_transaction();

        $sql = $this->sql_new[$table_name];
        $sql = str_ireplace(
            "CREATE TABLE '$table_name'",
            "CREATE TABLE '$tmp_name'",
            $sql
        );

        if( !$this->query_as_bool($sql) ) {
            $this->error('ERROR: can not create tmp table:<br />' . $sql );
            return FALSE;
        }

        // Get Columns of new table
        $this->set_table_column_info($tmp_name);
        $new_cols = $this->tables_current[$tmp_name];

        // Only use Columns both in new and old tables
        $cols = array();
        foreach( $new_cols as $new_col ) {
            if( isset( $this->tables_current[$table_name][$new_col['name']] ) ) {
                $cols[] = $new_col['name'];
            }
        }
        if( !$cols ) {
            $this->debug('Nothing to insert into table: ' . $table_name);
        } else {

            $old_size = $this->get_table_size($table_name);
            $cols = implode( $cols, ', ');
            $sql = "INSERT INTO '$tmp_name' ( $cols ) SELECT $cols FROM $table_name";
            if( !$this->query_as_bool($sql) ) {
                $this->error('ERROR: can not insert into tmp table: ' . $tmp_name
                . '<br />' . $sql);
                return FALSE;
            }
            $new_size = $this->get_table_size($tmp_name);
            if( $new_size == $old_size ) {
                $this->notice("Inserted OK: $new_size rows into $tmp_name");
            } else {
                $this->error("ERROR: Inserted new $new_size rows, from $old_size old rows");
            }
            if( !$this->query_as_bool("ALTER TABLE $table_name RENAME TO $backup_name") ) {
                $this->error('ERROR: can not rename '.$table_name.' to '.$backup_name );
                return FALSE;
            }
        }

        if( !$this->query_as_bool("ALTER TABLE $tmp_name RENAME TO $table_name") ) {
            $this->error('ERROR: can not rename '.$tmp_name.' to '.$backup_name );
            return FALSE;
        }

        $this->commit();
        $this->notice('Updated: ' . $table_name );

        $this->query_as_bool("DROP TABLE IF EXISTS '$tmp_name'");
        $this->query_as_bool("DROP TABLE IF EXISTS '$backup_name'");
        $this->vacuum();


    } // end function update_table()

    protected function set_table_info() {
        $this->debug('set_table_info()');
        $tables = $this->query_as_array("
            SELECT name, sql
            FROM sqlite_master
            WHERE type = 'table'");
        foreach($tables as $table) {
            if( preg_match('/^_STSU_/', $table['name']) ) {
                continue; // tmp and backup tables
            }
            $this->sql_current[$table['name']]
                = $this->normalize_sql($table['sql']);
            $this->set_table_column_info($table['name']);
        }
    }

    protected function set_table_column_info($table_name) {
        $this->debug("set_table_column_info($table_name)");
        $columns = $this->query_as_array("PRAGMA table_info( $table_name )");
        foreach($columns as $column) {
            $this->tables_current[$table_name][$column['name']] = $column;
        }
    }

} // end class SQLiteTableStructureUpdater
