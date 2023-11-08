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
        $repayments = $loan->scheduledRepayments;
        $dueRepayments = [];
        $paidAmount = 0;

        DB::beginTransaction();
        foreach ($repayments as $repayment) {
            if ($repayment->outstanding_amount == 0) {
                $repayment->outstanding_amount = $repayment->amount;
            }
            // If this repayment is already paid, move to the next.
            switch ($repayment->status) {
                case ScheduledRepayment::STATUS_REPAID:
                    $paidAmount += $repayment->amount;
                    break;
                case ScheduledRepayment::STATUS_DUE:
                    $dueRepayments[] = $repayment;
                    break;
            }
        }

        // If this is the last due repayment, loan needs to be mark as repaid.
        if (sizeof($dueRepayments) == 1) {
            $loan->outstanding_amount = 0;
            $loan->status = Loan::STATUS_REPAID;

            $repayment = $dueRepayments[0];
            $repayment->outstanding_amount = 0;
            $repayment->status = ScheduledRepayment::STATUS_REPAID;
            $repayment->save();
        } else {
            // Else, update loan's outstanding amount and repayments.
            $loan->outstanding_amount = ($loan->outstanding_amount ?? $loan->amount) - $amount;

            $currentRepayment = $dueRepayments[0];
            if ($amount > $currentRepayment->outstanding_amount) {
                $nextRepayment = $dueRepayments[1];
                $nextRepayment->status = ScheduledRepayment::STATUS_PARTIAL;
                $nextRepayment->outstanding_amount = $nextRepayment->amount - ($amount - $currentRepayment->outstanding_amount);
                $nextRepayment->save();
            }
            $currentRepayment->status = ScheduledRepayment::STATUS_REPAID;
            $currentRepayment->outstanding_amount = 0;
            $currentRepayment->save();
        }

        $loan->save();

        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt
        ]);
        DB::commit();

        return $receivedRepayment;
    }
}
