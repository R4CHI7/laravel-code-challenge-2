<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards

        // Create 2 cards for the user
        $this->user->debitCards()->createMany([
                [
                    'type' => 'credit_card',
                    'number' => rand(1000000000000000, 9999999999999999),
                    'expiration_date' => Carbon::now()->addYear(),
                ],
                [
                    'type' => 'credit_card',
                    'number' => rand(1000000000000000, 9999999999999999),
                    'expiration_date' => Carbon::now()->addYear(),
                ]]
        );
        $response = $this->getJson("/api/debit-cards");
        $response
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id', 'number', 'type', 'expiration_date', 'is_active'
                ]
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $user2 = User::factory()->create();
        $user2->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $response = $this->getJson("/api/debit-cards");
        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $card->id,
                'number' => $card->number,
                "type" => "credit_card",
                "is_active" => true]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $response = $this->postJson('/api/debit-cards', ['type' => 'credit_card']);
        $response
            ->assertCreated()
            ->assertJsonFragment(['id' => 1]);
        $this->assertDatabaseCount('debit_cards', 1);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $response = $this->getJson("/api/debit-cards/{$card->id}");
        $response
            ->assertOk()
            ->assertJsonFragment(['id' => 1, 'number' => $card->number]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);
        $this->assertDatabaseCount('debit_cards', 1);

        $response = $this->getJson("/api/debit-cards/999");
        $response
            ->assertNotFound();
    }

    public function testCustomerCanActivateADebitCard()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear(),
            'disabled_at' => Carbon::now()]);

        $this->assertFalse($card->getAttribute('is_active'));

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => true]);
        $response
            ->assertOk()
            ->assertJsonFragment(['id' => 1]);

        $this->assertDatabaseHas('debit_cards', [
           'id' => $card->id,
           'disabled_at' => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $this->assertTrue($card->getAttribute('is_active'));

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => false]);
        $response
            ->assertOk()
            ->assertJsonFragment(['id' => 1]);

        $this->assertDatabaseMissing('debit_cards', [
            'id' => $card->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $response = $this->putJson("/api/debit-cards/{$card->id}");
        $response
            ->assertUnprocessable()
            ->assertJsonFragment(['is_active' => ['The is active field is required.']]);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);
        $this->assertNotSoftDeleted($card);

        $response = $this->deleteJson("/api/debit-cards/{$card->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted($card);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $card = $this->user->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);
        $card->debitCardTransactions()->create([
            'amount' => 10000,
            'currency_code' => 'SGD'
        ]);

        $this->assertDatabaseCount('debit_card_transactions', 1);
        $response = $this->deleteJson("/api/debit-cards/{$card->id}");
        $response->assertForbidden();

        $this->assertNotSoftDeleted($card);
    }

    // Extra bonus for extra tests :)
    public function testCreateDebitCardReturnsErrorWithMissingData() {
        $response = $this->postJson('/api/debit-cards');
        $response
            ->assertUnprocessable()
            ->assertJsonFragment(['type' => ['The type field is required.']]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetailsBelongingToOtherUser() {
        $otherUser = User::factory()->create();
        $card = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $response = $this->getJson("/api/debit-cards/{$card->id}");
        $response
            ->assertForbidden()
            ->assertJsonFragment(["message" => "This action is unauthorized."]);
    }

    public function testCustomerCannotUpdateDebitCardDetailsBelongingToOtherUser() {
        $otherUser = User::factory()->create();
        $card = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => true]);
        $response
            ->assertForbidden()
            ->assertJsonFragment(["message" => "This action is unauthorized."]);
    }

    public function testCustomerCannotDeleteADebitCardBelongingToOtherUser()
    {
        $otherUser = User::factory()->create();
        $card = $otherUser->debitCards()->create([
            'type' => 'credit_card',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear()]);
        $this->assertNotSoftDeleted($card);

        $response = $this->deleteJson("/api/debit-cards/{$card->id}");
        $response
            ->assertForbidden()
            ->assertJsonFragment(["message" => "This action is unauthorized."]);

        $this->assertNotSoftDeleted($card);
    }
}
