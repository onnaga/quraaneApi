<?php

namespace App\Http\Controllers;

use App\Models\ActivityCategory;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;

class ActivityCategoryController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        // Public endpoint, getting all active categories
        $categories = ActivityCategory::where('is_active', true)->get();
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        // Must be auth:api and privilege == 4 (App Admin)
        if (auth()->user()->privilege != 4) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|unique:activity_categories,type',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = ActivityCategory::create($request->only(['name', 'type', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'category' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->privilege != 4) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $category = ActivityCategory::find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|unique:activity_categories,type,' . $id,
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    public function destroy($id)
    {
        if (auth()->user()->privilege != 4) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $category = ActivityCategory::find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
