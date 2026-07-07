<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Http\Requests\Api\V1\UpdateCompanyRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Company;
use App\Support\Audit\StateTransitionLogger;
use App\Support\MasterData\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Company::query()->withCount('products')->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        return CompanyResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->companyService->create($request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'company',
            entityId: $company->id,
            event: 'create',
            fromState: null,
            toState: $company->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Company created.',
            'company' => new CompanyResource($company),
        ], 201);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json(
            new CompanyResource($company->loadCount('products')),
        );
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $previousStatus = $company->status->value;

        $company = $this->companyService->update($company, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'company',
            entityId: $company->id,
            event: 'update',
            fromState: $previousStatus,
            toState: $company->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Company updated.',
            'company' => new CompanyResource($company),
        ]);
    }

    public function suspend(Request $request, Company $company): JsonResponse
    {
        $company = $this->companyService->suspend($company);

        $this->stateTransitionLogger->log(
            entityType: 'company',
            entityId: $company->id,
            event: 'suspend',
            fromState: CompanyStatus::Active->value,
            toState: CompanyStatus::Suspended->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Company suspended.',
            'company' => new CompanyResource($company),
        ]);
    }

    public function activate(Request $request, Company $company): JsonResponse
    {
        $previousStatus = $company->status->value;

        $company = $this->companyService->activate($company);

        $this->stateTransitionLogger->log(
            entityType: 'company',
            entityId: $company->id,
            event: 'activate',
            fromState: $previousStatus,
            toState: CompanyStatus::Active->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Company activated.',
            'company' => new CompanyResource($company),
        ]);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $previousStatus = $company->status->value;

        $company = $this->companyService->archive($company);

        $this->stateTransitionLogger->log(
            entityType: 'company',
            entityId: $company->id,
            event: 'archive',
            fromState: $previousStatus,
            toState: CompanyStatus::Archived->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Company archived.',
        ]);
    }
}
