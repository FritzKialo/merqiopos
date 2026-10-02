<?php

namespace App\Services;

use App\Models\Business;
use App\Models\FloatAdjustment;
use App\Models\Sale;
use App\Models\User;

/**
 * Digital Float — per-cashier cash accountability within the business's
 * single open Shift (Merqio has one shift per business, not per-cashier,
 * so this is a live-computed breakdown keyed by [shift_id, user_id] rather
 * than a separate concurrent float-session table). Only CASH sales affect
 * float — M-Pesa/bank transfer are confirmed synchronously at checkout and
 * never sit physically with the cashier, so they never need "claiming" the
 * way a tableside/QR-based system would.
 *
 * Available Float = credit limit + refloats − cash sales + deposits
 * Amount Owed (settlement) = cash sales − deposits (refloats excluded —
 *   a refloat raises the ceiling, it isn't money actually collected)
 */
class FloatService
{
    public function effectiveCreditLimit(User $user, Business $business): float
    {
        $profile = $user->staffProfileFor($business->id);
        if ($profile && !is_null($profile->credit_limit)) {
            return (float) $profile->credit_limit;
        }
        return (float) ($business->default_credit_limit ?? 0);
    }

    public function cashSales(int $shiftId, int $userId): float
    {
        return (float) Sale::where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('payment_method', 'cash')
            ->where('sale_status', 'completed')
            ->sum('paid_amount');
    }

    public function deposits(int $shiftId, int $userId): float
    {
        return (float) FloatAdjustment::forShiftUser($shiftId, $userId)
            ->where('type', 'deposit')
            ->sum('amount');
    }

    public function refloats(int $shiftId, int $userId): float
    {
        return (float) FloatAdjustment::forShiftUser($shiftId, $userId)
            ->where('type', 'refloat')
            ->sum('amount');
    }

    public function availableFloat(User $user, Business $business, int $shiftId): float
    {
        $limit    = $this->effectiveCreditLimit($user, $business);
        $refloats = $this->refloats($shiftId, $user->id);
        $sales    = $this->cashSales($shiftId, $user->id);
        $deposits = $this->deposits($shiftId, $user->id);

        return $limit + $refloats - $sales + $deposits;
    }

    public function amountOwed(int $shiftId, int $userId): float
    {
        return $this->cashSales($shiftId, $userId) - $this->deposits($shiftId, $userId);
    }

    public function shortfall(int $shiftId, int $userId): float
    {
        return max(0, $this->amountOwed($shiftId, $userId));
    }

    /**
     * Full breakdown for one cashier in one shift — used by the shift-close
     * settlement table and the Cash Deposits screen.
     */
    public function breakdown(User $user, Business $business, int $shiftId): array
    {
        $limit    = $this->effectiveCreditLimit($user, $business);
        $sales    = $this->cashSales($shiftId, $user->id);
        $deposits = $this->deposits($shiftId, $user->id);
        $refloats = $this->refloats($shiftId, $user->id);
        $owed     = $sales - $deposits;

        return [
            'user'            => $user,
            'credit_limit'    => $limit,
            'cash_sales'      => $sales,
            'deposits'        => $deposits,
            'refloats'        => $refloats,
            'available_float' => $limit + $refloats - $sales + $deposits,
            'amount_owed'     => $owed,
            'shortfall'       => max(0, $owed),
        ];
    }
}
