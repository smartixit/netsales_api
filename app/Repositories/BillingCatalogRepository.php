<?php
namespace App\Repositories;
use App\Enums\BillingPlanVersionStatus;
use App\Interfaces\BillingCatalogRepositoryInterface;
use App\Models\{BillingPlan,BillingPlanVersion,BillingPrice,BillingPlanFeature,BillingProduct};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
class BillingCatalogRepository implements BillingCatalogRepositoryInterface {
 public function createProduct(array $data): BillingProduct { return BillingProduct::query()->create($data); }
 public function createPlan(array $data): BillingPlan { return BillingPlan::query()->create($data); }
 public function findPlan(int $planId,bool $lock=false): ?BillingPlan {
  $q=BillingPlan::query()->with(['product','currentVersion.prices.currency','currentVersion.features']);
  if($lock){$q->lockForUpdate();}
  return $q->find($planId);
 }
 public function getPlans(array $filters=[]): Collection|CursorPaginator {
  $q=BillingPlan::query()->with(['product','currentVersion.prices.currency','currentVersion.features']);
  if(array_key_exists('is_active',$filters)){$q->where('is_active',(bool)$filters['is_active']);}
  if(!empty($filters['search'])){$term=trim((string)$filters['search']);$q->where(fn(Builder $b)=>$b->where('name','like',"%{$term}%")->orWhere('plan_key','like',"%{$term}%"));}
  $perPage=min(max((int)($filters['per_page']??15),1),50);
  if(!empty($filters['all'])){return $q->orderByDesc('plan_id')->get();}
  return $q->orderByDesc('plan_id')->cursorPaginate($perPage);
 }
 public function createDraftVersion(int $planId,int $actorId): BillingPlanVersion {
  $next=(int)BillingPlanVersion::query()->where('plan_id',$planId)->max('version_number')+1;
  return BillingPlanVersion::query()->create(['plan_id'=>$planId,'version_number'=>$next,'status'=>BillingPlanVersionStatus::DRAFT,'created_by'=>$actorId]);
 }
 public function findVersion(int $versionId,bool $lock=false): ?BillingPlanVersion {
  $q=BillingPlanVersion::query()->with(['plan','prices.currency','features']);
  if($lock){$q->lockForUpdate();}
  return $q->find($versionId);
 }
 public function createPrice(array $data): BillingPrice { return BillingPrice::query()->create($data); }
 public function createFeature(array $data): BillingPlanFeature { return BillingPlanFeature::query()->create($data); }
 public function publishVersion(BillingPlanVersion $version,int $actorId): BillingPlanVersion {
  $version->status=BillingPlanVersionStatus::PUBLISHED;
  $version->published_at=now('Asia/Aden');
  $version->created_by=$version->created_by??$actorId;
  $version->save();
  return $version->refresh()->load(['prices.currency','features']);
 }
 public function setCurrentVersion(BillingPlan $plan,int $versionId): void { $plan->current_version_id=$versionId; $plan->save(); }
}