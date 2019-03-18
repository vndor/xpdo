<?php 

use aphp\XPDO\Database;

class User_object {
	public $id;
	public $name;
	public $email;
	public $gender;
	public $age;
	public $binary;

	public $param1_v;
	public $param2_v;

	function __construct($param1, $param2) {
		$this->param1_v = $param1;
		$this->param2_v = $param2;
	}
}

class DatabaseTest extends Base_TestCase {
	
	// STATIC
	static $blobFile1 = '';
	static $blobFile2 = '';

	public static function setUpBeforeClass() {
		self::$blobFile1 = __DIR__ . '/blobData1.png';
		self::$blobFile2 = __DIR__ . '/blobData2.png';

		@unlink(self::$blobFile1);
		@unlink(self::$blobFile2);
	}

	public static function tearDownAfterClass() {
		@unlink(self::$blobFile1);
		@unlink(self::$blobFile2);
	}
	
	// tests
	
	public function test_fetchBlob() 
	{
		$db = Database::getInstance();

		$statement = $db->prepare('SELECT * FROM user WHERE id = 1');
		$blobData = null;
		$this->assertTrue( $statement->fetchBlob('binary', $blobData) );
		$this->assertTrue( file_put_contents(self::$blobFile1, $blobData) !== false );
		$this->assertFileEquals(self::$blobFile1 , __DIR__ . '/db/blobImage1.png');
	}

	public function test_insertRow() {
		$db = Database::getInstance();
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user2', 'email2', 2, 1.5 )");
		$statement->execute();

		$lastId = $statement->fetchLastId('user', 'id');

		$statement = $db->prepare('SELECT `id`, `name`, `email`, `gender`, `age` FROM user WHERE id = ?');
		$statement->bindValues([ $lastId ]);
		$data = $statement->fetchLine();

		$this->assertSame(
			[ 'id' => $lastId, 'name' => 'user2', 'email' => 'email2', 'gender' => '2', 'age' => '1.5' ], $data
		);
	}

	public function test_insertBlob() {
		$db = Database::getInstance();
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user3', 'email3', 3, 2.5 )");
		$statement->execute();

		$lastId = $statement->fetchLastId('user', 'id');

		$statement = $db->prepare("UPDATE user SET `binary` = :blob WHERE id = $lastId");
		$statement->bindNamedBlobAsFilename('blob', __DIR__ . '/db/blobImage2.png');
		$statement->execute();

		$statement = $db->prepare("SELECT * FROM user WHERE id = $lastId");
		$blobData = null;
		$this->assertTrue( $statement->fetchBlob('binary', $blobData) );
		$this->assertTrue( file_put_contents(self::$blobFile2, $blobData) !== false );
		$this->assertFileEquals(self::$blobFile2 , __DIR__ . '/db/blobImage2.png');
	}

	public function test_bindValues() {
		$db = Database::getInstance();
		// bindNamedValue
		$statement = $db->prepare("UPDATE user SET `name` = :value WHERE id = 1");
		$statement->bindNamedValue('value', 'user_bindNamedValue1');
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( $statement->fetchOne(), 'user_bindNamedValue1');
		// bindNamedValues
		$statement = $db->prepare("UPDATE user SET `name` = :value WHERE id = 1");
		$statement->bindNamedValues([ 'value' => 'user_bindNamedValue2' ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( $statement->fetchOne(), 'user_bindNamedValue2');
		// bindValues
		$statement = $db->prepare("UPDATE user SET `name` = ? WHERE id = 1");
		$statement->bindValues([ 'user_bindNamedValue3' ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( $statement->fetchOne(), 'user_bindNamedValue3');
	}

	public function test_bindValues_types() {
		$db = Database::getInstance();
		// bindNamedValues
		$statement = $db->prepare("UPDATE user SET `name` = :value, age = :age WHERE id = 1");
		$statement->bindNamedValues([ 'value' => 'test_bindValues_types', 'age' => 2.3 ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( $statement->fetchOne(), 'test_bindValues_types');
		$statement = $db->prepare("SELECT `age` FROM user WHERE id = 1");
		$this->assertEquals( $statement->fetchOne(), '2.3');
	}

	public function test_objects() {
		$db = Database::getInstance();
		$statement = $db->prepare("SELECT * FROM user WHERE id = 1");
		$obj = $statement->fetchObject(User_object::class, [ 'p1', 'p2' ]);
		$this->assertTrue( is_a($obj, User_object::class) );
		$this->assertEquals( $obj->id , '1');
		$this->assertEquals( $obj->param2_v , 'p2');
		
		$statement = $db->prepare("SELECT * FROM user LIMIT 2");
		$objList = $statement->fetchAllObjects(User_object::class, [ 'p3', 'p4' ]);
		$this->assertTrue( is_a($objList[1], User_object::class) );
		$this->assertEquals( $objList[1]->param1_v , 'p3');
		$this->assertEquals( $objList[1]->param2_v , 'p4');
	}

	// Exceptions

	public function test_DataBase_Exception() {
		$db = new Database;
		try {
			$db->prepare("SELECT `name` FROM user WHERE id = 1");
			$this->assertTrue(false);
		} catch (aphp\XPDO\DataBase_Exception $ex) {
			$this->assertTrue(true);
		}
	}

	public function test_bindNamedValue_Exception() {
		$db = Database::getInstance();
		$st = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		try {
			$st->bindNamedValue('invalid', [ 'hello world' ]);
			$this->assertTrue(false);
		} catch (aphp\XPDO\Statement_Exception $ex) {
			$this->assertTrue(true);
		}
	}

	public function test_bindNamedBlobAsFilename_Exception() {
		$db = Database::getInstance();
		try {
			$statement = $db->prepare("UPDATE user SET `binary` = :blob WHERE id = 2");
			$statement->bindNamedBlobAsFilename('blob', __DIR__ . '/db/invalid.png');
			$this->assertTrue(false);
		} catch (aphp\XPDO\Statement_Exception $ex) {
			$this->assertTrue(true);
		}
	}

	public function test_executeException() {
		$db = Database::getInstance();
		try {
			$statement = $db->prepare("SELEC * FROM user WHERE id = 1");
			$data = $statement->fetchLine();
			$this->assertTrue(false);
		} catch (\PDOException $ex) {
			$this->assertTrue(true);
		}
	}
}
	