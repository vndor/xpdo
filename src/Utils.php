<?php 

namespace aphp\XPDO;

/*
Utils::quoteFields($fieldOrFields)
Utils::selectFields($fields)
Utils::SQLite_tableFields(\PDO $pdo, $table)
Utils::MYSQL_tableFields(\PDO $pdo, $table)
*/

class Utils {
	// CONST
	const QUOTE = '`';

	// STATIC
	static function quoteFields($fieldOrFields) {
		if (is_array($fieldOrFields)) {
			$fields = [];
			foreach ($fieldOrFields as $val) {
				if (substr($val,0,1) != self::QUOTE) {
					$fields[] = self::QUOTE . $val . self::QUOTE;
				} else {
					$fields[] = $val;
				}
			}
			return $fields;
		} elseif (substr($fieldOrFields,0,1) != self::QUOTE) {
			return self::QUOTE . $fieldOrFields . self::QUOTE;
		}
		return $fieldOrFields;
	}

	static function selectFields($fields) { // $fields = array
		if (count($fields) > 0) {
			$fields = self::quoteFields($fields);
			return implode(', ', $fields);
		}
		return '*';
	}

	static function SQLite_tableFields(\PDO $pdo, $table) {
		$s = $pdo->prepare("PRAGMA table_info( '$table' )");
		$s->execute();
		$array = $s->fetchAll(\PDO::FETCH_ASSOC); // cid, name ....
		if (is_array($array)) {
			$fields = [];
			foreach ($array as $values) {
				$fields[] = $values['name'];
			}
			return $fields;
		}
		throw Utils_Exception::tableFields($table);
	}

	static function MYSQL_tableFields(\PDO $pdo, $table) {
		$s = $pdo->prepare("DESC `$table`");
		$s->execute();
		$array = $s->fetchAll(\PDO::FETCH_ASSOC); // Field, Type ..
		if (is_array($array)) {
			$fields = [];
			foreach ($array as $values) {
				$fields[] = $values['Field'];
			}
			return $fields;
		}
		throw Utils_Exception::tableFields($table);
	}

	static function interpolateQuery($query, $params) {
		$keys = array();
		$values = $params;
	
		# build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
			} else {
				$keys[] = '/[?]/';
			}
	
			if (is_array($value))
				$values[$key] = implode(',', $value);
	
			if (is_null($value))
				$values[$key] = 'NULL';
		}
		// Walk the array to see if we can add single-quotes to strings
		array_walk($values, create_function('&$v, $k', 'if (!is_numeric($v) && $v!="NULL") $v = "\'".$v."\'";'));
	
		$query = preg_replace($keys, $values, $query, 1, $count);
	
		return $query;
	}
}