<?php
namespace App\Interfaces;
use App\Models\{BillingPlan,BillingPlanVersion,BillingPrice,BillingPlanFeature,BillingProduct};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
interface BillingCatalogRepositoryInterface {
 public function createProduct(array $data): BillingProduct;
 public function createPlan(array $data): BillingPlan;
 public function findPlan(int $planId,bool $lock=false): ?BillingPlan;
 public function getPlans(array $filters=[]): Collection|CursorPaginator;
 public function createDraftVersion(int $planId,int $actorId): BillingPlanVersion;
 public function findVersion(int $versionId,bool $lock=false): ?BillingPlanVersion;
 public function createPrice(array $data): BillingPrice;
 public function createFeature(array $data): BillingPlanFeature;
 public function publishVersion(BillingPlanVersion $version,int $actorId): BillingPlanVersion;
 public function setCurrentVersion(BillingPlan $plan,int $versionId): void;
}