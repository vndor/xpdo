<?php 

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class DataBaseH {
	abstract public function SQLiteInit($fileName);
	abstract public function MySQLInit($user, $password, $dbname, $host = 'localhost');

	abstract public function prepare($queryString); // aphp\XPDO\Statement; 
	abstract public function exec($queryString); // int
	abstract public function fetchLastId($table, $idField); // value OR null ??

	abstract public function isMYSQL();
	abstract public function isSQLite();
	abstract public function getPDO(); // PDO
}

# ------------------------------------
# DataBase
# ------------------------------------

class DataBase extends DataBaseH {
	use \aphp\Foundation\TraitSingleton; // trait
	use \Psr\Log\LoggerAwareTrait; // trait

	// PROTECTED
	protected $_pdo = null; // PDO
	protected $_isMYSQL = false;
	protected $_isSQLite = false;

	// PUBLIC
	public function SQLiteInit($fileName) {
		$this->_pdo = new \PDO('sqlite:'.$fileName);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_isSQLite = true;
	}

	public function MySQLInit($user, $password, $dbname, $host = 'localhost') {
		$this->_pdo = new \PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_isMYSQL = true;
	}

	public function prepare($queryString) {
		$pdo = $this->getPDO();
		$statement = new Statement();
		$statement->_query = $queryString;
		$statement->_database = $this;
		$statement->_pdoStatement = $pdo->prepare($queryString);
		if ($this->logger) {
			$statement->setLogger($this->logger);
		}
		return $statement;
	}

	public function exec($queryString) {
		$pdo = $this->getPDO();
		return $pdo->exec($queryString);
	}

	public function fetchLastId($table, $idField) {
		$id    = Utils::quoteFields($idField);
		$table = Utils::quoteFields($table);
		$statement = $this->prepare("SELECT $id FROM $table ORDER BY $id DESC LIMIT 1");
		return $statement->fetchOne();
	}

	public function isMYSQL() {
		return $this->_isMYSQL;
	}
	
	public function isSQLite() {
		return $this->_isSQLite;
	}

	public function getPDO() {
		if ($this->_pdo) {
			return $this->_pdo;
		}
		throw DataBase_Exception::pdoIsNull();
	}
}