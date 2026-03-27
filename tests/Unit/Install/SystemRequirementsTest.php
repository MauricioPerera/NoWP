<?php

use ChimeraNoWP\Install\SystemRequirements;

it('checks PHP version requirement', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('php_version');
    expect($checks['php_version'])->toHaveKey('met');
    expect($checks['php_version'])->toHaveKey('current');
    expect($checks['php_version'])->toHaveKey('required');
    expect($checks['php_version']['met'])->toBeTrue(); // Should pass on PHP 8.1+
});

it('checks PDO extension', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('pdo_extension');
    expect($checks['pdo_extension']['met'])->toBeTrue(); // Should be installed
});

it('checks PDO MySQL driver', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('pdo_mysql');
    // May or may not be installed depending on environment
    expect($checks['pdo_mysql'])->toHaveKey('met');
});

it('checks mbstring extension', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('mbstring');
    expect($checks['mbstring']['met'])->toBeTrue(); // Should be installed
});

it('checks JSON extension', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('json');
    expect($checks['json']['met'])->toBeTrue(); // Should be installed
});

it('checks fileinfo extension', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('fileinfo');
    // May or may not be installed
    expect($checks['fileinfo'])->toHaveKey('met');
});

it('checks GD extension', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('gd');
    // May or may not be installed
    expect($checks['gd'])->toHaveKey('met');
});

it('checks writable storage directory', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('writable_storage');
    expect($checks['writable_storage'])->toHaveKey('met');
    expect($checks['writable_storage'])->toHaveKey('path');
});

it('checks writable cache directory', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('writable_cache');
    expect($checks['writable_cache'])->toHaveKey('met');
    expect($checks['writable_cache'])->toHaveKey('path');
});

it('checks mod_rewrite availability', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toHaveKey('mod_rewrite');
    expect($checks['mod_rewrite'])->toHaveKey('met');
});

it('returns all requirements check results', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    expect($checks)->toBeArray();
    expect(count($checks))->toBeGreaterThan(5);
});

it('detects if all requirements are met', function () {
    $requirements = new SystemRequirements();
    $allMet = $requirements->allRequirementsMet();
    
    expect($allMet)->toBeBool();
});

it('returns list of missing requirements', function () {
    $requirements = new SystemRequirements();
    $missing = $requirements->getMissingRequirements();
    
    expect($missing)->toBeArray();
    // May be empty if all requirements are met
});

it('includes error messages for missing requirements', function () {
    $requirements = new SystemRequirements();
    $missing = $requirements->getMissingRequirements();
    
    foreach ($missing as $requirement) {
        expect($requirement)->toHaveKey('name');
        expect($requirement)->toHaveKey('message');
    }
});

it('checks all required extensions', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    $requiredExtensions = ['pdo_extension', 'mbstring', 'json'];
    
    foreach ($requiredExtensions as $ext) {
        expect($checks)->toHaveKey($ext);
    }
});

it('provides detailed messages for each check', function () {
    $requirements = new SystemRequirements();
    $checks = $requirements->checkAll();
    
    foreach ($checks as $check) {
        expect($check)->toHaveKey('met');
        expect($check)->toHaveKey('message');
        expect($check['message'])->toBeString();
    }
});
