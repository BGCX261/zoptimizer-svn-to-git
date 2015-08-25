<?php
/***
 * Copyright (c) 2012, Zoptimizer, LightyBolt
 * All rights reserved.
 *
 * This work is licensed under
 * the Creative Commons Attribution-NonCommercial-NoDerivs 3.0 Unported License.
 *
 * To view a copy of this license, visit
 *
 *   http://creativecommons.org/licenses/by-nc-nd/3.0/
 *
 * or send a letter to
 *
 *   Creative Commons, 444 Castro Street, Suite 900,
 *   Mountain View, California, 94041, USA.
 */
namespace Zopt\Storage;

require_once 'base/base.php';
require_once 'base/logger.php';
require_once 'storage/datastore.php';
require_once 'cache/localcache.php';

class ShardSqlScanner implements ScannerInterface {
  /**
   * Returns the next row of values
   *
   * @return string[string]|bool An associate list given the columnName=>value, or FALSE if scanner reaches the end
   * @throws DataStoreClientException
   */
  public function getNextResult() {
    throw new DataStoreClientException('Not implemented yet.');
  }

  /**
   * Returns the next rows of values
   *
   * @return int $count number of the rows
   *
   * @return string[string][]|bool A list of associate lists given the columnName=>value, or FALSE if scanner reaches the end
   * @throws DataStoreClientException
   */
  public function getNextResults($count) {
    throw new DataStoreClientException('Not implemented yet.');
  }
}

class ShardSqlDataStoreClient implements DataStoreClientInterface {
  /**
   * @var Logger The shared logger for this class
   */
  private static $_logger;

  /**
   * @var PDO[int] The list mapping from hash to database PDO object, which has "name" attribute
   */
  private $_shardDbs = NULL;

  /**
   * @var CacheInterface The cached data store meta data
   */
  private $_metaCache = NULL;

  /**
   * @var string The data store name
   */
  private $_dsName = NULL;

  /**
   * new datastore client
   *
   * @param string $dsName The data store's name
   * @param PDO[int] $shardDbs An associate array with id=>database PDO objects,
   *                           and each PDO has "meta_table_of_table" table in it.
   * @param CacheInterface $metaCache The cache for datastore metadata
   */
  public function __construct($dsName, $shardDbs, $metaCache) {
    if (is_null(self::$_logger)) self::$_logger = \Zopt\Base\Logger::getLogger(__CLASS__);

    // signatures etc.
    $this->_dsName = $dsName;

    // sort the shared dbs
    ksort($shardDbs);
    $this->_shardDbs = $shardDbs;
    foreach ($this->_shardDbs as $key => $db) {
      $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);  // set to exception mode
    }

    // meta data cache
    $this->_metaCache = $metaCache;
  }

  /**
   * Get the meta data of this data store, in a cascading way.
   *
   * For performance consideration, the data is fetched from cacahe, possibly in the order: local->apc->db
   *
   * @return string[][string] The data store meta data
   *                          e.g.: {tableName => [family, ...], ...}
   * @throws DataStoreClientException
   */
  private function _getMeta() {
    if (($meta = $this->_metaCache->get('table-meta-data')) !== FALSE) return $meta;
    $meta = $this->_getMetaFromDbs();
    $this->_metaCache->set('table-meta-data', $meta);
    return $meta;
  }

  /**
   * Evict the meta data of this data store
   *
   * @return void
   */
  private function _evictMeta() {
    $this->_metaCache->delete('table-meta-data');
  }

  /**
   * Helper function fetch all rows and compare
   *
   * @return string[] The rows as list.
   * @throws DataStoreClientException
   */
  private static function _fetchAllRowsAndCompare($db, $query, $list) {
    $curList = array();
    $rows = $db->query($query);
    while ($row = $rows->fetchColumn()) $curList[] = $row;
    asort($curList);
    if ($list && (implode('|', $list) !== implode('|', $curList))) {
      throw new DataStoreClientException('MISMATCH');
    }
    return $curList;
  }

  /**
   * Get the meta data of this data store from DBs
   *
   * For the performance reason, this operation happens very rarely.
   *
   * @return string[][string] The data store meta data
   *                          e.g.: {tableName => [family, ...], ...}
   * @throws DataStoreClientException
   */
  private function _getMetaFromDbs() {
    try {
      $meta = array();
      // build tables
      $getTables = "SELECT `tableName` FROM `meta_table_of_table`";
      $tables = NULL;
      foreach ($this->_shardDbs as $db) {
        try {
          $tables = self::_fetchAllRowsAndCompare($db, $getTables, $tables);
        } catch (DataStoreClientException $e) {
          throw new DataStoreClientException('Meta data error: table name mismatch in shard dbs.');
        }
      }
      // build families
      foreach ($tables as $table) {
        $getFamilies = "SELECT `familyName` FROM `$table`";
        $families = NULL;
        foreach ($this->_shardDbs as $db) {
          try {
            $families = self::_fetchAllRowsAndCompare($db, $getFamilies, $families);
          } catch (DataStoreClientException $e) {
            throw new DataStoreClientException('Meta data error: family name mismatch in shard dbs.');
          }
        }
        $meta[$table] = $families;
      }
      return $meta;
    } catch (\PDOException $e) {
      throw new DataStoreClientException($e->getMessage());
    }
	}

  /**
   * Returns shard id.
   *
   * @param string $tableName The table that will be created
   * @return int the shard that DB holds
   */
  private function _getShardId($rowKey) {
    $rowId = crc32($rowKey);
    $shards = array_keys($this->_shardDbs);
    // Linear search, can do better if needed.
    $idx = count($idx) - 1;
    while ($idx > 0 && $shards[$idx] > $rowId) $idx--;
    if ($idx === -1) $idx += count($idx);
    return $shards[$idx];
  }

	/**
   * Returns a list of table names.
   *
   * @return string[] A list of the table names that this store has
   * @throws DataStoreClientException
   */
  public function getTableNames() {
    $meta = $this->_getMeta();
    return array_keys($meta);
  }

  /**
   * Create a new table
   *
   * @param string $tableName The table that will be created
   *
   * @return bool TRUE if success, FALSE if the table can not be created (already exists)
   * @throws DataStoreClientException
   */
  public function createTable($tableName) {
    $meta = $this->_getMeta();
    if (isset($meta[$tableName])) return FALSE;
    // create the table on each of the underlying dbs,
    // no need to batch it in cross-db manner as it is very rare.
    $maxFamilyLength = DataStoreClientInterface::MAX_FAMILY_LENGTH;
    try {
      foreach ($this->_shardDbs as $db) {
        $db->exec("CREATE TABLE `$tableName` (`familyName` CHAR($maxFamilyLength) NOT NULL, `config` BLOB, PRIMARY KEY(`familyName`), UNIQUE(`familyName`))");
        $db->exec("INSERT INTO `meta_table_of_table` (`tableName`, `config`) VALUE ('$tableName', '')");
      }
      $this->_evictMeta();
    } catch (\PDOException $e) {
      throw new DataStoreClientException($e->getMessage());
    }
    return TRUE;
  }

  /**
   * Delete a table
   *
   * @param string $tableName The table that will be deleted
   *
   * @return bool TRUE if success, FALSE if the tableName does not exist
   * @throws DataStoreClientException
   */
  public function deleteTable($tableName) {
    $meta = $this->_getMeta();
    if (!isset($meta[$tableName])) return FALSE;
    // three steps to delete a table:
    // 1) drop all family-tables
    // 2) drop main table
    // 3) remove main table entry from meta table
    // this can not be roll-back as "drop table" op is not roll-back-able.
    try {
      foreach ($this->_shardDbs as $db) {
        foreach ($meta[$tableName] as $family) {
          $fullName = $tableName . '_' . $family;
          $db->exec("DROP TABLE `$fullName`");
        }
        $db->exec("DROP TABLE `$tableName`");
        $db->exec("DELETE FROM `meta_table_of_table` WHERE `tableName` = '$tableName'");
      }
      $this->_evictMeta();
    } catch (\PDOException $e) {
      throw new DataStoreClientException($e->getMessage());
    }
    return TRUE;
  }

  /**
   * Create a new family in given table
   *
   * @param string $tableName The table that query runs on
   * @param string $familyName The family that will be created
   *
   * @return bool TRUE if success, FALSE if the family already exists
   * @throws DataStoreClientException
   */
  public function createFamily($tableName, $familyName) {
    $meta = $this->_getMeta();
    if (!isset($meta[$tableName])) throw new DataStoreClientException("createFamily: $tableName is not found.");
    if (isset($meta[$tableName][$familyName])) return FALSE;
    // create the table on each of the underlying dbs,
    // no need to batch it in cross-db manner as it is very rare.
    $maxKeyLength = DataStoreClientInterface::MAX_KEY_LENGTH;
    $fullName = $tableName . '_' . $familyName;
    try {
      foreach ($this->_shardDbs as $db) {
        $db->exec("CREATE TABLE `$fullName` (`rowKey` VARCHAR($maxKeyLength) NOT NULL, `data` MEDIUMBLOB, PRIMARY KEY(`rowKey`))");
        $db->exec("INSERT INTO `$tableName` (`family`, `config`) VALUE ('$familyName', '')");
      }
      $this->_evictMeta();
    } catch (\PDOException $e) {
      throw new DataStoreClientException($e->getMessage());
    }
    return TRUE;
  }

  /**
   * Delete a family from given table
   *
   * @param string $tableName The table that query runs on
   * @param string $familyName The family that will be deleted
   *
   * @return bool TRUE if success, FALSE if the familyName does not exist
   * @throws DataStoreClientException
   */
  public function deleteFamily($tableName, $familyName) {
    $meta = $this->_getMeta();
    if (!isset($meta[$tableName])) throw new DataStoreClientException("deleteFamily: $tableName is not found.");
    if (!isset($meta[$tableName][$familyName])) return FALSE;
    // two steps to delete a family:
    // 1) drop family-tables
    // 2) remove family table entry from main table
    // this can not be roll-back as "drop table" op is not roll-back-able.
    $fullName = $tableName . '_' . $familyName;
    try {
      foreach ($this->_shardDbs as $db) {
        $db->exec("DROP TABLE `$fullName`");
        $db->exec("DELETE FROM `$tableName` WHERE `familyName` = '$familyName'");
      }
      $this->_evictMeta();
    } catch (\PDOException $e) {
      throw new DataStoreClientException($e->getMessage());
    }
    return TRUE;
  }

  /**
   * Returns a row that matches the query in the given table
   *
   * @param string $tableName The table that this query run on
   * @param string $rowKey The row key query
   * @param string[] $columns The columns that the result includes, e.g:
   *                           "family"     - returns all qualifiers with array(qualifier => value)
   *                           "family:"    - returns implicit "family:*" sub as string
   *                           "family:sub" - returns "family:qualifiers" as string
   * @param int $timestamp The timestamp of the data has
   *
   * @return string[string]|bool An associate list given the columnName=>value, or FALSE if the rowKey is not found
   * @throws DataStoreClientException
   */
  public function getRow($tableName, $rowKey, $columns = NULL, $timestamp = NULL) {
    $meta = $this->_getMeta();
    if (!isset($meta[$tableName])) throw new DataStoreClientException("getRow: $tableName is not found.");
    if (!is_null($columns) && !is_array($columns)) throw new DataStoreClientException("getRow: unrecognized columns format: $columns.");
    if (is_null($columns) || empty($columns)) {
      $families = $meta[$tableName];
    } else {
      $families = array();
      foreach ($columns as $column) {
        $components = explode(':', $column);
        if (count($components) > 2) throw new DataStoreClientException("getRow: unrecognized column format: $column.");
        if (!in_array($components[0], $meta[$tableName])) throw new DataStoreClientException("getRow: unrecognized family: $components[0] for table $tableName.");
        if (!in_array($components[0], $families)) $families[] = $components[0];
      }
    }
    $db = $this->_shardDbs[$this->_getShardId($rowKey)];

    // batch reading
    $familyFetchers = array();
    $db->beginTransaction();
    foreach ($families as $familyName) {
      $fullName = $tableName . '_' . $familyName;
      $familyFetchers[$familyName] = $db->query("SELECT `data` FROM `$fullName` WHERE `rowKey` = $rowKey");
    }
    $db->commit();
    $familyResults = array();
    foreach ($familyFetchers as $name => $fetcher) {
      $familyResult = $fetcher->fetchColumn();
      if ($familyResult === FALSE) continue;  // specific family is not set for this rowKey
      $qualifierResults = json_decode($familyResult, true);  // TODO: decouple json dependency.
      foreach ($qualifierResults as $qualifier => $result) {  // verification
        if (!is_string($result)) throw new DataStoreClientException("getRow: wrong data format for rowKey $rowKey in family $name: [$familyResult].");
      }
      $familyResults[$name] = $qualifierResults;
    }

    // return back the queried columns
    if (empty($familyResults)) return FALSE;  // no family was found - rowKey does not exist
    $res = array();
    if (is_null($columns) || empty($columns)) {  // return all
      foreach ($familyResults as $name => $qualifierResults) {
        foreach ($qualifierResults as $qualifier => $result) {
          $fullname = ($qualifier === '*') ? $name : "$name:$qualifier";
          $res[$fullname] = $result;
        }
      }
      return $res;
    }
    foreach ($columns as $column) {  // return selected
      $components = explode(':', $column);
      if (count($components) === 1) {
        // "family"
        if (isset($familyResults[$components[0]])) $res[$column] = $familyResults[$components[0]];
      } elseif ($components[1] === '') {
        // "family:" = "family:*"
        if (isset($familyResults[$components[0]]['*'])) $res[$column] = $familyResults[$components[0]]['*'];
      } else {
        // "family:qualifier"
        if (isset($familyResults[$components[0]][$components[1]])) $res[$column] = $familyResults[$components[0]][$components[1]];
      }
    }
    return $res;
  }

  /**
   * Apply the mutations to the row in the given table
   *
   * For the consistency model, all mutations on one row is considered to be an atomic operation.
   *
   * @param string $tableName The table that this query run on
   * @param string $rowKey The row key query
   * @param Mutation[] $mutations The mutations that will be applied
   * @param int $timestamp The timestamp of the data has
   *
   * @return string[string]|bool An associate list given the columnName=>value to present the updated row, or FALSE if the rowKey is not found
   * @throws DataStoreClientException
   */
  public function mutateRow($tableName, $rowKey, $mutations, $timestamp) {
    throw new DataStoreClientException('Not implemented yet.');
  }

  /**
   * Remove a row from the given table
   *
   * @param string $tableName The table that this query run on
   * @param string $rowKey The row key query
   * @param int $timestamp The timestamp of the data has
   *
   * @return bool TRUE if success, FALSE if the rowKey is not found
   * @throws DataStoreClientException
   */
  public function deleteRow($tableName, $rowKey, $timestamp = NULL) {
    $meta = $this->_getMeta();
    if (!isset($meta[$tableName])) throw new DataStoreClientException("deleteRow: $tableName is not found.");
    $db = $this->_shardDbs[$this->_getShardId($rowKey)];
    // batch deleting
    $familyDeleters = array();
    $db->beginTransaction();
    foreach ($meta[$tableName] as $familyName) {
      $fullName = $tableName . '_' . $familyName;
      $familyDeleters[] = $db->query("DELETE FROM `$fullName` WHERE `rowKey` = $rowKey");
    }
    $db->commit();
    // gather results
    $rowCount = 0;
    foreach ($familyDeleters as $familyDeleter) {
      $rowCount += $familyDeleter->rowCount();
    }
    return ($rowCount > 0);
  }

  /**
   * Open a scanner to iterate the rows
   *
   * @param string $tableName The table that this query run on
   * @param string $startRowKey The row key that this scan start with (include)
   * @param string $stopRowKey The row key that this scan stop with (exclude)
   * @param string[] $columns The columns that the result will include
   *
   * @return Scanner|bool The scanner to read data later, FALSE if scanner could not be opened
   * @throws DataStoreClientException
   */
  public function openScanner($tableName, $startRowKey, $stopRowKey, $columns) {
    throw new DataStoreClientException('Not implemented yet.');
  }

  /**
   * Close the scanner
   *
   * @param Scanner $scanner The scanner will be closed
   *
   * @return bool TRUE if success, FALSE if failed
   * @throws DataStoreClientException
   */
  public function closeScanner($scanner) {
    throw new DataStoreClientException('Not implemented yet.');
  }
}

class ShardSqlDataStoreClientMgr implements DataStoreClientMgrInterface {
  /**
   * Get a availible data store client from the pool
   *
   * @return DataStoreClientInterface the data store client instance
   * @throws DataStoreClientException
   */
  public static function getClient() {
    throw new DataStoreClientException('Not implemented yet.');
  }
}

