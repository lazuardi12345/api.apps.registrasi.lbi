<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fees\ResolveCourseFeeAction;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCourseFeeController extends BaseApiController
{
    public function show(Request $request, ResolveCourseFeeAction $resolver): JsonResponse
    {
        $filters = $request->validate([
            'fee_type' => ['required', 'in:registration,tuition'],
            'division' => ['nullable', 'string'],
            'course_catalog_id' => ['nullable', 'integer'],
            'course_level_id' => ['nullable', 'integer'],
            'course_schedule_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
        ]);

        $rule = $resolver->execute($filters);
        if (! $rule) {
            return $this->sendSuccess(null, 'Tarif aktif belum dikonfigurasi.');
        }

        return $this->sendSuccess([
            'id' => $rule->id,
            'name' => $rule->name,
            'fee_type' => $rule->fee_type,
            'amount' => (float) $rule->amount,
        ], 'Tarif aktif berhasil dimuat.');
    }
}