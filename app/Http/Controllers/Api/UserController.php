<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{

    public function __construct(private UserService $service)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = auth()->user();
            $data = $this->service->getAll($user);
            return ApiMessage::paginated('Success get all users', $data);
        } catch (Throwable $th) {
            Log::error('UserController@method: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $user = auth()->user();
            $data = $this->service->create($request, $user);
            return ApiMessage::success('Success create user', $data, 201);
        } catch (Throwable $th) {
            Log::error('UserController@method: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        try {
             $this->service->authorizeAccess(auth()->user(), $user);
            return ApiMessage::success('Success get user', $user, 201);
        } catch (Throwable $th) {
            Log::error('UserController@method: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $user);
            $data = $this->service->update($request, $user);
            return ApiMessage::success('Success update user', $data, 201);
        } catch (Throwable $th) {
            Log::error('UserController@method: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $user);
            $this->service->delete($user);
            return ApiMessage::success('Success deleted user', null, 201);
        } catch (Throwable $th) {
            Log::error('UserController@method: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }
}
