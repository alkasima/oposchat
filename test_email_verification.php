<?php

require_once 'vendor/autoload.php';

echo "=== Testing Email Verification System ===\n\n";

// Test 1: Check if User model has required methods
echo "Test 1: Checking User model methods:\n";
$user = new App\Models\User();
$methods = ['generateEmailVerificationToken', 'verifyEmail', 'hasVerifiedEmail', 'needsEmailVerification', 'getEmailVerificationUrl', 'canResendVerificationEmail'];
foreach ($methods as $method) {
    echo "- $method: " . (method_exists($user, $method) ? 'EXISTS' : 'MISSING') . "\n";
}

// Test 2: Check if EmailVerificationController exists
echo "\nTest 2: Checking EmailVerificationController:\n";
echo "- Class exists: " . (class_exists('App\\Http\\Controllers\\EmailVerificationController') ? 'YES' : 'NO') . "\n";

// Test 3: Check if EnsureEmailIsVerified middleware exists
echo "\nTest 3: Checking EnsureEmailIsVerified middleware:\n";
echo "- Class exists: " . (class_exists('App\\Http\\Middleware\\EnsureEmailIsVerified') ? 'YES' : 'NO') . "\n";

// Test 4: Check if views exist
echo "\nTest 4: Checking views:\n";
echo "- email-verified.blade.php: " . (file_exists('resources/views/auth/email-verified.blade.php') ? 'EXISTS' : 'MISSING') . "\n";
echo "- email-verification-error.blade.php: " . (file_exists('resources/views/auth/email-verification-error.blade.php') ? 'EXISTS' : 'MISSING') . "\n";
echo "- email-verification.blade.php: " . (file_exists('resources/views/emails/email-verification.blade.php') ? 'EXISTS' : 'MISSING') . "\n";

// Test 5: Check email configuration
echo "\nTest 5: Checking email configuration:\n";
try {
    $mailConfig = config('services.ses');
    echo "- SES config exists: " . (empty($mailConfig) ? 'NO' : 'YES') . "\n";
    if (!empty($mailConfig)) {
        echo "- SES region: " . ($mailConfig['region'] ?? 'NOT SET') . "\n";
        echo "- SES from address: " . ($mailConfig['from_address'] ?? 'NOT SET') . "\n";
    }
} catch (Exception $e) {
    echo "- Error reading SES config: " . $e->getMessage() . "\n";
}

echo "\n=== Email Verification System Check Complete ===\n";