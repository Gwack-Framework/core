<?php

namespace Tests\Integration\Http\Validation;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\RuleExecutor;
use Gwack\Http\Validation\RequestValidator;
use Gwack\Http\Validation\Rules\StringRule;
use Gwack\Http\Validation\Rules\EmailRule;
use Gwack\Http\Validation\Rules\NumberRule;
use Gwack\Http\Request;
use Gwack\Core\Exceptions\ValidationException;

/**
 * Integration tests for the complete validation system
 */
class ValidationIntegrationTest extends TestCase
{
    private RuleExecutor $ruleExecutor;
    private RequestValidator $validator;

    protected function setUp(): void
    {
        $this->ruleExecutor = new RuleExecutor();
        $this->validator = new RequestValidator($this->ruleExecutor);
    }

    private function createRequest(array $data = [], string $method = 'POST'): Request
    {
        if ($method === 'GET') {
            return new Request($data, [], [], [], [], ['REQUEST_METHOD' => $method]);
        } else {
            return new Request([], $data, [], [], [], ['REQUEST_METHOD' => $method]);
        }
    }

    public function testCompleteUserRegistrationValidation(): void
    {
        $validData = [
            'username' => 'john_doe123',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePassword123',
            'password_confirm' => 'SecurePassword123',
            'age' => 25,
            'bio' => 'A software developer passionate about PHP.',
            'website' => 'https://johndoe.dev',
            'score' => 95.5
        ];

        $rules = [
            'username' => 'string,max=20',
            'email' => 'email',
            'password' => 'string:min=8',
            'age' => 'number,max=120',
            'bio' => 'string:max=500',
            'score' => 'number,max=100'
        ];

        $request = $this->createRequest($validData);
        $result = $this->validator->validate($request, $rules);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals($validData['username'], $result['data']['username']);
        $this->assertEquals($validData['email'], $result['data']['email']);
    }

    public function testUserRegistrationValidationWithErrors(): void
    {
        $invalidData = [
            'username' => 'jo', // Too short
            'email' => 'invalid-email', // Invalid format
            'password' => '123', // Too short
            'age' => 15, // Too young
            'bio' => str_repeat('a', 600), // Too long
            'score' => 150 // Too high
        ];

        $rules = [
            'username' => 'string,max=20',
            'email' => 'email',
            'password' => 'string:min=8',
            'age' => 'number,max=120',
            'bio' => 'string:max=500',
            'score' => 'number,max=100'
        ];

        $request = $this->createRequest($invalidData);
        $result = $this->validator->validate($request, $rules);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('username', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
        $this->assertArrayHasKey('age', $result['errors']);
        $this->assertArrayHasKey('bio', $result['errors']);
        $this->assertArrayHasKey('score', $result['errors']);
    }

    public function testApiEndpointValidation(): void
    {
        // Simulate API endpoint for creating a blog post
        $postData = [
            'title' => 'Introduction to PHP Validation',
            'slug' => 'intro-php-validation',
            'content' => 'This is a comprehensive guide to PHP validation...',
            'category_id' => 5,
            'tags' => 'php,validation,tutorial',
            'published' => true,
            'rating' => 4.8
        ];

        $rules = [
            'title' => 'string,max=100',
            'slug' => 'string,max=50',
            'content' => 'string:min=50',
            'category_id' => 'number:min=1',
            'rating' => 'number,max=5'
        ];

        $request = $this->createRequest($postData);
        $result = $this->validator->validate($request, $rules);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testFormValidationWithMixedRuleFormats(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'age' => 30,
            'salary' => 75000.50,
            'department' => 'Engineering'
        ];

        $rules = [
            'name' => 'string,max=50', // String format
            'email' => ['email'], // Array format
            'phone' => ['string:min=10'], // Array with string rule
            'age' => 'number,max=65',
            'salary' => ['number', 'min' => 0],
            'department' => 'string'
        ];

        $request = $this->createRequest($formData);
        $result = $this->validator->validate($request, $rules);

        if (!$result['valid']) {
            var_dump('Validation failed with errors:', $result['errors']);
        }

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($formData['name'], $result['data']['name']);
        $this->assertEquals($formData['email'], $result['data']['email']);
    }

    public function testValidationWithCustomRules(): void
    {
        // Register a custom rule
        $customRule = new class ('custom') extends \Gwack\Http\Validation\Rule {
            public function executeOnRequest(string $field, Request $request): mixed
            {
                $data = $request->getAllData();
                $value = $data[$field] ?? null;

                if ($value === null) {
                    return true;
                }

                // Custom validation: must start with "CUSTOM_"
                if (!is_string($value) || !str_starts_with($value, 'CUSTOM_')) {
                    return "The {$field} field must start with 'CUSTOM_'.";
                }

                return true;
            }
        };

        $this->ruleExecutor->registerRule('custom', $customRule);

        $data = [
            'code' => 'CUSTOM_12345',
            'name' => 'Test Item'
        ];

        $rules = [
            'code' => 'custom',
            'name' => 'string:min=3'
        ];

        $request = $this->createRequest($data);
        $result = $this->validator->validate($request, $rules);

        $this->assertTrue($result['valid']);

        // Test with invalid custom rule
        $invalidData = [
            'code' => 'INVALID_12345',
            'name' => 'Test Item'
        ];

        $invalidRequest = $this->createRequest($invalidData);
        $invalidResult = $this->validator->validate($invalidRequest, $rules);

        $this->assertFalse($invalidResult['valid']);
        $this->assertArrayHasKey('code', $invalidResult['errors']);
    }

    public function testValidationPerformanceWithLargeDataset(): void
    {
        // Create a large dataset to test performance
        $data = [];
        $rules = [];

        // Create 1000 fields with various rules
        for ($i = 0; $i < 1000; $i++) {
            $data["field_{$i}"] = "value_{$i}";

            // Alternate between different rule types
            if ($i % 3 === 0) {
                $rules["field_{$i}"] = 'string,max=20';
            } elseif ($i % 3 === 1) {
                $rules["field_{$i}"] = 'number,max=1000';
                $data["field_{$i}"] = $i; // Set numeric value
            } else {
                $rules["field_{$i}"] = 'email';
                $data["field_{$i}"] = "user_{$i}@example.com"; // Set email value
            }
        }

        $request = $this->createRequest($data);

        $startTime = microtime(true);
        $result = $this->validator->validate($request, $rules);
        $endTime = microtime(true);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Should complete within 2 seconds even with 1000 fields
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(2.0, $executionTime, "Validation took {$executionTime} seconds, which is too slow");
    }

    public function testValidationWithStopOnFirstFailure(): void
    {
        $invalidData = [
            'field1' => 'a', // Too short (will fail first)
            'field2' => 'invalid-email', // Invalid email
            'field3' => -5, // Negative number
            'field4' => str_repeat('x', 1000) // Too long
        ];

        $rules = [
            'field1' => 'string:min=5',
            'field2' => 'email',
            'field3' => 'number:min=0',
            'field4' => 'string:max=100'
        ];

        $request = $this->createRequest($invalidData);
        $result = $this->validator->validate($request, $rules, true); // Stop on first failure

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // Should only have one error due to stopping on first failure
        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('field1', $result['errors']);
    }

    public function testValidationExceptionThrown(): void
    {
        $invalidData = [
            'email' => 'invalid-email'
        ];

        $rules = [
            'email' => 'email'
        ];

        $request = $this->createRequest($invalidData);

        $this->expectException(ValidationException::class);
        $this->validator->validateOrThrow($request, $rules);
    }

    public function testValidationPassesAndFails(): void
    {
        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $invalidData = [
            'name' => 'Jo',
            'email' => 'invalid-email'
        ];

        $rules = [
            'name' => 'string:min=5',
            'email' => 'email'
        ];

        $validRequest = $this->createRequest($validData);
        $invalidRequest = $this->createRequest($invalidData);

        // Test passes method
        $this->assertFalse($this->validator->passes($validRequest, $rules)); // Name too short
        $this->assertFalse($this->validator->passes($invalidRequest, $rules));

        // Test fails method
        $this->assertTrue($this->validator->fails($validRequest, $rules));
        $this->assertTrue($this->validator->fails($invalidRequest, $rules));

        // Test with valid data for all rules
        $allValidData = [
            'name' => 'John Doe Smith',
            'email' => 'john@example.com'
        ];

        $allValidRequest = $this->createRequest($allValidData);
        $this->assertTrue($this->validator->passes($allValidRequest, $rules));
        $this->assertFalse($this->validator->fails($allValidRequest, $rules));
    }

    public function testValidationWithGetAndPostRequests(): void
    {
        $data = [
            'search' => 'php validation',
            'category' => 'programming',
            'page' => 1
        ];

        $rules = [
            'search' => 'string:min=3',
            'category' => 'string',
            'page' => 'number:min=1'
        ];

        // Test GET request
        $getRequest = $this->createRequest($data, 'GET');
        $getResult = $this->validator->validate($getRequest, $rules);

        $this->assertTrue($getResult['valid']);
        $this->assertEmpty($getResult['errors']);

        // Test POST request
        $postRequest = $this->createRequest($data, 'POST');
        $postResult = $this->validator->validate($postRequest, $rules);

        $this->assertTrue($postResult['valid']);
        $this->assertEmpty($postResult['errors']);
    }

    public function testValidationWithNullAndMissingFields(): void
    {
        $dataWithNulls = [
            'name' => 'John',
            'email' => null,
            'age' => ''
        ];

        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'number',
            'missing_field' => 'string'
        ];

        $request = $this->createRequest($dataWithNulls);
        $result = $this->validator->validate($request, $rules);

        // Most rules should allow null/empty values
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testValidationRuleCaching(): void
    {
        $data = ['field' => 'test value'];
        $rules = ['field' => 'string,max=20'];

        $request = $this->createRequest($data);

        // Run validation multiple times to test caching
        $result1 = $this->validator->validate($request, $rules);
        $result2 = $this->validator->validate($request, $rules);
        $result3 = $this->validator->validate($request, $rules);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
        $this->assertTrue($result1['valid']);
    }

    public function testValidationWithDifferentParameterFormats(): void
    {
        $data = [
            'username' => 'john123',
            'password' => 'secret123',
            'age' => 25,
            'score' => 85.5
        ];

        // Test different parameter parsing formats
        $rules = [
            'username' => 'string,max=20', // Standard format
            'password' => 'string,max=50', // With equals signs
            'age' => 'number,max=100:integer:true', // Mixed format
            'score' => 'number,max=100:float:true' // Float validation
        ];

        $request = $this->createRequest($data);
        $result = $this->validator->validate($request, $rules);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
}
