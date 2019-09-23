<?php

namespace ProcessMaker\Http\Controllers\Process;

use Illuminate\Support\Facades\Auth;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Models\Script;
use ProcessMaker\Models\ScriptCategory;
use ProcessMaker\Models\User;

class ScriptController extends Controller
{
     /**
     * Get the list of environment variables
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View
     */
    public function index()
    {
        $catConfig = (object) [
            'labels' => (object) [
                'titleMenu' => __('Scripts'),
                'titleModal' => __('Create Script Category'),
                'countColumn' => __('# Scripts'),
            ],
            'routes' => (object) [
                'routeMenu' => 'scripts.index',
                'route' => 'script_categories',
                'location' => '/designer/scripts/categories',
            ],
            'countField' => 'scripts_count',
            'apiListInclude' => 'scriptsCount',
            'permissions' => Auth::user()->hasPermissionsFor('categories')
        ];

        $listConfig = (object) [
            'scriptFormats' => Script::scriptFormatList(),
            'countCategories' => ScriptCategory::where(['status' => 'ACTIVE', 'is_system' => false])->count()
        ];

        return view('processes.scripts.index', compact ('listConfig', 'catConfig'));

//        $scriptFormats = Script::scriptFormatList();
//        $countCategories = ScriptCategory::where(['status' => 'ACTIVE', 'is_system' => false])->count();
//        $titleMenu = __('Scripts');
//        $routeMenu = 'scripts.index';
//        $titleModal = __('Create Category');
//        $permissions = Auth::user()->hasPermissionsFor('categories');
//        $route = 'script_categories';
//        $location = '/designer/scripts/categories';
//        $include = 'scriptsCount';
//        $labelCount = __('# Scripts');
//        $count = 'scripts_count';
//        $showCategoriesTab = 'script-categories.index' === \Request::route()->getName() || $countCategories === 0 ? true : false;
//
//        return view('processes.scripts.index', compact('scriptFormats', 'countCategories', 'titleMenu', 'routeMenu',
//            'permissions', 'titleModal', 'route', 'location', 'include', 'labelCount', 'count', 'showCategoriesTab'));
    }

    public function edit(Script $script, User $users)
    {
        $selectedUser = $script->runAsUser;
        $scriptFormats = Script::scriptFormatList();

        return view('processes.scripts.edit', compact('script', 'selectedUser', 'scriptFormats'));
    }

    public function builder(Script $script)
    {
        $scriptFormat = $script->language_name;

        return view('processes.scripts.builder', compact('script', 'scriptFormat'));
    }
}
