<?php

namespace Tests\Unit\Http\Validation\Rules;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\Rules\StringRule;
use Gwack\Http\Request;

/**
 * Unit tests for StringRule validation
 */
class StringRuleTest extends TestCase
{
    private function createRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getAllData')->willReturn($data);
        return $request;
    }

    public function testGetName(): void
    {
        $rule = new StringRule('string');
        $this->assertEquals('string', $rule->getName());
    }

    public function testGetParameters(): void
    {
        $rule = new StringRule('string', ['min' => 5]);
        $this->assertEquals(['min' => 5], $rule->getParameters());
    }

    public function testValidatesValidStrings(): void
    {
        $rule = new StringRule('string');

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => ''])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => '123'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'Hello World!'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => '   '])));
    }

    public function testRejectsNonScalarValues(): void
    {
        $rule = new StringRule('string');

        // Non-scalar values should be rejected
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => []])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => new \stdClass()])));
    }

    public function testAcceptsScalarValues(): void
    {
        $rule = new StringRule('string');

        // Scalar values should be accepted and converted to strings
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 123])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 45.67])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => true])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => false])));
    }

    public function testAllowsNullValues(): void
    {
        $rule = new StringRule('string');
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest([])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => null])));
    }

    public function testMinLengthValidation(): void
    {
        $rule = new StringRule('string', ['min' => 3]);

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'abc'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hi'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => ''])));
    }

    public function testMaxLengthValidation(): void
    {
        $rule = new StringRule('string', ['max' => 5]);

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hi'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello world'])));
    }

    public function testMinMaxCombinedValidation(): void
    {
        $rule = new StringRule('string', ['min' => 3, 'max' => 10]);

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello'])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'abc'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hi'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello world extra long'])));
    }

    public function testExactLengthValidation(): void
    {
        $rule = new StringRule('string', ['length' => 5]);

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hi'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello world'])));
    }

    public function testPatternValidation(): void
    {
        $rule = new StringRule('string', ['pattern' => '/^[a-z0-9]+$/']);

        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello123'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'Hello123'])));
        $this->assertIsString($rule->executeOnRequest('field', $this->createRequest(['field' => 'hello!'])));
    }

    public function testScalarValueConversion(): void
    {
        $rule = new StringRule('string', ['min' => 1]);

        // Test that scalar values are converted to strings and then validated
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 123])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => 45.67])));
        $this->assertTrue($rule->executeOnRequest('field', $this->createRequest(['field' => true])));

        // false becomes "" which fails min:1, so this should be a string (error message)
        $result = $rule->executeOnRequest('field', $this->createRequest(['field' => false]));
        $this->assertIsString($result);
    }

    public function testErrorMessages(): void
    {
        $rule = new StringRule('string', ['min' => 5]);
        $result = $rule->executeOnRequest('username', $this->createRequest(['username' => 'hi']));

        $this->assertIsString($result);
        $this->assertStringContainsString('username', $result);
        $this->assertStringContainsString('5', $result);
    }

    public function testMaxLengthErrorMessage(): void
    {
        $rule = new StringRule('string', ['max' => 3]);
        $result = $rule->executeOnRequest('code', $this->createRequest(['code' => 'toolong']));

        $this->assertIsString($result);
        $this->assertStringContainsString('code', $result);
        $this->assertStringContainsString('3', $result);
    }

    public function testExactLengthErrorMessage(): void
    {
        $rule = new StringRule('string', ['length' => 5]);
        $result = $rule->executeOnRequest('pin', $this->createRequest(['pin' => '123']));

        $this->assertIsString($result);
        $this->assertStringContainsString('pin', $result);
        $this->assertStringContainsString('5', $result);
    }

    public function testPatternErrorMessage(): void
    {
        $rule = new StringRule('string', ['pattern' => '/^[a-z]+$/']);
        $result = $rule->executeOnRequest('name', $this->createRequest(['name' => 'Name123']));

        $this->assertIsString($result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('format', $result);
    }
}
