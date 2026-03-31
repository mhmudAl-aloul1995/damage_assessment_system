---
name: pest-testing
description: "Tests applications using the Pest 3 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works."
license: MIT
metadata:
  author: laravel
---
@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Pest Testing 3

## When to Apply

Activate this skill when:
- Creating new tests (unit or feature)
- Modifying existing tests
- Debugging test failures
- Working with datasets, mocking, or test organization
- Writing architecture tests

## Documentation

Use ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ for detailed Pest 3 patterns and documentation.

## Basic Usage

### Creating Tests

All tests must be written using Pest. Use ___SINGLE_BACKTICK___{{ $assist->artisanCommand('make:test --pest {name}') }}___SINGLE_BACKTICK___.

### Test Organization

- Tests live in the ___SINGLE_BACKTICK___tests/Feature___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___tests/Unit___SINGLE_BACKTICK___ directories.
- Do NOT remove tests without approval - these are core application code.
- Test happy paths, failure paths, and edge cases.

### Basic Test Structure

___BOOST_SNIPPET_0___

### Running Tests

- Run minimal tests with filter before finalizing: ___SINGLE_BACKTICK___{{ $assist->artisanCommand('test --compact --filter=testName') }}___SINGLE_BACKTICK___.
- Run all tests: ___SINGLE_BACKTICK___{{ $assist->artisanCommand('test --compact') }}___SINGLE_BACKTICK___.
- Run file: ___SINGLE_BACKTICK___{{ $assist->artisanCommand('test --compact tests/Feature/ExampleTest.php') }}___SINGLE_BACKTICK___.

## Assertions

Use specific assertions (___SINGLE_BACKTICK___assertSuccessful()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___assertNotFound()___SINGLE_BACKTICK___) instead of ___SINGLE_BACKTICK___assertStatus()___SINGLE_BACKTICK___:

___BOOST_SNIPPET_1___

| Use | Instead of |
|-----|------------|
| ___SINGLE_BACKTICK___assertSuccessful()___SINGLE_BACKTICK___ | ___SINGLE_BACKTICK___assertStatus(200)___SINGLE_BACKTICK___ |
| ___SINGLE_BACKTICK___assertNotFound()___SINGLE_BACKTICK___ | ___SINGLE_BACKTICK___assertStatus(404)___SINGLE_BACKTICK___ |
| ___SINGLE_BACKTICK___assertForbidden()___SINGLE_BACKTICK___ | ___SINGLE_BACKTICK___assertStatus(403)___SINGLE_BACKTICK___ |

## Mocking

Import mock function before use: ___SINGLE_BACKTICK___use function Pest\Laravel\mock;___SINGLE_BACKTICK___

## Datasets

Use datasets for repetitive tests (validation rules, etc.):

___BOOST_SNIPPET_2___

## Pest 3 Features

### Architecture Testing

Pest 3 includes architecture testing to enforce code conventions:

___BOOST_SNIPPET_3___

### Type Coverage

Pest 3 provides improved type coverage analysis. Run with ___SINGLE_BACKTICK___--type-coverage___SINGLE_BACKTICK___ flag.

## Common Pitfalls

- Not importing ___SINGLE_BACKTICK___use function Pest\Laravel\mock;___SINGLE_BACKTICK___ before using mock
- Using ___SINGLE_BACKTICK___assertStatus(200)___SINGLE_BACKTICK___ instead of ___SINGLE_BACKTICK___assertSuccessful()___SINGLE_BACKTICK___
- Forgetting datasets for repetitive validation tests
- Deleting tests without approval
