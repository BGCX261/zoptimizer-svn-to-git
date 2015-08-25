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

class DataStoreClientException extends \Exception {
}

class Mutation {
  /**
   * @var bool The switch flips when a cell needs to be deleted
   */
  public $isDelete = FALSE;

  /**
   * @var string The cell/column name
   */
  public $columnName;

  /**
   * @var string The cell/column value
   */
  public $value;

  public function __construct($isDelete, $columnName, $value) {
    $this->isDelete = $isDelete;
    $this->columnName = $columnName;
    $this->value = $value;
  }
}

interface ScannerInterface {
  /**
   * Returns the next row of values
   *
   * @return string[string]|bool An associate list given the columnName=>value, or FALSE if scanner reaches the end
   * @throws DataStoreClientException
   */
  public function getNextResult();

  /**
   * Returns the next rows of values
   *
   * @return int $count number of the rows
   *
   * @return string[string][]|bool A list of associate lists given the columnName=>value, or FALSE if scanner reaches the end
   * @throws DataStoreClientException
   */
  public function getNextResults($count);
}

interface DataStoreClientInterface {
  const MAX_FAMILY_LENGTH = 31;  // save 1 bit for length
  const MAX_KEY_LENGTH = 255; // save 1 bits for length

  /**
   * Returns a list of table names.
   *
   * @return string[] A list of the table names that this store has
   * @throws DataStoreClientException
   */
  public function getTableNames();

  /**
   * Create a new table
   *
   * @param string $tableName The table that will be created
   *
   * @return bool TRUE if success, FALSE if the table can not be created
   * @throws DataStoreClientException
   */
  public function createTable($tableName);

  /**
   * Delete a table
   *
   * @param string $tableName The table that will be deleted
   *
   * @return bool TRUE if success, FALSE if the tableName does not exist
   * @throws DataStoreClientException
   */
  public function deleteTable($tableName);

  /**
   * Create a new family in given table
   *
   * @param string $tableName The table that query runs on
   * @param string $familyName The family that will be created
   *
   * @return bool TRUE if success, FALSE if the family is already
   * @throws DataStoreClientException
   */
  public function createFamily($tableName, $familyName);

  /**
   * Delete a family from given table
   *
   * @param string $tableName The table that query runs on
   * @param string $familyName The family that will be deleted
   *
   * @return bool TRUE if success, FALSE if the tableName does not exist
   * @throws DataStoreClientException
   */
  public function deleteFamily($tableName, $familyName);

  /**
   * Returns a row that matches the query in the given table
   *
   * @param string $tableName The table that this query run on
   * @param string $rowKey The row key query
   * @param string[] $columns The columns that the result includes
   * @param int $timestamp The timestamp of the data has
   *
   * @return string[string]|bool An associate list given the columnName=>value, or FALSE if the rowKey is not found
   * @throws DataStoreClientException
   */
  public function getRow($tableName, $rowKey, $columns, $timestamp);

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
  public function mutateRow($tableName, $rowKey, $mutations, $timestamp);

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
  public function deleteRow($tableName, $rowKey, $timestamp);

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
  public function openScanner($tableName, $startRowKey, $stopRowKey, $columns);

  /**
   * Close the scanner
   *
   * @param Scanner $scanner The scanner will be closed
   *
   * @return bool TRUE if success, FALSE if failed
   * @throws DataStoreClientException
   */
  public function closeScanner($scanner);
}

interface DataStoreClientMgrInterface {
  /**
   * Get a availible data store client from the pool
   *
   * @return DataStoreClientInterface the data store client instance
   * @throws DataStoreClientException
   */
  public static function getClient();
}

