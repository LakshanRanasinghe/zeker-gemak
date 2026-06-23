<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PrinterResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoritePrinterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()->favoritePrinters()
            ->latest()
            ->get();

        $printers = Post::printer()
            ->with(['translations', 'meta'])
            ->whereIn('id', $favorites->pluck('printer_id'))
            ->get();

        return PrinterResource::collection($printers);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        Post::printer()->findOrFail($id);

        $request->user()->favoritePrinters()->firstOrCreate([
            'printer_id' => $id,
        ]);

        return response()->json(['message' => 'Printer added to favorites.'], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->favoritePrinters()
            ->where('printer_id', $id)
            ->delete();

        return response()->json(['message' => 'Printer removed from favorites.']);
    }

    public function check(Request $request, int $id): JsonResponse
    {
        $exists = $request->user()->favoritePrinters()
            ->where('printer_id', $id)
            ->exists();

        return response()->json(['is_favorite' => $exists]);
    }
}
