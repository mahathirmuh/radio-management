<?php

namespace Modules\ApprovalManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Organization\Company;
use App\Models\UserManagement\Menu;
use App\Models\UserManagement\Permission;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\RoleUser;
use App\Models\Organization\Site;
use App\Models\UserManagement\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class ApprovalManagementController extends Controller
{
    protected $pageTitle;
    protected $menuItems;

    public function __construct()
    {
        $this->pageTitle = "ApprovalManagement";
        $this->menuItems = [
            ['url' => '/', 'label' => 'Approval Management', 'active' => false],
            ['url' => route('approval-management.approval-flows.index'), 'label' => 'Flows', 'active' => true]
        ];
    }


    /**
    * Show the approval flows page.
    *
    * @return \Illuminate\Contracts\Support\Renderable
    */
    public function indexApprovalFlows()
    {
        try {
            $users = User::all();
            $roles = Role::all();
            return view('ApprovalManagement::approval-flows', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems,
                'users' => $users,
                'roles' => $roles
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
    * Show the approval inbox (items waiting on the current user).
    *
    * @return \Illuminate\Contracts\Support\Renderable
    */
    public function indexInbox()
    {
        return view('ApprovalManagement::approval-inbox', [
            'pageTitle' => $this->pageTitle,
            'menuItems' => [
                ['url' => '/', 'label' => 'Approval Management', 'active' => false],
                ['url' => route('approval-management.approval-inbox.index'), 'label' => 'Inbox', 'active' => true],
            ],
            'users' => User::all(),
            'roles' => Role::all(),
        ]);
    }

    /**
    * Show the approval steps page.
    *
    * @return \Illuminate\Contracts\Support\Renderable
    */
    public function indexApprovalSteps()
    {
        try {
            $data = []; // Fetch or process data for approval steps
            return view('ApprovalManagement::approval-steps', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
    * Show the approval delegate page.
    *
    * @return \Illuminate\Contracts\Support\Renderable
    */
    public function indexApprovalDelegate()
    {
        try {
            $data = []; // Fetch or process data for approval delegate
            return view('ApprovalManagement::approval-delegate', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
    * Show the approval takeover page.
    *
    * @return \Illuminate\Contracts\Support\Renderable
    */
    public function indexApprovalTakeover()
    {
        try {
            $data = []; // Fetch or process data for approval takeover
            return view('ApprovalManagement::approval-takeover', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }
}