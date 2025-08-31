<?php

namespace Tests\Unit\Http\Validation;

use PHPUnit\Framework\TestCase;
use Gwack\Http\Validation\RuleExecutor;
use Gwack\Http\Validation\Rule;
use Gwack\Http\Request;

/**
 * Unit tests for RuleExecutor
 */
class RuleExecutorTest extends TestCase
{
    private RuleExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new RuleExecutor();
    }

    private function createRequest(array $data): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getAllData')->willReturn($data);
        return $request;
    }

    public function testConstructorRegistersDefaultRules(): void
    {
        $this->assertTrue($this->executor->hasRule('string'));
        $this->assertTrue($this->executor->hasRule('email'));
        $this->assertTrue($this->executor->hasRule('number'));

        // Test aliases
        $this->assertTrue($this->executor->hasRule('str'));
        $this->assertTrue($this->executor->hasRule('mail'));
        $this->assertTrue($this->executor->hasRule('num'));
    }

    public function testRegisterRule(): void
    {
        $mockRule = $this->createMock(Rule::class);
        $mockRule->method('getName')->willReturn('custom');

        $this->executor->registerRule('custom', $mockRule, ['alias1', 'alias2']);

        $this->assertTrue($this->executor->hasRule('custom'));
        $this->assertTrue($this->executor->hasRule('alias1'));
        $this->assertTrue($this->executor->hasRule('alias2'));
    }

    public function testGetRule(): void
    {
        $rule = $this->executor->getRule('string');
        $this->assertNotNull($rule);
        $this->assertEquals('string', $rule->getName());

        // Test with alias
        $aliasRule = $this->executor->getRule('str');
        $this->assertNotNull($aliasRule);
        $this->assertEquals('string', $aliasRule->getName());
    }

    public function testGetRuleWithParameters(): void
    {
        $rule = $this->executor->getRule('string', ['min' => 5]);
        $this->assertNotNull($rule);
        $this->assertEquals(['min' => 5], $rule->getParameters());
    }

    public function testGetNonExistentRule(): void
    {
        $rule = $this->executor->getRule('nonexistent');
        $this->assertNull($rule);
    }

    public function testExecuteRule(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        $result = $this->executor->executeRule('string', 'name', $request);
        $this->assertTrue($result);

        // Test with invalid data
        $invalidRequest = $this->createRequest(['name' => []]);
        $result = $this->executor->executeRule('string', 'name', $invalidRequest);
        $this->assertIsString($result);
    }

    public function testExecuteRuleWithParameters(): void
    {
        $request = $this->createRequest(['name' => 'Jo']);

        $result = $this->executor->executeRule('string', 'name', $request, ['min' => 5]);
        $this->assertIsString($result); // Should fail min length

        $validRequest = $this->createRequest(['name' => 'John Doe']);
        $result = $this->executor->executeRule('string', 'name', $validRequest, ['min' => 5]);
        $this->assertTrue($result);
    }

    public function testExecuteRuleWithNonExistentRule(): void
    {
        $request = $this->createRequest(['field' => 'value']);

        $result = $this->executor->executeRule('nonexistent', 'field', $request);
        $this->assertIsString($result);
        $this->assertStringContainsString('Unknown validation rule', $result);
    }

    public function testExecuteRules(): void
    {
        $request = $this->createRequest(['email' => 'test@example.com']);

        $rules = ['string', 'email'];
        $results = $this->executor->executeRules($rules, 'email', $request);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('string', $results);
        $this->assertArrayHasKey('email', $results);
        $this->assertTrue($results['string']);
        $this->assertTrue($results['email']);
    }

    public function testExecuteRulesWithParameters(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        $rules = [
            'string' => ['min' => 3],
            'email' => []
        ];
        $results = $this->executor->executeRules($rules, 'name', $request);

        $this->assertIsArray($results);
        $this->assertTrue($results['string']); // Should pass min length
        $this->assertIsString($results['email']); // Should fail email validation
    }

    public function testExecuteRulesWithStringFormat(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        // Test string format: ['required', 'string', 'email']
        $rules = ['string', 'email'];
        $results = $this->executor->executeRules($rules, 'name', $request);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    public function testExecuteRulesWithColonSeparatedFormat(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        // Test colon-separated format: ['string:min:3', 'email']
        $rules = ['string:min:3', 'email'];
        $results = $this->executor->executeRules($rules, 'name', $request);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('string', $results);
        $this->assertArrayHasKey('email', $results);
    }

    public function testExecuteRulesStopsOnFirstFailure(): void
    {
        $request = $this->createRequest(['name' => 'Jo']); // Too short

        $rules = [
            'string' => ['min' => 5], // Will fail
            'email' => [] // Won't be executed
        ];
        $results = $this->executor->executeRules($rules, 'name', $request);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('string', $results);
        $this->assertArrayNotHasKey('email', $results); // Should stop after first failure
        $this->assertIsString($results['string']);
    }

    public function testGetRuleNames(): void
    {
        $names = $this->executor->getRuleNames();
        $this->assertIsArray($names);
        $this->assertContains('string', $names);
        $this->assertContains('email', $names);
        $this->assertContains('number', $names);
    }

    public function testGetAliases(): void
    {
        $aliases = $this->executor->getAliases();
        $this->assertIsArray($aliases);
        $this->assertArrayHasKey('str', $aliases);
        $this->assertArrayHasKey('mail', $aliases);
        $this->assertArrayHasKey('num', $aliases);
        $this->assertEquals('string', $aliases['str']);
        $this->assertEquals('email', $aliases['mail']);
        $this->assertEquals('number', $aliases['num']);
    }

    public function testGetAvailableRules(): void
    {
        $rules = $this->executor->getAvailableRules();
        $this->assertIsArray($rules);
        $this->assertContains('string', $rules);
        $this->assertContains('email', $rules);
        $this->assertContains('number', $rules);
    }

    public function testGetAvailableAliases(): void
    {
        $aliases = $this->executor->getAvailableAliases();
        $this->assertIsArray($aliases);
        $this->assertContains('str', $aliases);
        $this->assertContains('mail', $aliases);
        $this->assertContains('num', $aliases);
    }

    public function testClearCache(): void
    {
        // Get a rule to populate cache
        $rule1 = $this->executor->getRule('string', ['min' => 5]);
        $this->assertNotNull($rule1);

        // Clear cache
        $this->executor->clearCache();

        // Get the same rule again - should create new instance
        $rule2 = $this->executor->getRule('string', ['min' => 5]);
        $this->assertNotNull($rule2);

        // They should be different instances due to cache clearing
        $this->assertNotSame($rule1, $rule2);
    }

    public function testRuleCaching(): void
    {
        // Get the same rule twice with same parameters
        $rule1 = $this->executor->getRule('string', ['min' => 5]);
        $rule2 = $this->executor->getRule('string', ['min' => 5]);

        // Should return same cached instance
        $this->assertSame($rule1, $rule2);

        // But different parameters should create different instances
        $rule3 = $this->executor->getRule('string', ['min' => 10]);
        $this->assertNotSame($rule1, $rule3);
    }

    public function testParseRuleParameters(): void
    {
        $request = $this->createRequest(['name' => 'John']);

        // Test parsing of colon-separated parameters
        $rules = ['string:min:3:max:10'];
        $results = $this->executor->executeRules($rules, 'name', $request);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('string', $results);
        $this->assertTrue($results['string']); // Should pass
    }

    public function testParseParameterValues(): void
    {
        $request = $this->createRequest(['count' => '5']);

        // Test parsing of different parameter value types
        $rules = ['number:min:1:max:10:integer:true'];
        $results = $this->executor->executeRules($rules, 'count', $request);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('number', $results);
    }

    public function testRegisterRuleOverwrite(): void
    {
        $mockRule = $this->createMock(Rule::class);
        $mockRule->method('getName')->willReturn('custom');

        // Register rule twice
        $this->executor->registerRule('custom', $mockRule);
        $this->executor->registerRule('custom', $mockRule);

        $this->assertTrue($this->executor->hasRule('custom'));
    }

    public function testRegisterRuleWithAliasOverwrite(): void
    {
        $mockRule1 = $this->createMock(Rule::class);
        $mockRule1->method('getName')->willReturn('custom1');

        $mockRule2 = $this->createMock(Rule::class);
        $mockRule2->method('getName')->willReturn('custom2');

        // Register rule with alias
        $this->executor->registerRule('custom1', $mockRule1, ['shared']);
        $this->executor->registerRule('custom2', $mockRule2, ['shared']);

        // The alias should point to the last registered rule
        $this->assertTrue($this->executor->hasRule('shared'));
        $rule = $this->executor->getRule('shared');
        $this->assertEquals('custom2', $rule->getName());
    }
}
