<?php 

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class StatementH {
	const TYPE_JSON = 'json';
	
	public $_pdoStatement; // PDOStatement
	public $_query;
	public $_database; // aphp\XPDO\Database
	public $_params = [];
	protected $_jsonColumns = [];

	abstract public function bindNamedValue($name, $value); // Statement
	abstract public function bindNamedValues($params); // Statement, $params = array()
	abstract public function bindValues($params);      // Statement, $params = array()
	
	abstract public function setJSONColumns($colums); // $colums = array()

	abstract public function execute(); // Statement

	abstract public function fetchAll(); // array[row][column] OR null
	abstract public function fetchLine(); // array[column] OR null
	abstract public function fetchOne(); // value OR null
	abstract public function fetchLastId($table, $idColumn); // value OR null

	// Blob interface
	abstract public function bindNamedBlob($name, &$blob); // Statement
	abstract public function bindNamedBlobAsFilename($name, $filename); // Statement
	abstract public function fetchBlob($columnName, &$blob); // true OR false

	// Object Interface
	abstract public function fetchObject($className, $constructorParams = null); // object OR null
	abstract public function fetchAllObjects($className, $constructorParams = null); // [object] OR null
}

# ------------------------------------
# Statement
# ------------------------------------

class Statement extends StatementH {
	use \Psr\Log\LoggerAwareTrait; // trait

	public function bindNamedValue($name, $value) {
		$type = $this->getPDOParamType($value);
		// json conversion
		if ($type === self::TYPE_JSON) {
			$value = Utils::jsonEncode($value);
			$type = $this->getPDOParamType($value);
		}
		$this->_pdoStatement->bindValue($name, $value, $type);
		if ($this->logger) {
			$this->_params[ $name ] = $value;
		}
		return $this;
	}

	public function bindNamedValues($params) { // $params = array()
		foreach ($params as $name=>$value) {
			$this->bindNamedValue($name, $value);
		}
		return $this;
	} 

	public function bindValues($params) {  // $params = array()
		if (Utils::$_jsonBindDetection) {
			foreach ($params as &$value) {
				$type = $this->getPDOParamType($value);
				if ($type === self::TYPE_JSON) {
					$value = Utils::jsonEncode($value);
				}
			}
		}
		$this->executeValues = $params;
		if ($this->logger) {
			$this->_params = $params;
		}
		return $this;
	}

	// --
	
	public function setJSONColumns($colums) {
		if (is_array($colums)) {
			$this->_jsonColumns = $colums;
		}
	}

	public function execute() {
		if ($this->logger) {
			$query = Utils::interpolateQuery($this->_query, $this->_params);
			$this->logger->info($query, Utils::$_logContext);
		}
		if (is_array($this->executeValues)) {
			$this->_pdoStatement->execute( $this->executeValues );
			$this->executeValues = null;
		} else {
			$this->_pdoStatement->execute();
		}
		return $this;
	}

	// --

	public function fetchAll() {
		$this->execute();
		$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
		if (is_array($array) && count($array) > 0) {
			$this->jsonColumnsDecode($array, 'fetchAll');
			return $array;
		}
		return null;
	}

	public function fetchLine() {
		$this->execute();
		$array = $this->_pdoStatement->fetch(\PDO::FETCH_ASSOC);
		if (is_array($array)) {
			$this->jsonColumnsDecode($array, 'fetchLine');
			return $array;
		}
		return null;
	}

	public function fetchOne() {
		$this->execute();
		$array = $this->_pdoStatement->fetch(\PDO::FETCH_NUM);
		if (is_array($array) && count($array)>0) {
			$this->jsonColumnsDecode($array[0], 'fetchOne');
			return $array[0];
		}
		return null;
	}

	public function fetchLastId($table, $idColumn) {
		return $this->_database->fetchLastId($table, $idColumn);
	}

	// Blob interface

	public function bindNamedBlob($name, &$blob) {
		$this->_pdoStatement->bindParam($name, $blob, \PDO::PARAM_LOB);
	}

	public function bindNamedBlobAsFilename($name, $filename) {
		$fp = @fopen($filename, 'rb'); // read binary
		if ($fp === false) {
			throw Statement_Exception::bindNamedBlobAsFilenameException($name, $this->_query, $filename);
		}
		$this->bindNamedBlob($name, $fp);
	}

	public function fetchBlob($columnName, &$blob) { // true OR false
		$this->execute();
		$this->_pdoStatement->bindColumn($columnName, $blob, \PDO::PARAM_LOB);
		return $this->_pdoStatement->fetch(\PDO::FETCH_BOUND);
	}

	// Object Interface

	public function fetchObject($className, $constructorParams = null) {
		$this->execute();
		if ($constructorParams) {
			$object = $this->_pdoStatement->fetchObject($className, $constructorParams);
		} else {
			$object = $this->_pdoStatement->fetchObject($className);
		}
		if (is_a($object, $className)) {
			$this->jsonColumnsDecode($object, 'fetchObject');
			return $object;
		}
		return null;
	}

	public function fetchAllObjects($className, $constructorParams = null) {
		$this->execute();
		if ($constructorParams) {
			$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_CLASS, $className, $constructorParams);
		} else {
			$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_CLASS, $className);
		}
		if (
			is_array($array) && 
			count($array) > 0 && 
			is_a($array[0], $className)
		) {
			$this->jsonColumnsDecode($array, 'fetchAllObjects');
			return $array;
		}
		return null;
	}

	// PROTECTED
	
	protected $executeValues = null;
	protected function getPDOParamType( &$value ) {
		if (is_int($value))    return \PDO::PARAM_INT;
		if (is_string($value)) return \PDO::PARAM_STR;
		if (is_float($value)) return \PDO::PARAM_STR; // FLOAT is not exists
		if (Utils::$_jsonBindDetection && is_array($value)) return self::TYPE_JSON;
		if ($value == null)   return \PDO::PARAM_NULL;
		throw Statement_Exception::bindInvalidType($value, $this->_query);
	}
	
	protected function jsonColumnsDecode( &$fetchResult , $callMethod ) {
		if (count($this->_jsonColumns) > 0) {
			if ($callMethod == 'fetchAll' || $callMethod == 'fetchAllObjects') {
				foreach ($fetchResult as &$value) {
					$this->jsonColumnsDecode($value, 'fetchObject');
				}
			} elseif ($callMethod == 'fetchObject' || $callMethod == 'fetchLine') {
				foreach ($this->_jsonColumns as $column) {
					if (is_object($fetchResult)) {
						// object
						$fetchResult->{ $column } = Utils::jsonDecode($fetchResult->{ $column });
					} else {
						// array
						$fetchResult[ $column ] = Utils::jsonDecode($fetchResult[ $column ]);
					}
				}
			} elseif ($callMethod == 'fetchOne') {
				$fetchResult = Utils::jsonDecode($fetchResult);
			}
		}
	}
}