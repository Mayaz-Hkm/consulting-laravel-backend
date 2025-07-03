<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expert;
use App\Models\Section;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function getCategoriesWithSections(): \Illuminate\Http\JsonResponse
    {
        $categories = Category::with('sections')->paginate(10);  // التصفح

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => 'No categories found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }


    public function showCategory($id): \Illuminate\Http\JsonResponse
    {
        $category = Category::with('sections')->find($id);

        if (!$category) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid Category ID'
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'All Sections in Category (' . $category->categoryName . ')',
            'data' => $category->sections
        ]);
    }

    public function searchExpertsByRating($categoryId, Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'nullable|numeric|min:0|max:5',
        ]);

        $rating = $request->input('rating', 0);

        // جلب التصنيف مع الأقسام والخبراء
        $category = Category::with(['sections.experts' => function ($query) use ($rating) {
            $query->where('rate', '>=', $rating);
        }])->find($categoryId);

        if (!$category) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid Category ID'
            ], 404);
        }

        // تجميع الخبراء وترتيبهم حسب التقييم
        $experts = $category->sections
            ->flatMap(fn($section) => $section->experts)
            ->sortByDesc('rate')
            ->values(); // إعادة تعيين المفاتيح

        return response()->json([
            'status' => 1,
            'message' => 'Experts filtered by rating',
            'data' => $experts
        ]);
    }

    // Fetch categories
    public function getCategories(): \Illuminate\Http\JsonResponse
    {
        $categories = Category::all(['id', 'CategoryName']);
        return response()->json($categories);
    }

    // Fetch Sections


    public function getSections($category_id): \Illuminate\Http\JsonResponse
    {
        // التأكد من أن الـ category_id موجود
        if (!$category_id) {
            return response()->json(['error' => 'Category ID is required'], 400);
        }

        // جلب الأقسام المرتبطة بالـ category_id
        $sections = Section::where('category_id', $category_id)
            ->get(['id', 'sectionName']);

        if ($sections->isEmpty()) {
            return response()->json(['message' => 'No sections found for this category.'], 404);
        }

        return response()->json($sections);
    }

}
