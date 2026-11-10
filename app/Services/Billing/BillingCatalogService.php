<?php
namespace App\Services\Billing;
use App\Enums\{BillingFeatureType,BillingInterval,BillingPlanVersionStatus};
use App\Interfaces\{BillingCatalogRepositoryInterface,CurrencyRepositoryInterface};
use App\Models\{BillingPlan,BillingPlanVersion,BillingPrice,BillingPlanFeature,BillingProduct};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BillingCatalogService {
 public function __construct(private BillingCatalogRepositoryInterface $catalog,private CurrencyRepositoryInterface $currencies) {}
 public function createProduct(array $data,int $actorId): BillingProduct { return DB::transaction(fn()=> $this->catalog->createProduct([...$data,'created_by'=>$actorId])); }
 public function createPlan(array $data,int $actorId): BillingPlan {
  return DB::transaction(function()use($data,$actorId){
   $product=BillingProduct::query()->whereKey($data['product_id'])->where('is_active',true)->first();
   if(!$product){throw ValidationException::withMessages(['product_id'=>['المنتج غير موجود أو غير مفعل.']]);}
   return $this->catalog->createPlan([...$data,'created_by'=>$actorId]);
  });
 }
 public function plans(array $filters=[]) { return $this->catalog->getPlans($filters); }
 public function createDraftVersion(int $planId,int $actorId): BillingPlanVersion {
  return DB::transaction(function()use($planId,$actorId){
   $plan=$this->catalog->findPlan($planId,true);
   if(!$plan || !$plan->is_active){throw ValidationException::withMessages(['plan_id'=>['الخطة غير موجودة أو غير مفعلة.']]);}
   return $this->catalog->createDraftVersion($planId,$actorId);
  });
 }
 public function addPrice(int $versionId,array $data): BillingPrice {
  return DB::transaction(function()use($versionId,$data){
   $version=$this->catalog->findVersion($versionId,true);
   if(!$version || $version->status!==BillingPlanVersionStatus::DRAFT){throw ValidationException::withMessages(['plan_version_id'=>['لا يمكن تعديل أسعار نسخة منشورة أو غير موجودة.']]);}
   $currency=$this->currencies->findActiveById((int)$data['currency_id']);
   if(!$currency){throw ValidationException::withMessages(['currency_id'=>['العملة غير موجودة أو غير مفعلة.']]);}
   $interval=BillingInterval::tryFrom((string)$data['billing_interval']);
   if(!$interval){throw ValidationException::withMessages(['billing_interval'=>['فترة الفوترة غير معروفة.']]);}
   $unit=Money::fromDecimal((string)$data['unit_amount'],$currency->decimal_places,$currency->code);
   $setup=Money::fromDecimal((string)($data['setup_fee']??'0'),$currency->decimal_places,$currency->code);
   if($unit->amountMinor<0 || $setup->amountMinor<0){throw ValidationException::withMessages(['unit_amount'=>['لا يسمح بالسعر أو رسوم التفعيل السالبة.']]);}
   return $this->catalog->createPrice([
    'plan_version_id'=>$versionId,'currency_id'=>$currency->currency_id,
    'billing_interval'=>$interval,'interval_count'=>(int)($data['interval_count']??1),
    'unit_amount_minor'=>$unit->amountMinor,'setup_fee_minor'=>$setup->amountMinor,
    'tax_behavior'=>$data['tax_behavior']??'EXCLUSIVE','is_active'=>true,
   ]);
  });
 }
 public function addFeature(int $versionId,array $data): BillingPlanFeature {
  return DB::transaction(function()use($versionId,$data){
   $version=$this->catalog->findVersion($versionId,true);
   if(!$version || $version->status!==BillingPlanVersionStatus::DRAFT){throw ValidationException::withMessages(['plan_version_id'=>['لا يمكن تعديل ميزات نسخة منشورة أو غير موجودة.']]);}
   $type=BillingFeatureType::tryFrom((string)$data['feature_type']);
   if(!$type){throw ValidationException::withMessages(['feature_type'=>['نوع الميزة غير مدعوم.']]);}
   if($type===BillingFeatureType::BOOLEAN && !in_array($data['value']??null,['true','false',true,false,1,0,'1','0'],true)){throw ValidationException::withMessages(['value'=>['قيمة الميزة المنطقية يجب أن تكون true أو false.']]);}
   return $this->catalog->createFeature([...$data,'plan_version_id'=>$versionId]);
  });
 }
 public function publishVersion(int $versionId,int $actorId): BillingPlanVersion {
  return DB::transaction(function()use($versionId,$actorId){
   $version=$this->catalog->findVersion($versionId,true);
   if(!$version || $version->status!==BillingPlanVersionStatus::DRAFT){throw ValidationException::withMessages(['plan_version_id'=>['لا يمكن نشر هذه النسخة.']]);}
   if($version->prices->isEmpty()){throw ValidationException::withMessages(['prices'=>['يجب إضافة سعر واحد على الأقل بعملة وفترة فوترة محددتين قبل النشر.']]);}
   $seen=[];
   foreach($version->prices as $price){$key=$price->currency_id.':'.$price->billing_interval->value.':'.$price->interval_count;if(isset($seen[$key])){throw ValidationException::withMessages(['prices'=>['يوجد سعر مكرر لنفس العملة وفترة الفوترة.']]);}$seen[$key]=true;}
   $published=$this->catalog->publishVersion($version,$actorId);
   $plan=$this->catalog->findPlan((int)$version->plan_id,true);
   if(!$plan){throw ValidationException::withMessages(['plan_id'=>['الخطة الأصلية غير موجودة.']]);}
   $this->catalog->setCurrentVersion($plan,$published->plan_version_id);
   return $published;
  });
 }
}