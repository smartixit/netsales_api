<?php
namespace App\Interfaces;
use App\Models\{BillingPlan,BillingPlanVersion,BillingPrice,BillingPlanFeature,BillingProduct};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
interface BillingCatalogRepositoryInterface {
 /** @param array<string, mixed> $data */
 public function createProduct(array $data): BillingProduct;
 /** @param array<string, mixed> $data */
 public function createPlan(array $data): BillingPlan;
 public function findPlan(int $planId,bool $lock=false): ?BillingPlan;
 /** @param array<string, mixed> $filters @return Collection<int, BillingPlan>|CursorPaginator<int, BillingPlan> */
 public function getPlans(array $filters=[]): Collection|CursorPaginator;
 public function createDraftVersion(int $planId,int $actorId): BillingPlanVersion;
 public function findVersion(int $versionId,bool $lock=false): ?BillingPlanVersion;
 /** @param array<string, mixed> $data */
 public function createPrice(array $data): BillingPrice;
 /** @param array<string, mixed> $data */
 public function createFeature(array $data): BillingPlanFeature;
 public function publishVersion(BillingPlanVersion $version,int $actorId): BillingPlanVersion;
 public function setCurrentVersion(BillingPlan $plan,int $versionId): void;
}