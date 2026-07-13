<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\V1\StaffController;
use Modules\Staff\Infrastructure\Models\StaffProfile;
Route::middleware(['auth:sanctum','account.not-locked','organization.context'])->prefix('staff')->group(function (): void {
 Route::get('/',[StaffController::class,'index'])->middleware('permission:staff.view'); Route::post('/',[StaffController::class,'store'])->middleware('permission:staff.create'); Route::patch('/{staff}',[StaffController::class,'update'])->middleware('permission:staff.update');
 foreach(['activate','suspend','archive','restore'] as $status) Route::post('/{staff}/'.$status,fn(StaffController $c,\Illuminate\Http\Request $r,StaffProfile $staff)=>$c->status($r,$staff,$status))->middleware('permission:staff.manage_employment');
});
