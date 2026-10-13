<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\AdminLog;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::with('prices')->orderBy('sort_order')->orderBy('months')->get());
    }

    public function store(StorePlanRequest $request): PlanResource
    {
        $plan = DB::transaction(function () use ($request) {
            $plan = Plan::create($request->safe()->except('prices'));
            $plan->prices()->createMany($request->validated('prices'));

            return $plan;
        });

        AdminLog::record('plan.create', $plan);

        return new PlanResource($plan->load('prices'));
    }

    /** Plans are never deleted (payments reference them); deactivate with `active: false`. */
    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        DB::transaction(function () use ($request, $plan) {
            $plan->update($request->safe()->except('prices'));
            $plan->prices()->delete();
            $plan->prices()->createMany($request->validated('prices'));
        });

        AdminLog::record('plan.update', $plan);

        return new PlanResource($plan->load('prices'));
    }
}
