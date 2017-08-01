<?php
// SQLite Table Structure Updater

namespace Attogram;

define('__STSU__','0.0.7');

//////////////////////////////////////////////////////////
class stsu_utils {

    public $debug;
    protected $timer;
    public $timer_results;

    public function debug( $msg ) {
        if( !$this->debug ) { return; }
        print '<p class="debug">' . print_r($msg,1) . '</p>';
    }

    public function notice( $msg ) {
        print '<p class="notice">' . print_r($msg,1) . '</p>';
    }

    public function error( $msg ) {
        print '<p class="error">' . print_r($msg,1) . '</p>';
    }

    function time_now() {
        return gmdate('Y-m-d H:i:s');
    }

    function start_timer( $name ) {
        $this->timer[$name] = microtime(1);
    }

    function end_timer( $name ) {
        if( !isset($this->timer[$name]) ) {
            $this->timer_results[$name] = 0;
            return;
        }
        $result = microtime(1) - $this->timer[$name];
        if( isset($this->timer_results[$name]) ) {
            $this->timer_results[$name] += $result;
            return;
        }
        $this->timer_results[$name] = $result;
    }

}

//////////////////////////////////////////////////////////
class stsu_database EXTENDS stsu_utils  {

    protected $db;
    protected $database_file;
    protected $last_insert_id;
    protected $last_error;

    public function database_loaded() {
        if( !$this->db ) {
            $this->open_database();
        }
        if( !$this->db ) {
            return FALSE;
        }
        return TRUE;
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
        if( !$this->database_loaded() ) {
            return array();
        }
        $this->start_timer('query_as_array');
        $statement = $this->db->prepare($sql);
        if( !$statement ) {
            $this->error('query_as_array(): ERROR PREPARE');
            $this->end_timer('query_as_array');
            return array();
        }
        while( $xbind = each($bind) ) {
            $this->debug('query_as_array(): bindParam '. $xbind[0] .' = ' . $xbind[1]);
            $statement->bindParam( $xbind[0], $xbind[1]);
        }
        if( !$statement->execute() ) {
            $this->error('ERROR EXECUTE: '.print_r($this->db->errorInfo(),1));
            $this->end_timer('query_as_array');
            return array();
        }
        $response = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if( !$response && $this->db->errorCode() != '00000') {
            $this->error('query_as_array(): ERROR FETCH: '.print_r($this->db->errorInfo(),1));
            $response = array();
        }
        $this->end_timer('query_as_array');
        return $response;
    }

    protected function query_as_bool( $sql, $bind=array() ) {
        $this->debug( $this->normalize_sql($sql) );
        if( !$this->database_loaded() ) {
            return FALSE;
        }
        $this->start_timer('query_as_bool');
        $this->last_error = FALSE;
        $statement = $this->db->prepare($sql);
        if( !$statement ) {
            $this->last_error = $this->db->errorInfo();
            $this->error('ERROR: ' . print_r($this->last_error,1) );
            $this->end_timer('query_as_bool');
            return FALSE;
        }
        while( $xbind = each($bind) ) {
            $statement->bindParam( $xbind[0], $xbind[1] );
        }
        if( !$statement->execute() ) {
            $this->last_error = $this->db->errorInfo();
            if( $this->last_error[0] == '00000' ) { // no error
                $this->end_timer('query_as_bool');
                return TRUE;
            }
            $this->error('query_as_bool: prepare failed: ' . print_r($this->last_error,1) );
            $this->end_timer('query_as_bool');
            return FALSE;
        }
        $this->last_error = $this->db->errorInfo();
        $this->end_timer('query_as_bool');
        return TRUE;
    } // end function query_as_bool()

    protected function vacuum() {
        $this->start_timer('vacuum');
        if( $this->query_as_bool('VACUUM') ) {
            $this->end_timer('vacuum');
            return TRUE;
        }
        $this->end_timer('vacuum');
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

} // end class database_utils

//////////////////////////////////////////////////////////
class stsu_database_utils EXTENDS stsu_database  {

    protected function normalize_sql( $sql ) {
        $sql = preg_replace('/\s+/', ' ', $sql); // remove all excessive spaces and control chars
        $sql = str_replace('"', "'", $sql); // use only single quote '
        $sql = str_ireplace('CREATE TABLE IF NOT EXISTS', 'CREATE TABLE', $sql); // standard create syntax
        return trim($sql);
    }

    protected function get_table_size( $table_name ) {
        $size = $this->query_as_array('SELECT count(rowid) AS count FROM ' . $table_name);
        if( isset($size[0]['count']) ) {
            return $size[0]['count'];
        }
        $this->error('Can not get table size: ' . $table_name);
        return 0;
    }

}

//////////////////////////////////////////////////////////
class SQLiteTableStructureUpdater extends stsu_database_utils {

    protected $tables_current;
    protected $sql_current;
    protected $sql_new;

    public function __construct() {
        $this->start_timer('page');
        $this->debug('__construct()');
    }

    public function set_database_file( $file ) {
        $this->debug("set_database_file($file)");
        $this->database_file = $file;
        $this->tables_current = array();
        $this->sql_current = array();
        $this->set_table_info();
        return $this->database_loaded();
    }

    public function set_new_structures( $tables = array() ) {
        $this->debug('set_new_structures()');
        if( !$tables || !is_array($tables) ) {
            $this->error('$tables array is invalid');
            return FALSE;
        }
        $errors = 0;
        $count = 0;
        while( list($table_name,$table_sql) = each($tables) ) {
            $count++;
            if( !$table_name || !is_string($table_name) ) {
                $this->error("#$count - Invalid table name");
                $errors++;
                continue;
            }
            if( !$table_sql || !is_string($table_sql) ) {
                $this->error("#$count - Invalid table sql");
                $errors++;
                continue;
            }
            $this->set_new_structure( $table_name, $table_sql );
        }
        return $errors ? FALSE : TRUE;
    }

    public function set_new_structure( $table_name, $sql ) {
        $this->debug("set_new_structure($table_name, $sql)");
        $sql = $this->normalize_sql($sql);
        $this->sql_new[$table_name] = $sql;
    }

    public function update() {
        $this->debug("update()");
        $this->start_timer('update');
        $to_update = array();
        foreach( array_keys($this->sql_new) as $name ) {
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
                'OK: ' . sizeof($this->sql_new) . ' tables up-to-date'
            );
            $this->end_timer('update');
            return TRUE;
        }
        $this->notice(
            sizeof($to_update) . ' tables to update: '
            . implode($to_update,', ')
        );
        foreach( $to_update as $table_name ) {
            $this->update_table($table_name);
        }
        $this->end_timer('update');
        return TRUE;
    } // end function update()

    protected function update_table( $table_name ) {
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
            $new_size = 0;
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
                $this->debug("Inserted OK: $new_size rows into $tmp_name");
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
        $this->notice('OK: Table Structure Updated: ' . $table_name
            . ': +' . number_format($new_size) . ' rows');

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

    protected function set_table_column_info( $table_name ) {
        $this->debug("set_table_column_info($table_name)");
        $columns = $this->query_as_array("PRAGMA table_info( $table_name )");
        foreach($columns as $column) {
            $this->tables_current[$table_name][$column['name']] = $column;
        }
    }

} // end class SQLiteTableStructureUpdater
