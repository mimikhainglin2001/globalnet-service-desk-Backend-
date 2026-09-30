<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->with('team')->orderBy('name')->paginate(50)
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category->load('team')))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->load('team'));
    }

    /**
     * Categories referenced by tickets are kept for history; deactivate them instead.
     */
    public function destroy(Category $category): Response
    {
        if ($category->tickets()->exists()) {
            throw new ConflictHttpException('This category is used by tickets. Deactivate it instead of deleting it.');
        }

        $category->delete();

        return response()->noContent();
    }
}
