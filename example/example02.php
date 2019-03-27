<?php 

require __DIR__ . '/../vendor/autoload.php';

use aphp\XPDO\Database;


class User_object {
	public $id;
	public $name;
	public $email;
	public $param1_v;
	public $param2_v;

	function __construct($param1, $param2) {
		$this->param1_v = $param1;
		$this->param2_v = $param2;
	}
}

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/sampleBase.sqlite');

$statement = $db->prepare("SELECT `id`, `name`, `email` FROM user WHERE id = 1");
$obj = $statement->fetchObject(User_object::class, [ 'p1', 'p2' ]);

print_r($obj);