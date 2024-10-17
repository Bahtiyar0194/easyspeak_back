<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserRole;
use App\Models\OperationRole;

class CheckRoles{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next){

        $user = auth()->user();

        $operation_type_id = $request->operation_type_id;

        if(isset($operation_type_id)){
            $role_found = false;

            $userRoles = UserRole::where('user_id', $user->user_id)->get();

            foreach ($userRoles as $role) {
                $findOperationRole = OperationRole::where('operation_type_id', $operation_type_id)
                ->where('role_type_id', $role->role_type_id)
                ->first();

                if(isset($findOperationRole)){
                    $role_found = true;
                    break;
                }
            }

            if($role_found === false){
                return response()->json('Role not found', 403);
            }

            return $next($request);
        }
        else{
            return response()->json('Operation type id required', 403);
        }
    }
}