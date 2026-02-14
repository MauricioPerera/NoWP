<?php

/**
 * Validation Middleware
 * 
 * Validates and sanitizes request input data.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace Framework\Core\Middleware;

use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\Exceptions\ValidationException;

class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;
    
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }
    
    public function handle(Request $request, callable $next): Response
    {
        // Validate if rules are provided
        if (!empty($this->rules)) {
            // Sanitize and get all data
            $data = $this->sanitizeData($request->all());
            
            $errors = $this->validate($data, $this->rules);
            
            if (!empty($errors)) {
                throw new ValidationException('Validation failed', $errors);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Sanitize data array
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($value);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize request data
     *
     * @param Request $request
     * @return void
     */
    private function sanitizeRequest(Request $request): void
    {
        // Note: Request properties are readonly
        // Sanitization is handled in sanitizeData() method
    }
    
    /**
     * Sanitize a value
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        return $value;
    }
    
    /**
     * Validate data against rules
     *
     * @param array $data
     * @param array $rules
     * @return array Validation errors
     */
    private function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $error = $this->validateRule($field, $value, $rule);
                
                if ($error) {
                    $errors[$field] = $error;
                    break; // Stop at first error for this field
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return string|null Error message or null if valid
     */
    private function validateRule(string $field, mixed $value, string $rule): ?string
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];
        
        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'email' => $this->validateEmail($field, $value),
            'min' => $this->validateMin($field, $value, $parameters[0] ?? 0),
            'max' => $this->validateMax($field, $value, $parameters[0] ?? 0),
            'numeric' => $this->validateNumeric($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'string' => $this->validateString($field, $value),
            'array' => $this->validateArray($field, $value),
            'url' => $this->validateUrl($field, $value),
            'in' => $this->validateIn($field, $value, $parameters),
            'regex' => $this->validateRegex($field, $value, $parameters[0] ?? ''),
            default => null,
        };
    }
    
    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "The {$field} field is required";
        }
        return null;
    }
    
    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null; // Skip if empty (use 'required' rule for that)
        }
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} must be a valid email address";
        }
        return null;
    }
    
    private function validateMin(string $field, mixed $value, int|string $min): ?string
    {
        $min = (int)$min;
        
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_string($value) && mb_strlen($value) < $min) {
            return "The {$field} must be at least {$min} characters";
        }
        
        if (is_numeric($value) && $value < $min) {
            return "The {$field} must be at least {$min}";
        }
        
        return null;
    }
    
    private function validateMax(string $field, mixed $value, int|string $max): ?string
    {
        $max = (int)$max;
        
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_string($value) && mb_strlen($value) > $max) {
            return "The {$field} must not exceed {$max} characters";
        }
        
        if (is_numeric($value) && $value > $max) {
            return "The {$field} must not exceed {$max}";
        }
        
        return null;
    }
    
    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!is_numeric($value)) {
            return "The {$field} must be a number";
        }
        return null;
    }
    
    private function validateInteger(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0) {
            return "The {$field} must be an integer";
        }
        return null;
    }
    
    private function validateString(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!is_string($value)) {
            return "The {$field} must be a string";
        }
        return null;
    }
    
    private function validateArray(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (!is_array($value)) {
            return "The {$field} must be an array";
        }
        return null;
    }
    
    private function validateUrl(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return "The {$field} must be a valid URL";
        }
        return null;
    }
    
    private function validateIn(string $field, mixed $value, array $allowed): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!in_array($value, $allowed, true)) {
            $list = implode(', ', $allowed);
            return "The {$field} must be one of: {$list}";
        }
        return null;
    }
    
    private function validateRegex(string $field, mixed $value, string $pattern): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!preg_match($pattern, (string)$value)) {
            return "The {$field} format is invalid";
        }
        return null;
    }
}
