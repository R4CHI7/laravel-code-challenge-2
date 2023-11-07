<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        $this->debitCard->debitCardTransactions()->createMany([[
            'amount' => 10000,
            'currency_code' => 'SGD'
        ], [
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response
            ->assertOk()
            ->assertJson([
                ['amount' => 10000, 'currency_code' => 'SGD'],
                ['amount' => 15000, 'currency_code' => 'SGD']
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $debitCard = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()
        ]);
        $debitCard->debitCardTransactions()->createMany([[
            'amount' => 10000,
            'currency_code' => 'SGD'
        ], [
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$debitCard->id}");

        $response
            ->assertForbidden()
            ->assertJsonFragment(["message" => "This action is unauthorized."]);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $response = $this->postJson("/api/debit-card-transactions", [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);

        $response
            ->assertCreated()
            ->assertJson(['amount' => 15000, 'currency_code' => 'SGD']);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $debitCard = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()
        ]);

        $response = $this->postJson("/api/debit-card-transactions", [
            'debit_card_id' => $debitCard->id,
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $txn = $this->debitCard->debitCardTransactions()->create([
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$txn->id}");

        $response
            ->assertOk()
            ->assertJson(['amount' => 15000, 'currency_code' => 'SGD']);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $debitCard = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()
        ]);
        $txn = $debitCard->debitCardTransactions()->create([
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$txn->id}");

        $response
            ->assertForbidden()
            ->assertJsonFragment(["message" => "This action is unauthorized."]);
    }

    // Extra bonus for extra tests :)
    public function testCustomerCannotSeeAListOfDebitCardTransactionsIfDebitCardDoesNotExist()
    {
        $this->debitCard->debitCardTransactions()->createMany([[
            'amount' => 10000,
            'currency_code' => 'SGD'
        ], [
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id=999");

        $response->assertForbidden();
    }

    public function testCustomerCannotCreateADebitCardTransactionWithInvalidData()
    {
        $response = $this->postJson("/api/debit-card-transactions", [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 15000
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonFragment(['currency_code' => ['The currency code field is required.']]);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 15000,
            'currency_code' => 'SGD'
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionIfItDoesntExist() {
        $response = $this->getJson("/api/debit-card-transactions/999");

        $response->assertNotFound();
    }
}
