<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Core\AIGateway\Application\ProviderRegistry;
use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Core\Health\Application\HealthCheckManager;
use Core\Modules\Infrastructure\Models\ModuleRegistration;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\View\View;
use Modules\Organizations\Infrastructure\Models\Organization;

/** Read-only presentation layer over the platform's existing administration APIs. */
final class SuperAdminController
{
    public function dashboard(HealthCheckManager $health, ProviderRegistry $providers): View
    {
        $checks = $health->runAll();
        return view('super-admin.dashboard', ['stats'=>['organizations'=>Organization::count(),'active_organizations'=>Organization::where('status','active')->count(),'suspended_organizations'=>Organization::where('status','suspended')->count(),'users'=>User::count(),'today_logins'=>User::whereDate('last_login_at',today())->count(),'storage'=>Organization::sum('current_storage')],'health'=>$health->overallStatus($checks)->value,'providers'=>$providers->availableProviderNames(),'activity'=>AuditLog::latest()->limit(6)->get(),'organizations_list'=>Organization::latest()->limit(5)->get()]);
    }
    public function organizations(): View { return view('super-admin.organizations',['organizations'=>Organization::latest()->paginate(20)]); }
    public function wizard(): View { return view('super-admin.wizard'); }
    public function users(): View { return view('super-admin.table',['title'=>'Users','rows'=>User::latest()->paginate(20),'columns'=>['name','email','status','created_at']]); }
    public function roles(): View { return view('super-admin.table',['title'=>'Roles','rows'=>Role::withCount('permissions','users')->paginate(20),'columns'=>['name','description','is_system']]); }
    public function modules(): View { return view('super-admin.table',['title'=>'Installed modules','rows'=>ModuleRegistration::paginate(20),'columns'=>['display_name','version','status','description']]); }
    public function ai(ProviderRegistry $providers): View { return view('super-admin.ai',['providers'=>$providers->all()]); }
    public function audit(): View { return view('super-admin.table',['title'=>'Audit centre','rows'=>AuditLog::with('actor')->latest()->paginate(50),'columns'=>['action','actor_id','created_at']]); }
    public function health(HealthCheckManager $health): View { $checks=$health->runAll(); return view('super-admin.health',['checks'=>$checks,'overall'=>$health->overallStatus($checks)->value]); }
}
