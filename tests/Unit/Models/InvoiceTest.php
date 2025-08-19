<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_belongs_to_user()
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $invoice->user);
        $this->assertEquals($user->id, $invoice->user->id);
    }

    public function test_invoice_belongs_to_subscription()
    {
        $subscription = Subscription::factory()->create();
        $invoice = Invoice::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertInstanceOf(Subscription::class, $invoice->subscription);
        $this->assertEquals($subscription->id, $invoice->subscription->id);
    }

    public function test_is_paid_returns_true_for_paid_status()
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);
        $this->assertTrue($invoice->isPaid());
    }

    public function test_is_paid_returns_false_for_open_status()
    {
        $invoice = Invoice::factory()->create(['status' => 'open']);
        $this->assertFalse($invoice->isPaid());
    }

    public function test_is_open_returns_true_for_open_status()
    {
        $invoice = Invoice::factory()->create(['status' => 'open']);
        $this->assertTrue($invoice->isOpen());
    }

    public function test_is_void_returns_true_for_void_status()
    {
        $invoice = Invoice::factory()->create(['status' => 'void']);
        $this->assertTrue($invoice->isVoid());
    }

    public function test_is_draft_returns_true_for_draft_status()
    {
        $invoice = Invoice::factory()->create(['status' => 'draft']);
        $this->assertTrue($invoice->isDraft());
    }

    public function test_formatted_amount_attribute_converts_cents_to_dollars()
    {
        $invoice = Invoice::factory()->create(['amount_paid' => 2500]); // $25.00
        $this->assertEquals('25.00', $invoice->formatted_amount);
    }

    public function test_amount_in_dollars_attribute_converts_cents_to_dollars()
    {
        $invoice = Invoice::factory()->create(['amount_paid' => 1299]); // $12.99
        $this->assertEquals(12.99, $invoice->amount_in_dollars);
    }

    public function test_currency_symbol_attribute_returns_correct_symbol_for_usd()
    {
        $invoice = Invoice::factory()->create(['currency' => 'usd']);
        $this->assertEquals('$', $invoice->currency_symbol);
    }

    public function test_currency_symbol_attribute_returns_correct_symbol_for_eur()
    {
        $invoice = Invoice::factory()->create(['currency' => 'eur']);
        $this->assertEquals('€', $invoice->currency_symbol);
    }

    public function test_currency_symbol_attribute_returns_correct_symbol_for_gbp()
    {
        $invoice = Invoice::factory()->create(['currency' => 'gbp']);
        $this->assertEquals('£', $invoice->currency_symbol);
    }

    public function test_currency_symbol_attribute_returns_currency_code_for_unknown_currency()
    {
        $invoice = Invoice::factory()->create(['currency' => 'jpy']);
        $this->assertEquals('JPY', $invoice->currency_symbol);
    }

    public function test_amount_paid_is_cast_to_integer()
    {
        $invoice = Invoice::factory()->create(['amount_paid' => '1500']);
        $this->assertIsInt($invoice->amount_paid);
        $this->assertEquals(1500, $invoice->amount_paid);
    }
}