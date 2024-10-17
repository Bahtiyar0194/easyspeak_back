<?php

namespace App\Http\Controllers;

use App\Models\OperationType;
use App\Models\Language;
use App\Models\UserOperation;
use App\Models\User;

use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_operation_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $operation_types = OperationType::leftJoin('types_of_operations_lang', 'types_of_operations_lang.operation_type_id', '=', 'types_of_operations.operation_type_id')
            ->select(
                'types_of_operations.operation_type_id',
                'types_of_operations_lang.operation_type_name'
            )
            ->where('types_of_operations_lang.lang_id', '=', $language->lang_id)
            ->orderBy('types_of_operations_lang.operation_type_id', 'asc')
            ->get();

        $attributes = new \stdClass();

        $attributes->operation_types = $operation_types;

        return response()->json($attributes, 200);
    }

    public function get_operations(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $per_page = $request->per_page ? $request->per_page : 10;

        $operations = UserOperation::leftJoin('types_of_operations', 'types_of_operations.operation_type_id', '=', 'user_operations.operation_type_id')
            ->leftJoin('types_of_operations_lang', 'types_of_operations_lang.operation_type_id', '=', 'types_of_operations.operation_type_id')
            ->leftJoin('users as operator', 'user_operations.operator_id', '=', 'operator.user_id')
            ->leftJoin('schools', 'schools.school_id', '=', 'operator.school_id')
            ->select(
                'user_operations.user_operation_id',
                'user_operations.operation_type_id',
                'types_of_operations_lang.operation_type_name',
                'user_operations.created_at',
                'operator.first_name as operator_first_name',
                'operator.last_name as operator_last_name',
                'operator.avatar as operator_avatar'
            )
            ->where('operator.school_id', '=', auth()->user()->school_id)
            ->where('types_of_operations_lang.lang_id', '=', $language->lang_id)
            ->orderBy('user_operations.created_at', 'desc');

        $operation_type_id = $request->operation_type_id;
        $operator = $request->operator;
        $description = $request->description;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;

        // Фильтрация по ФИО оператора
        if (!empty($operator)) {
            $operations->whereRaw("CONCAT(operator.last_name, ' ', operator.first_name) LIKE ?", ['%' . $operator . '%']);
        }

        // Фильтрация по типу операции
        if (!empty($operation_type_id)) {
            $operations->where('user_operations.operation_type_id', '=', $operation_type_id);
        }

        if (!empty($description)) {
            $operations->where('user_operations', 'LIKE', '%' . $description . '%');
        }

        if ($created_at_from && $created_at_to) {
            $operations->whereBetween('user_operations.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:00']);
        }

        if ($created_at_from) {
            $operations->where('user_operations.created_at', '>=', $created_at_from . ' 00:00:00');
        }

        if ($created_at_to) {
            $operations->where('user_operations.created_at', '<=', $created_at_to . ' 23:59:00');
        }

        return response()->json($operations->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_operation(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $operation = UserOperation::leftJoin('types_of_operations', 'types_of_operations.operation_type_id', '=', 'user_operations.operation_type_id')
            ->leftJoin('types_of_operations_lang', 'types_of_operations_lang.operation_type_id', '=', 'types_of_operations.operation_type_id')
            ->leftJoin('users as operator', 'user_operations.operator_id', '=', 'operator.user_id')
            ->leftJoin('schools', 'schools.school_id', '=', 'operator.school_id')
            ->select(
                'user_operations.description',
                'types_of_operations_lang.operation_type_name',
                'user_operations.created_at',
                'operator.first_name as operator_first_name',
                'operator.last_name as operator_last_name',
                'operator.avatar as operator_avatar'
            )
            ->where('user_operations.user_operation_id', '=', $request->user_operation_id)
            ->where('operator.school_id', '=', auth()->user()->school_id)
            ->where('types_of_operations_lang.lang_id', '=', $language->lang_id)
            ->first();

        return response()->json($operation, 200);
    }
}
