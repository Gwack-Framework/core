<?php

namespace Tests\Unit\Http\Validation\Rules;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\Rules\NumberRule;
use Gwack\Http\Request;

/**
 * Unit tests for NumberRule validation
 */
class NumberRuleTest extends TestCase
{
    private function createRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getAllData')->willReturn($data);
        return $request;
    }

    public function testGetName(): void
    {
        $rule = new NumberRule('number');
        $this->assertEquals('number', $rule->getName());
    }

    public function testGetParameters(): void
    {
        $rule = new NumberRule('number', ['min' => 0, 'max' => 100]);
        $this->assertEquals(['min' => 0, 'max' => 100], $rule->getParameters());
    }

    public function testValidatesValidNumbers(): void
    {
        $rule = new NumberRule('number');

        $validNumbers = [
            123,
            45.67,
            0,
            -10,
            0.5,
            -0.5,
            '123',
            '45.67',
            '0',
            '-10',
            '0.5'
        ];

        foreach ($validNumbers as $number) {
            $result = $rule->executeOnRequest('number', $this->createRequest(['number' => $number]));
            $this->assertTrue($result, "Number '{$number}' should be valid");
        }
    }

    public function testRejectsInvalidNumbers(): void
    {
        $rule = new NumberRule('number');

        $invalidNumbers = [
            'abc',
            'not a number',
            '',
            '12.34.56',
            'infinity',
            [],
            new \stdClass(),
            true,
            false
        ];

        foreach ($invalidNumbers as $number) {
            $displayValue = is_object($number) ? get_class($number) :
                (is_array($number) ? 'array' : (string) $number);
            $result = $rule->executeOnRequest('number', $this->createRequest(['number' => $number]));
            $this->assertIsString($result, "Value '{$displayValue}' should be invalid");
        }
    }

    public function testAllowsNullValues(): void
    {
        $rule = new NumberRule('number');
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest([])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => null])));
    }

    public function testMinValueValidation(): void
    {
        $rule = new NumberRule('number', ['min' => 10]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 10])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 15])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '20'])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 5])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => '9'])));
    }

    public function testMaxValueValidation(): void
    {
        $rule = new NumberRule('number', ['max' => 100]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 100])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 50])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '75'])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 101])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => '150'])));
    }

    public function testMinMaxCombinedValidation(): void
    {
        $rule = new NumberRule('number', ['min' => 10, 'max' => 100]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 50])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 10])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 100])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 5])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 101])));
    }

    public function testIntegerValidation(): void
    {
        $rule = new NumberRule('number', ['integer' => true]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 123])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '456'])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 0])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => -10])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 12.34])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => '45.67'])));
    }

    public function testFloatValidation(): void
    {
        $rule = new NumberRule('number', ['float' => true]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 12.34])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '45.67'])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 0.5])));

        // Integers should also be valid floats
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 123])));
    }

    public function testPositiveValidation(): void
    {
        $rule = new NumberRule('number', ['positive' => true]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 0.1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '123'])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 0])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => -1])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => '-0.5'])));
    }

    public function testNegativeValidation(): void
    {
        $rule = new NumberRule('number', ['negative' => true]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => -1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => -0.1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '-123'])));

        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 0])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 1])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => '0.5'])));
    }

    public function testZeroHandling(): void
    {
        $positiveRule = new NumberRule('number', ['positive' => true]);
        $negativeRule = new NumberRule('number', ['negative' => true]);

        // Zero should not be positive or negative
        $this->assertIsString($positiveRule->executeOnRequest('number', $this->createRequest(['number' => 0])));
        $this->assertIsString($negativeRule->executeOnRequest('number', $this->createRequest(['number' => 0])));

        // But should be valid for min/max constraints
        $minMaxRule = new NumberRule('number', ['min' => -1, 'max' => 1]);
        $this->assertTrue($minMaxRule->executeOnRequest('number', $this->createRequest(['number' => 0])));
    }

    public function testFloatingPointPrecision(): void
    {
        $rule = new NumberRule('number');

        // Test floating point numbers with various precisions
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 0.1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 0.12345])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '0.123456789'])));
    }

    public function testScientificNotation(): void
    {
        $rule = new NumberRule('number');

        // Test scientific notation
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '1e10'])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => '1.5e-10'])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 1e10])));
    }

    public function testErrorMessages(): void
    {
        $rule = new NumberRule('number', ['min' => 10]);
        $result = $rule->executeOnRequest('age', $this->createRequest(['age' => 5]));

        $this->assertIsString($result);
        $this->assertStringContainsString('age', $result);
        $this->assertStringContainsString('10', $result);
    }

    public function testMaxErrorMessage(): void
    {
        $rule = new NumberRule('number', ['max' => 100]);
        $result = $rule->executeOnRequest('score', $this->createRequest(['score' => 150]));

        $this->assertIsString($result);
        $this->assertStringContainsString('score', $result);
        $this->assertStringContainsString('100', $result);
    }

    public function testIntegerErrorMessage(): void
    {
        $rule = new NumberRule('number', ['integer' => true]);
        $result = $rule->executeOnRequest('count', $this->createRequest(['count' => 12.5]));

        $this->assertIsString($result);
        $this->assertStringContainsString('count', $result);
        $this->assertStringContainsString('integer', strtolower($result));
    }

    public function testPositiveErrorMessage(): void
    {
        $rule = new NumberRule('number', ['positive' => true]);
        $result = $rule->executeOnRequest('amount', $this->createRequest(['amount' => -5]));

        $this->assertIsString($result);
        $this->assertStringContainsString('amount', $result);
        $this->assertStringContainsString('positive', strtolower($result));
    }

    public function testCombinedConstraints(): void
    {
        $rule = new NumberRule('number', [
            'min' => 1,
            'max' => 100,
            'integer' => true,
            'positive' => true
        ]);

        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 50])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 1])));
        $this->assertTrue($rule->executeOnRequest('number', $this->createRequest(['number' => 100])));

        // Should fail various constraints
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 0])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 101])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => 50.5])));
        $this->assertIsString($rule->executeOnRequest('number', $this->createRequest(['number' => -1])));
    }
}
