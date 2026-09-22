<?php
// app/Http/Controllers/ReferralController.php
namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {
    }

    /**
     * POST /api/referrals/attach
     */
    public function attach(Request $request): JsonResponse
    {
        /** @var Master $master */
        $master = $request->attributes->get('master');
        $code   = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['error' => 'Code is required'], 422);
        }

        $referral = $this->referralService->registerReferral($master, $code);

        if ($referral === null) {
            return response()->json([
                'error' => 'Invalid code or self-referral not allowed'
            ], 422);
        }

        return response()->json([
            'data' => [
                'id'         => $referral->id,
                'status'     => $referral->status,
                'created_at' => $referral->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/referrals/my
     */
    public function my(Request $request): JsonResponse
    {
        /** @var Master $master */
        $master = $request->attributes->get('master');

        $items = $master->referrals()
            ->with('referredMaster')
            ->get()
            ->map(function (Referral $referral) {
                $earned = (int) ReferralEarning::where('referral_id', $referral->id)->sum('amount');

                return [
                    'name'       => $referral->referredMaster->name,
                    'date'       => $referral->created_at->toIso8601String(),
                    'is_counted' => $referral->status === Referral::STATUS_REWARDED,
                    'earned'     => $earned,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/referrals/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var Master $master */
        $master = $request->attributes->get('master');

        $total   = (int) $master->referralEarnings()->sum('amount');
        $pending = (int) $master->referralEarnings()
            ->where('status', ReferralEarning::STATUS_PENDING)
            ->sum('amount');
        $paid    = (int) $master->referralEarnings()
            ->where('status', ReferralEarning::STATUS_PAID)
            ->sum('amount');
        $counted = $master->referrals()
            ->where('status', Referral::STATUS_REWARDED)
            ->count();

        return response()->json([
            'data' => [
                'total'             => $total,
                'pending'           => $pending,
                'paid'              => $paid,
                'counted_referrals' => $counted,
            ],
        ]);
    }
}