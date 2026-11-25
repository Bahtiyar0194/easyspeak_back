<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserRole;
use App\Models\OperationRole;

class CheckRoles{
    /**
     * Проверяет, что у пользователя есть роль, которой разрешена операция маршрута.
     *
     * Номер операции задаётся в маршруте: ->middleware('check_roles:13').
     * Значение operation_type_id из запроса намеренно не используется — его присылает клиент
     * и может подставить любую операцию, разрешённую его роли.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  ...$operation_type_ids
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$operation_type_ids){

        if(empty($operation_type_ids)){
            return response()->json('Operation type is not configured for this route', 403);
        }

        $user = auth()->user();

        $role_type_ids = UserRole::where('user_id', $user->user_id)
        ->pluck('role_type_id');

        $role_found = OperationRole::whereIn('operation_type_id', $operation_type_ids)
        ->whereIn('role_type_id', $role_type_ids)
        ->exists();

        if($role_found === false){
            return response()->json('Role not found', 403);
        }

        return $next($request);
    }
}
