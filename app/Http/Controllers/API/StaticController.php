<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\StaticData;

class StaticController extends BaseController
{
    /**
     * Get all static data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $staticData = StaticData::active()->get()->groupBy('type');
        
        return $this->sendResponse($staticData, 'تم جلب البيانات الثابتة بنجاح');
    }

    /**
     * Get static data by type
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByType(Request $request, $type)
    {
        $staticData = StaticData::active()->ofType($type)->get();
        
        if ($staticData->isEmpty()) {
            return $this->sendError('لم يتم العثور على بيانات من هذا النوع', [], 404);
        }
        
        return $this->sendResponse($staticData, 'تم جلب البيانات بنجاح');
    }

    /**
     * Get specific static data by code
     *
     * @param Request $request
     * @param string $type
     * @param string $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCode(Request $request, $type, $code)
    {
        $staticData = StaticData::active()->ofType($type)->withCode($code)->first();
        
        if (!$staticData) {
            return $this->sendError('لم يتم العثور على البيانات المطلوبة', [], 404);
        }
        
        return $this->sendResponse($staticData, 'تم جلب البيانات بنجاح');
    }

    /**
     * Get available types
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTypes(Request $request)
    {
        $types = StaticData::active()->distinct()->pluck('type');
        
        return $this->sendResponse($types, 'تم جلب أنواع البيانات المتاحة بنجاح');
    }
}
