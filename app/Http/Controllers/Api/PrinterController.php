<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PrinterResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Log;

class PrinterController extends Controller
{
    public function index(Request $request)
    {
        $printers = Post::printer()
            ->with(['translations', 'propertyValues.property'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->paginate($request->query('per_page', 15));

        Log::info($printers);

        return PrinterResource::collection($printers);
    }

    public function select()
    {
        $printers = Post::printer()
            ->with('translations')
            ->where('status', 'published')
            ->orderBy('title')
            ->get()
            ->map(function ($printer) {
                return [
                    'id' => $printer->id,
                    'name' => $printer->title,
                    'slug' => $printer->slug,
                ];
            });

        return response()->json(['data' => $printers]);
    }

    public function show(int $id)
    {
        $printer = Post::printer()->with(['translations', 'propertyValues.property'])->findOrFail($id);

        return new PrinterResource($printer);
    }

    public function showBySlug(string $slug)
    {
        $printer = Post::printer()->with(['translations', 'propertyValues.property'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new PrinterResource($printer);
    }

    public function searchPrinter(Request $request)
    {
        $query = $request->input('query');

        if (empty($query)) {
            $printers = Post::printer()->with(['translations', 'propertyValues.property'])->limit(5)->get();

            return PrinterResource::collection($printers);
        }

        $printers = Post::printer()
            ->with(['translations', 'propertyValues.property'])
            ->where('title', 'like', '%'.$query.'%')
            ->get();

        return PrinterResource::collection($printers);
    }
}
