<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiQueryFilter;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Models\WhitelistedUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class WhitelistController extends Controller
{
    const PAGINATION_LIMIT = 10;

    const MODULE_NAME = 'birthdate ban whitelist';

    /**
     * Permissions / Gates
     */
    const CAN_ACCESS = 'access ' . self::MODULE_NAME;
    const CAN_CREATE = 'create ' . self::MODULE_NAME;
    const CAN_EDIT = 'edit ' . self::MODULE_NAME;
    const CAN_DELETE = 'delete ' . self::MODULE_NAME;
    const CAN_SAVE = 'save ' . self::MODULE_NAME;
    const CAN_UPDATE = 'update ' . self::MODULE_NAME;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        abort_if(!Auth::user()->can(self::CAN_ACCESS) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');
        
        $model = new WhitelistedUsers();
        $helper = new ApiQueryFilter($model, $request);

        if($request->pagination) {

            $q = $helper->searchAndSort(array(
                'id', 'user.first_name', 'user.last_name', 'remarks'
            ));

            return $q->with(['user'])->where('status', 1)->paginate(self::PAGINATION_LIMIT)->withQueryString();

        } else {
            return response()->json([
                'error' => false, 
                'message' => 'Whitelisted User List', 
                'result' => WhitelistedUsers::all()
            ]);
        }
    }

    public function view()
    {
        abort_if(!Auth::user()->can(self::CAN_ACCESS) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');

        return view('admin.modules.whitelisted_users.list')->with([
            'userType' => $this->getUserType(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_if(!Auth::user()->can(self::CAN_CREATE) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');

        $users = User::where('is_status', 0)->role('Client')->orderBy('username', 'asc')->get();

        return view('admin.modules.whitelisted_users.add')->with([
            'userType' => $this->getUserType(),
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_if(!Auth::user()->can(self::CAN_SAVE) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');

        $request->validate([
            'user_id' => 'required',
            'remarks' => 'nullable|string',
        ]);

        $request->request->add([
            'status' => 1,
        ]);

        $store = WhitelistedUsers::updateOrCreate([
            'user_id' => $request->user_id
        ], $request->all());

        if(!$store) {
            return $request->wantsJson()
                ? new JsonResponse([
                    'error' => false,
                    'message' => 'Failure to add'
                ], 500)
                : redirect()->back()->with('error', 'Failure to add');
        }

        return $request->wantsJson()
                ? new JsonResponse([
                    'error' => false,
                    'message' => 'Added successfully'
                ], 201)
                : redirect()->back()->with('success', 'Added successfully');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($t, $package)
    {
        abort_if(!Auth::user()->can(self::CAN_EDIT) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');

        return view('admin.modules.packages.edit_package')->with([
            'userType' => $this->getUserType(),
            'row' => (object) Package::where('id',$package)->first()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $t,  $id)
    {
        abort_if(!Auth::user()->can(self::CAN_UPDATE) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');

        if($id) {

            $request->validate([
                'name' => 'required|string|unique:packages,name,' . $id,
                'description' => 'required|string',
                'rate' => 'required|numeric',
                'max_questions' => 'required|numeric',
                'sequence' => 'nullable',
                'thumbnail_color' => 'present'
            ]);

            $data = $request->only(['name', 'description', 'rate', 'max_questions', 'sequence', 'thumbnail_color']);

            $update = Package::where('id', $id)->update($data);
            
            if(!$update) {
                return $request->wantsJson()
                    ? new JsonResponse([
                        'error' => false,
                        'message' => 'Failure to update'
                    ], 500)
                    : redirect()->back()->with('error', 'Failure to updae');
            }
    
            return $request->wantsJson()
                ? new JsonResponse([
                    'error' => false,
                    'message' => 'Updated successfully'
                ], 500)
                : redirect()->back()->with('success', 'Updated successfully');

        } 

        return $request->wantsJson()
                ? new JsonResponse([
                    'error' => false,
                    'message' => 'No id'
                ], 500)
                : redirect()->back()->with('error', 'No id found');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($t, $id)
    {
        abort_if(!Auth::user()->can(self::CAN_DELETE) && !Auth::user()->is_admin, Response::HTTP_FORBIDDEN, 'Request denied');
        
        if(!$id) {
            abort(500, "Missing id");
        }

        if(!WhitelistedUsers::where('id', $id)->delete() ) {
            return request()->wantsJson()
                ? new JsonResponse([
                    'error' => true,
                    'message' => 'No id'
                ], 500)
                : redirect()->back()->with('error', 'No id found');
        } else {
            return request()->wantsJson()
                ? new JsonResponse([
                    'error' => false,
                    'message' => 'Record deleted successfully'
                ], 200)
                : redirect()->back()->with('error', 'Record deleted successfully');
        }
    }

    public function getUserType()
    {
        return get_user_type()['userType'];
    }
}