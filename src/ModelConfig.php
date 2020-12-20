<?php

namespace vndor\XPDO;

# ------------------------------------
# Header
# ------------------------------------

class ModelConfig {
	static $keyField = 'id';
	static $relationClass = '\vndor\XPDO\Relation';
	static $relationDefaultPropertyCache = true;
	static $relationMagicMethods = true;
	static $modelClass_relation_namespace = 'auto';
	static $fetchAll_nullValue = [];
}