<?php

namespace Tests\Unit\Http\Validation;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\RequestValidator;
use Gwack\Http\Validation\RuleExecutor;
use Gwack\Http\Request;
use Gwack\Core\Exceptions\ValidationException;

/**
 * Unit tests for RequestValidator
 */
class RequestValidatorTest extends TestCase
{
    private RequestValidator $validator;
    private RuleExecutor $ruleExecutor;

    protected function setUp(): void
    {
        $this->ruleExecutor = new RuleExecutor();
        $this->validator = new RequestValidator($this->ruleExecutor);
    }

    private function createRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getAllData')->willReturn($data);
        return $request;
    }

    public function testValidateField(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        $result = $this->validator->validateField('name', 'string', $request);
        $this->assertIsArray($result);
        $this->assertEmpty($result); // No errors

        // Test with invalid data
        $invalidRequest = $this->createRequest(['name' => []]);
        $result = $this->validator->validateField('name', 'string', $invalidRequest);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result); // Should have errors
    }

    public function testValidateFieldWithStringRules(): void
    {
        $request = $this->createRequest(['name' => 'Jo']);

        $result = $this->validator->validateField('name', 'string:min=5', $request);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result); // Should fail min length

        $validRequest = $this->createRequest(['name' => 'John Doe']);
        $result = $this->validator->validateField('name', 'string:min=5', $validRequest);
        $this->assertIsArray($result);
        $this->assertEmpty($result); // Should pass
    }

    public function testValidateFieldWithArrayRules(): void
    {
        $request = $this->createRequest(['name' => 'Jo']);

        $result = $this->validator->validateField('name', ['string:min=5'], $request);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result); // Should have errors due to min length
    }

    public function testValidate(): void
    {
        $request = $this->createRequest([
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25
        ]);

        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'number'
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateWithFailures(): void
    {
        $request = $this->createRequest([
            'name' => 'Jo',
            'email' => 'invalid-email',
            'age' => -5
        ]);

        $rules = [
            'name' => 'string:min=5',
            'email' => 'email',
            'age' => 'number:min=0'
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('age', $result['errors']);
    }

    public function testValidateWithMissingFields(): void
    {
        $request = $this->createRequest([
            'name' => 'John'
            // Missing email and age
        ]);

        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'number'
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        // Depending on implementation, missing fields might be valid (null allowed)
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testValidateStopOnFirstFailure(): void
    {
        $request = $this->createRequest([
            'name' => 'Jo', // Too short
            'email' => 'invalid-email', // Invalid
            'age' => -5 // Invalid
        ]);

        $rules = [
            'name' => 'string:min=5',
            'email' => 'email',
            'age' => 'number:min=0'
        ];

        $result = $this->validator->validate($request, $rules, true);

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        // Should only have one error due to stop on first failure
        $this->assertCount(1, $result['errors']);
    }

    public function testPasses(): void
    {
        $request = $this->createRequest([
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $rules = [
            'name' => 'string',
            'email' => 'email'
        ];

        $this->assertTrue($this->validator->passes($request, $rules));

        // Test with failing validation
        $invalidRequest = $this->createRequest([
            'name' => 'Jo',
            'email' => 'invalid-email'
        ]);

        $strictRules = [
            'name' => 'string:min=5',
            'email' => 'email'
        ];

        $this->assertFalse($this->validator->passes($invalidRequest, $strictRules));
    }

    public function testFails(): void
    {
        $request = $this->createRequest([
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $rules = [
            'name' => 'string',
            'email' => 'email'
        ];

        $this->assertFalse($this->validator->fails($request, $rules));

        // Test with failing validation
        $invalidRequest = $this->createRequest([
            'name' => 'Jo',
            'email' => 'invalid-email'
        ]);

        $strictRules = [
            'name' => 'string:min=5',
            'email' => 'email'
        ];

        $this->assertTrue($this->validator->fails($invalidRequest, $strictRules));
    }

    public function testValidateOrThrow(): void
    {
        $request = $this->createRequest([
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $rules = [
            'name' => 'string',
            'email' => 'email'
        ];

        // Should not throw for valid data
        $result = $this->validator->validateOrThrow($request, $rules);
        $this->assertIsArray($result);

        // Should throw for invalid data
        $invalidRequest = $this->createRequest([
            'name' => 'Jo',
            'email' => 'invalid-email'
        ]);

        $strictRules = [
            'name' => 'string:min=5',
            'email' => 'email'
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateOrThrow($invalidRequest, $strictRules);
    }

    public function testValidateComplexRules(): void
    {
        $request = $this->createRequest([
            'username' => 'john_doe',
            'password' => 'secret123',
            'email' => 'john@example.com',
            'age' => 25,
            'score' => 85.5
        ]);

        $rules = [
            'username' => 'string:min=3,max=20',
            'password' => 'string:min=8',
            'email' => 'email',
            'age' => 'number:min=18,max=120',
            'score' => 'number:min=0,max=100'
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateEmptyRules(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        $result = $this->validator->validate($request, []);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateEmptyData(): void
    {
        $request = $this->createRequest([]);

        $rules = [
            'name' => 'string',
            'email' => 'email'
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        // Null values are typically allowed, so should be valid
        $this->assertArrayHasKey('valid', $result);
    }

    public function testValidateFieldWithNonExistentRule(): void
    {
        $request = $this->createRequest(['field' => 'value']);

        $result = $this->validator->validateField('field', 'nonexistent', $request);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result); // Should have error
    }

    public function testValidateWithNonExistentRule(): void
    {
        $request = $this->createRequest(['field' => 'value']);

        $result = $this->validator->validate($request, ['field' => 'nonexistent']);

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidatePerformance(): void
    {
        // Create a large dataset to test performance
        $data = [];
        $rules = [];

        for ($i = 0; $i < 100; $i++) {
            $data["field{$i}"] = "value{$i}";
            $rules["field{$i}"] = 'string:min=3';
        }

        $request = $this->createRequest($data);

        $startTime = microtime(true);
        $result = $this->validator->validate($request, $rules);
        $endTime = microtime(true);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);

        // Should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $endTime - $startTime);
    }

    public function testValidateMultipleRulesPerField(): void
    {
        $request = $this->createRequest([
            'email' => 'john@example.com'
        ]);

        $rules = [
            'email' => ['string', 'email']
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
    }

    public function testValidateWithDifferentRuleFormats(): void
    {
        $request = $this->createRequest([
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25
        ]);

        $rules = [
            'name' => 'string:min=3', // String format with equals
            'email' => ['email'], // Array format
            'age' => ['number:min=18'] // Array with string rule
        ];

        $result = $this->validator->validate($request, $rules);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
    }
}
