<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        $loanDate = Carbon::create(2020, 01, 20);

        DB::beginTransaction();
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'processed_at' => $loanDate->toDateString(),
            'status' => Loan::STATUS_DUE
        ]);

        $pendingAmount = $amount;
        for ($i = 0; $i < $terms; $i++) {
            $repaymentAmount = intdiv($pendingAmount, ($terms - $i));
            $pendingAmount = $pendingAmount - $repaymentAmount;
            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $repaymentAmount,
                'outstanding_amount' => $repaymentAmount,
                'currency_code' => $currencyCode,
                'status' => ScheduledRepayment::STATUS_DUE,
                'due_date' => Carbon::create(2020, 01, 20)->addMonths($i + 1)->toDateString()
            ]);
        }
        DB::commit();

        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
    }
}
