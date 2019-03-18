<?php 

use aphp\XPDO\Database;
use aphp\XPDO\Model;

class user extends Model {
	public $id;
	public $name;
	public $email;
	public $gender;
	public $age;
	public $binary;
}

class user_object_uniq extends Model {
	static function tableName() {
		return 'user_uniq';
	}
	static function keyField() {
		return 'name';
	}
	static function keyFieldAutoIncrement() {
		return false;
	}
}

class user_object_nokey extends Model {
	static function tableName() {
		return 'user_uniq'; // table name must exists
	}
	static function keyField() {
		return null; // not keyfield used
	}
	// dynamic properties, not implemented
}

// ---

class ModelTest extends Base_TestCase {
	// STATIC
	public static function setUpBeforeClass() {

	}

	public static function tearDownAfterClass() {

	}

	// tests
	public function test_newModel() 
	{
		$obj2 = user::newModel();

		$obj2->name = 'name 01';
		$obj2->email = 'email 01';

		$obj2->save();
		$obj2_read = user::loadWithId( $obj2->id );
		$this->assertTrue(is_a($obj2_read, user::class));
		$this->assertEquals($obj2_read->email, 'email 01');
	}

	public function test_newModel2() 
	{
		$obj1 = new user_object_uniq();
		$obj1->name = 'obj_name1';
		$obj1->lastname = 'obj_lastname1';

		$obj1->save();

		$obj1_read = user_object_uniq::loadWithId( $obj1->name );
		$this->assertTrue(is_a($obj1_read, user_object_uniq::class));
		$this->assertEquals($obj1_read->lastname, 'obj_lastname1');
	}

	public function test_update() 
	{
		$obj = user::loadWithId('1');
		$this->assertTrue(is_a($obj, user::class));
		
		$oldName = $obj->name;
		$obj->name = 'new name';
		$obj->email = 'new email';

		$obj->save(['email']);

		$obj1_read = user::loadWithId( '1' );
		$this->assertTrue(is_a($obj1_read, user::class));
		$this->assertEquals($obj1_read->email, 'new email');
		$this->assertEquals($obj1_read->name, $oldName);
	}

	public function test_nokeyObj() 
	{
		$obj = user_object_nokey::newModel();
		$this->assertTrue(is_a($obj, user_object_nokey::class));

		$obj->name = 'user_object_nokey name';
		$obj->lastname = 'user_object_nokey lastname';

		$obj->save();

		$obj1_read = user_object_nokey::loadWithField('name', 'user_object_nokey name');
		$this->assertTrue(is_a($obj1_read, user_object_nokey::class));
		$this->assertEquals($obj1_read->lastname, 'user_object_nokey lastname');

		$obj->lastname = 'hello world';
		try {
			$obj->save();
			$this->assertTrue( false );
		} catch (aphp\XPDO\Model_Exception $ex) {
			$this->assertTrue( true );
		}
	}
	
	public function test_nokeyObj2() 
	{
		$obj = user_object_uniq::newModel();
		$this->assertTrue(is_a($obj, user_object_uniq::class));

		$id = 'user2_object_nokey name';

		$obj->name = $id;
		$obj->lastname = 'user2_object_nokey lastname';

		$obj->save();

		$obj1_read = user_object_uniq::loadWithField('name', $id);
		$this->assertTrue(is_a($obj1_read, user_object_uniq::class));
		$this->assertEquals($obj1_read->lastname, 'user2_object_nokey lastname');

		$obj->lastname = 'hello world';
		$obj->save();

		$obj2_read = user_object_uniq::loadWithField('name', $id);
		$this->assertTrue(is_a($obj2_read, user_object_uniq::class));
		$this->assertEquals($obj2_read->lastname, 'hello world');
	}

	public function test_fetch() 
	{
		$db = Database::getInstance();
		$statement = $db->prepare('SELECT * FROM user_uniq');
		$objects = user_object_nokey::loadAllWithStatement($statement);
		$this->assertTrue(is_a($objects[1], user_object_nokey::class));

		$statement->_pdoStatement->closeCursor();

		$statement2 = $db->prepare('SELECT * FROM user_uniq');
		$object = user_object_nokey::loadWithStatement($statement);
	}

	public function test_delete() 
	{
		$obj = user_object_uniq::loadWithId('obj_name1');
		$this->assertTrue(is_a($obj, user_object_uniq::class));

		$obj->delete();
		$obj1_read = user_object_uniq::loadWithId('obj_name1');
		$this->assertTrue( $obj1_read === null );
	}
}