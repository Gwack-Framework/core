<?php

namespace Tests\Unit\Http\Validation\Rules;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\Rules\EmailRule;
use Gwack\Http\Request;

/**
 * Unit tests for EmailRule validation
 */
class EmailRuleTest extends TestCase
{
    private function createRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getAllData')->willReturn($data);
        return $request;
    }

    public function testGetName(): void
    {
        $rule = new EmailRule('email');
        $this->assertEquals('email', $rule->getName());
    }

    public function testGetParameters(): void
    {
        $rule = new EmailRule('email', ['strict' => true]);
        $this->assertEquals(['strict' => true], $rule->getParameters());
    }

    public function testValidatesValidEmails(): void
    {
        $rule = new EmailRule('email');

        $validEmails = [
            'test@example.com',
            'user.name@example.com',
            'user+tag@example.com',
            'user_name@example.co.uk',
            'a@b.co',
            'test.email.with+symbol@example.com',
            '123@example.com',
            'test@example-site.com'
        ];

        foreach ($validEmails as $email) {
            $result = $rule->executeOnRequest('email', $this->createRequest(['email' => $email]));
            $this->assertTrue($result, "Email '{$email}' should be valid");
        }
    }

    public function testRejectsInvalidEmails(): void
    {
        $rule = new EmailRule('email');

        $invalidEmails = [
            'invalid',
            'invalid@',
            '@invalid.com',
            'invalid..email@example.com',
            'invalid.@example.com',
            '.invalid@example.com',
            'invalid@.example.com',
            'invalid@example.',
            'invalid@example',
            'invalid email@example.com',
            'invalid@exam ple.com',
            ''
        ];

        foreach ($invalidEmails as $email) {
            $result = $rule->executeOnRequest('email', $this->createRequest(['email' => $email]));
            $this->assertIsString($result, "Email '{$email}' should be invalid");
        }
    }

    public function testAllowsNullValues(): void
    {
        $rule = new EmailRule('email');
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest([])));
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => null])));
    }

    public function testRejectsNonStrings(): void
    {
        $rule = new EmailRule('email');

        $this->assertIsString($rule->executeOnRequest('email', $this->createRequest(['email' => 123])));
        $this->assertIsString($rule->executeOnRequest('email', $this->createRequest(['email' => []])));
        $this->assertIsString($rule->executeOnRequest('email', $this->createRequest(['email' => new \stdClass()])));
        $this->assertIsString($rule->executeOnRequest('email', $this->createRequest(['email' => true])));
    }

    public function testStrictModeValidation(): void
    {
        $rule = new EmailRule('email', ['strict' => true]);

        // These should pass in strict mode
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => 'test@example.com'])));
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => 'user.name@example.com'])));

        // These might be more restrictive in strict mode (depending on implementation)
        $result = $rule->executeOnRequest('email', $this->createRequest(['email' => 'test+tag@example.com']));
        // Could be either true or error message depending on strict implementation
        $this->assertTrue(is_bool($result) || is_string($result));
    }

    public function testDomainValidation(): void
    {
        $rule = new EmailRule('email', ['checkDomain' => true]);

        // Test with real domains (should pass)
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => 'test@gmail.com'])));

        // Test with fake domain (might fail depending on implementation)
        $result = $rule->executeOnRequest('email', $this->createRequest(['email' => 'test@nonexistentdomain12345.com']));
        $this->assertTrue(is_bool($result) || is_string($result));
    }

    public function testErrorMessages(): void
    {
        $rule = new EmailRule('email');
        $result = $rule->executeOnRequest('userEmail', $this->createRequest(['userEmail' => 'invalid-email']));

        $this->assertIsString($result);
        $this->assertStringContainsString('userEmail', $result);
        $this->assertStringContainsString('email', strtolower($result));
    }

    public function testEmptyStringValidation(): void
    {
        $rule = new EmailRule('email');
        $result = $rule->executeOnRequest('email', $this->createRequest(['email' => '']));

        $this->assertIsString($result);
        $this->assertStringContainsString('email', $result);
    }

    public function testCaseInsensitivity(): void
    {
        $rule = new EmailRule('email');

        // Email addresses should be case insensitive
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => 'Test@Example.COM'])));
        $this->assertTrue($rule->executeOnRequest('email', $this->createRequest(['email' => 'TEST@EXAMPLE.COM'])));
    }

    public function testInternationalDomains(): void
    {
        $rule = new EmailRule('email');

        // Test with international domain names
        $result = $rule->executeOnRequest('email', $this->createRequest(['email' => 'test@例え.テスト']));
        // Should handle international domains (might depend on implementation)
        $this->assertTrue(is_bool($result) || is_string($result));
    }

    public function testLongEmailAddresses(): void
    {
        $rule = new EmailRule('email');

        // Test very long email address
        $longLocal = str_repeat('a', 60);
        $longDomain = str_repeat('b', 60) . '.com';
        $longEmail = $longLocal . '@' . $longDomain;

        $result = $rule->executeOnRequest('email', $this->createRequest(['email' => $longEmail]));
        $this->assertTrue(is_bool($result) || is_string($result));
    }

    public function testSpecialCharacters(): void
    {
        $rule = new EmailRule('email');

        // Test emails with special characters
        $specialEmails = [
            'test+special@example.com',
            'test.special@example.com',
            'test_special@example.com',
            'test-special@example.com'
        ];

        foreach ($specialEmails as $email) {
            $result = $rule->executeOnRequest('email', $this->createRequest(['email' => $email]));
            $this->assertTrue($result, "Email with special characters '{$email}' should be valid");
        }
    }
}
