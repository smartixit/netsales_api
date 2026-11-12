<?php
namespace App\Services\Reports\Providers;
use App\Enums\OperationalReportType;
use App\Models\NetworkWalletLog;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
final class NetworkWalletReportProvider extends AbstractOperationalReportProvider {
 public function type(): OperationalReportType{return OperationalReportType::NETWORK_WALLET;}
 protected function label():string{return 'كشف حساب محفظة الشبكة';}
 protected function description():string{return 'حركات الإيداع والسحب المسجلة على محفظة الشبكة.';}
 protected function viewPermission():string{return 'network_wallets.report';}
 protected function exportPermission():string{return 'network_wallets.export';}
 protected function scopes():array{return ['network'];}
 protected function posSelection():string{return 'forbidden';}
 protected function statusChoices():array{return ['DEPOSIT','WITHDRAW'];}
 protected function columns():array{return [['id'=>'log_id','label'=>'رقم الحركة','data_type'=>'integer'],['id'=>'log_date','label'=>'التاريخ','data_type'=>'date'],['id'=>'log_operation','label'=>'نوع الحركة','data_type'=>'text'],['id'=>'log_amount','label'=>'المبلغ','data_type'=>'decimal'],['id'=>'log_statement','label'=>'البيان','data_type'=>'text'],['id'=>'created_by_name','label'=>'نفذها','data_type'=>'text']];}
 protected function metricSlots():array{return [['id'=>'record_count','label'=>'عدد الحركات','data_type'=>'integer'],['id'=>'deposits','label'=>'إجمالي الإيداعات','data_type'=>'decimal'],['id'=>'withdrawals','label'=>'إجمالي السحوبات','data_type'=>'decimal']];}
 protected function summaryCharts():array{return [['id'=>'wallet_movements','label'=>'حركة المحفظة','chart_type'=>'bar','value_key'=>'amount','label_key'=>'operation']];}
 private function query(int $networkId,array $filters):Builder{$pattern=$this->pattern($filters);$q=NetworkWalletLog::query()->with(['wallet','createdBy'])->whereHas('wallet',fn(Builder $w):Builder=>$w->where('network_id',$networkId))->when($filters['status']??null,fn(Builder $q,string $v):Builder=>$q->where('log_operation',$v))->when($pattern!==null,fn(Builder $q):Builder=>$q->where('log_statement','like',$pattern));return $this->applyDates($q,'log_date',$filters);}
 public function build(User $user,int $networkId,?int $posId,array $filters):CursorPaginator{return $this->paginate($this->query($networkId,$filters),$filters,'log_date','log_id');}
 public function summary(User $user,int $networkId,?int $posId,array $filters):array{$q=$this->query($networkId,$filters);$groups=(clone $q)->reorder()->selectRaw('log_operation as operation, SUM(log_amount) as amount')->groupBy('log_operation')->get();return ['record_count'=>(clone $q)->count(),'deposits'=>$this->amountValue((clone $q)->where('log_operation','DEPOSIT')->sum('log_amount')),'withdrawals'=>$this->amountValue((clone $q)->where('log_operation','WITHDRAW')->sum('log_amount')),'charts'=>['wallet_movements'=>$groups->map(fn($r)=>['operation'=>$r->operation,'amount'=>$this->amountValue($r->amount)])->all()]];}
 public function exportColumns():array{return ['log_id'=>'رقم الحركة','log_date'=>'التاريخ','log_operation'=>'نوع الحركة','log_amount'=>'المبلغ','log_statement'=>'البيان','wallet_uuid'=>'مرجع المحفظة','created_by_name'=>'نفذها'];}
 public function serializeExportRow(mixed $r):array{return ['log_id'=>$r->log_id,'log_date'=>$this->dateValue($r->log_date),'log_operation'=>$this->enumValue($r->log_operation),'log_amount'=>$this->amountValue($r->log_amount),'log_statement'=>$r->log_statement,'wallet_uuid'=>$r->wallet?->wallet_uuid,'created_by_name'=>$r->createdBy?->user_full_name];}
}