<?php

namespace Jules;

class JulesTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testPathWithDots()
    {
		$jules = new Jules();
		$this->assertEquals($jules->readPath('input'), [['input'], 'input']);
		$this->assertEquals($jules->readPath('input.name'), [['input', 'name'], 'input.name']);
		$this->assertEquals($jules->readPath('  input.name'), NULL);
		$this->assertEquals($jules->readPath('$.input'), [['input'], '$.input']);
    }

    public function testPathWithIndex()
    {
		$jules = new Jules();
		$this->assertEquals($jules->readPath('input[1324]'), [['input', 1324], 'input[1324]']);
		$this->assertEquals($jules->readPath('$[1324]'), [[1324], '$[1324]']);
    }

    public function testPathWithDoubleQuotes()
    {
		$jules = new Jules();
		$this->assertEquals($jules->readPath('input["test input"]'), [['input', 'test input'], 'input["test input"]']);
		$this->assertEquals($jules->readPath('input[ "test input"]'), [['input', 'test input'], 'input[ "test input"]']);
		$this->assertEquals($jules->readPath('input["test input" ]'), [['input', 'test input'], 'input["test input" ]']);
		$this->assertEquals($jules->readPath('$["test input"]'), [['test input'], '$["test input"]']);
		$str = 'input["test \"input\" example"]';
		$this->assertEquals($jules->readPath($str), [['input', 'test "input" example'], $str]);
    }

    public function testPathWithSingleQuotes()
    {
		$jules = new Jules();
		$this->assertEquals($jules->readPath("input['test input']"), [['input', 'test input'], "input['test input']"]);
		$this->assertEquals($jules->readPath("input[ 'test input']"), [['input', 'test input'], "input[ 'test input']"]);
		$this->assertEquals($jules->readPath("input['test input' ]"), [['input', 'test input'], "input['test input' ]"]);
		$this->assertEquals($jules->readPath("\$['test input']"), [['test input'], "\$['test input']"]);
		$str = "input['test \"input\" example']";
		$this->assertEquals($jules->readPath($str), [['input', 'test "input" example'], $str]);
		$str = "input['test \\'input\\' example']";
		$this->assertEquals([['input', "test 'input' example"], $str], $jules->readPath($str));
    }

	public function testGet() {
		$jules = new Jules();
		$input = json_decode('{"a": 123, "b": {"$get": "a"}}');
		$expected = json_decode('{"a": 123, "b": 123}');
		$this->assertEquals($expected, $jules->eval($input));

		$input = json_decode('{"a": {"aa": 500}, "b": {"$get": "a.aa"}}');
		$expected = json_decode('{"a": {"aa": 500}, "b": 500}');
		$this->assertEquals($expected, $jules->eval($input));

		$input = json_decode('{"a": 123, "b": {"bb": {"$get": "a"}}}');
		$expected = json_decode('{"a": 123, "b": {"bb": 123}}');
		$this->assertEquals($expected, $jules->eval($input));
	}

	public function testGetInString() {
		$jules = new Jules();
		$input_str = <<<'EOS'
{"a": 16, "b": "a{'$get': 'a'}z"}
EOS;
		$input = json_decode($input_str);
		$expected = json_decode('{"a": 16, "b": "a16z"}');
		$this->assertEquals($expected, $jules->eval($input));

		$input_str = <<<'EOS'
{"a": 16, "a2": 11, "b": "a{'$get': 'a'}z a{'$get': 'a2'}y"}
EOS;
		$input = json_decode($input_str);
		$expected = json_decode('{"a": 16, "a2": 11, "b": "a16z a11y"}');
		$this->assertEquals($expected, $jules->eval($input));

		$input_str = <<<'EOS'
{"a": 1, "a2": 1, "b": "a{'$get': 'a'}{'$get': 'a2'}y"}
EOS;
		$input = json_decode($input_str);
		$expected = json_decode('{"a": 1, "a2": 1, "b": "a11y"}');
		$this->assertEquals($expected, $jules->eval($input));
	}

	public function testEvalStr() {
		$jules = new Jules();
		$input_str = <<<'EOS'
{"a": 16, "b": "a{'$get': 'a'}z"}
EOS;
		$input = json_decode($input_str);
		$this->assertEquals('a16z', $jules->evalStr($input->{'b'}, $input));
	}

	public function testChainedGet() {
		$jules = new Jules();
		$input_str = <<<'EOS'
{"a": 16, "b": {"$get": "a"}, "c": {"$get": "b"}}
EOS;
		$input = json_decode($input_str);
		$this->assertEquals(16, $jules->eval($input)->{'c'});
	}

	public function testCircularGet() {
		$jules = new Jules();
		$input_str = <<<'EOS'
{"a": {"$get": "b"}, "b": {"$get": "a"}}
EOS;
		$input = json_decode($input_str);
		$this->assertEquals(NULL, $jules->eval($input)->{'a'});
	}

	public function skip_testLet() {
		$jules = new Jules();
		$input_str = <<<'EOS'
{"$let": {"foo": 29}, "a": {"$get": "foo"}}
EOS;
		$input = json_decode($input_str);
		$this->assertEquals(29, $jules->eval($input)->{'a'});
	}
}
