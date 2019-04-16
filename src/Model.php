<?php 

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class ModelH {
	// STATIC Override
	static function keyField() {
		return ModelConfig::$keyField;
	}
	static function keyFieldAutoIncrement() {
		return true;
	}
	static function tableName() {
		return (new \ReflectionClass(get_called_class()))->getShortName();
	}
	// STATIC
	static $_fields = []; // [className] = [ field, field, field ]

	/* 
	abstract static function newModel() { } // Model 
	abstract static function lastId() { } // value OR null
	
	abstract  static function loadWithWhereQuery($SQLWhere, $params,  $fields = []) { } // Model or null
	abstract  static function loadAllWithWhereQuery($SQLWhere, $params,  $fields = []) { } // [ Model ] or null
	
	abstract static function loadWithStatement(Statement $statement) { } // Model or null , 
	abstract static function loadAllWithStatement(Statement $statement) { }  // [ Model ] or null

	abstract static function loadWithField( $field, $value, $fields = [],  $newModel = false ) { } // Model or null
	abstract static function loadWithId( $value, $fields = [],  $newModel = false ) { } // Model or null
	*/

	// PUBLIC
	public $_model_isLoadedWithDB = false;
	protected $_model_isDeleted = false;

	abstract public function save($fields = []);
	abstract public function delete();
}

# ------------------------------------
# Model
# ------------------------------------

class Model extends ModelH {

	const LOADED_WITH_DB = true;

	// PRIVATE

	private static function statementWithWhereQuery($SQLWhere, $params,  $fields) { // Statement
		$db = Database::getInstance();
		$select = Utils::selectColumns($fields);
		$table = Utils::quoteColumns( static::tableName() );
		$statement = $db->prepare( "SELECT $select FROM $table WHERE $SQLWhere" );
		if (count($params) > 0) {
			if (strpos($statement->_query, '?') !== false) {
				$statement->bindValues($params);
			} else {
				$statement->bindNamedValues($params);
			}
		}
		return $statement;
	}

	private static function getTableFields($fields) {
		if (count($fields) == 0) {
			$class = get_called_class();
			if (!isset(self::$_fields[$class])) {
				$db = Database::getInstance();
				if ($db->isSQLite()) {
					self::$_fields[$class] = Utils::SQLite_tableColumns( $db->getPDO(), static::tableName() );
				}
				if ($db->isMYSQL()) {
					self::$_fields[$class] = Utils::MYSQL_tableColumns( $db->getPDO(), static::tableName() );
				}
			}
			return self::$_fields[$class];
		}
		return $fields;
	}
	
	// Constructor

	public function __construct($isLoadedWithDB = false) {
		$this->_model_isLoadedWithDB = $isLoadedWithDB;
	}

	public function __clone() {
		$this->_model_isDeleted = false;
		$this->_model_isLoadedWithDB = false;
		if (is_string( static::keyField() )) {
			$this->{ static::keyField() } = null;
		}
    }

	// STATIC

	static function newModel() {
		$class = get_called_class();
		$model = new $class(false);
		return $model;
	}

	static function lastId() {
		if (static::keyField() == null) {
			return null;
		}
		$db = Database::getInstance();
		return $db->fetchLastId(static::tableName(), static::keyField());
	}

	// --

	static function loadWithWhereQuery($SQLWhere, $params,  $fields = []) { // Model or null
		$SQLWhere .= ' LIMIT 1';
		$s = self::statementWithWhereQuery($SQLWhere, $params, $fields);
		return $s->fetchObject( get_called_class() , [ self::LOADED_WITH_DB ]);		
	}

	static function loadAllWithWhereQuery($SQLWhere, $params,  $fields = []) { // [ Model ] or null
		$statement = self::statementWithWhereQuery($SQLWhere, $params, $fields);
		return $statement->fetchAllObjects( get_called_class() , [ self::LOADED_WITH_DB ]);
	}

	static function loadWithStatement(Statement $statement) { // Model or null
		return $statement->fetchObject( get_called_class() , [ self::LOADED_WITH_DB ]);
	}

	static function loadAllWithStatement(Statement $statement) { // [ Model ] or null
		return $statement->fetchAllObjects( get_called_class() , [ self::LOADED_WITH_DB ]);
	}

	static function loadWithField( $field, $value, $fields = [],  $newModel = false ) { // Model or null
		$SQLWhere = Utils::quoteColumns($field) . ' = ?';
		$model = static::loadWithWhereQuery($SQLWhere, [ $value ], $fields);
		if ($model == null && $newModel) {
			$model = static::newModel();
			$model->{ $field } = $value;
		}
		return $model;
	}

	static function loadWithId( $value, $fields = [],  $newModel = false ) { // Model or null
		if (static::keyField() == null) {
			return null;
		}
		return static::loadWithField( static::keyField(), $value, $fields, $newModel );
	}

	// PUBLIC

	public function save($fields = []) {
		if ($this->_model_isDeleted) {
			// object is deleted
			return;
		}
		$db = Database::getInstance();
		$fields = static::getTableFields($fields);
		if ($this->_model_isLoadedWithDB) {
			$this->save_update($fields);
		} else {
			$this->save_insert($fields);
			$this->_model_isLoadedWithDB = true;
		}
	}

	public function delete() {
		$keyField = $this->{ static::keyField() };
		if ($keyField == null) {
			return false;
		}
		$table = Utils::quoteColumns( static::tableName() );
		$where = Utils::quoteColumns(static::keyField()) . ' = :keyvalue'; 
		$query = "DELETE FROM $table WHERE $where";
		$db = Database::getInstance();
		$db->prepare($query)
			->bindNamedValue('keyvalue', $keyField)
			->execute();
		$this->_model_isDeleted = true;
	}

	// PROTECTED

	protected function save_update(&$fields) {
		$db = Database::$instance;
		$set = []; 
		$params = [];
		$i = 0;
		$keyField = static::keyField();
		if (!is_string($keyField)) {
			throw Model_Exception::keyFieldIsNull( get_class() );
		}
		foreach ($fields as $column) {
			if ($column == $keyField) {
				continue; // existing keyfield is cannot be overridden
			}
			$param = 'param' . $i;
			$set[] = Utils::quoteColumns($column) . ' = :' . $param;
			$params[$param] = $this->{ $column };
			$i++;
		}
		$where = Utils::quoteColumns($keyField) . ' = :keyvalue';
		$params['keyvalue'] = $this->{ $keyField };
		$table = Utils::quoteColumns( static::tableName() );
		$setStr = implode(', ', $set);
		$query = "UPDATE $table SET $setStr WHERE $where";
		$db->prepare($query)
			->bindNamedValues($params)
			->execute();
	}
	
	protected function save_insert(&$fields) {
		$db = Database::$instance;
		
		$table = Utils::quoteColumns( static::tableName() );
		$keyField = static::keyField();
		$keyFieldAutoIncrement = static::keyFieldAutoIncrement();
		$values = [];
		$params = [];
		$columns = [];
		$i = 0;
		foreach ($fields as $column) {
			if ($column == $keyField && $keyFieldAutoIncrement) {
				continue; // existing auto increment key field is cannot be writing
			}
			$param = 'param' . $i;
			$values[] = ':' . $param;
			$columns[] = $column;
			$params[$param] = $this->{ $column };
			$i++;
		}
		$columns = Utils::selectColumns($columns);
		$valuesStr = implode(', ', $values);
		$query = "INSERT INTO $table ( $columns ) VALUES ( $valuesStr )";
		$db->prepare($query)
			->bindNamedValues($params)
			->execute();
		
		if (is_string($keyField) && $keyFieldAutoIncrement) {
			$this->{ $keyField } = static::lastId();
		}
	}
}