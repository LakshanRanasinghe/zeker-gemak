<?php

namespace App\Http\Controllers;

use App\Models\WysiwygMedia;
use Illuminate\Http\Request;

class WysiwygUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ]);

        $entry = WysiwygMedia::create();

        $media = $entry
            ->addMediaFromRequest('file')
            ->toMediaCollection('wysiwyg');

        return response()->json([
            'url' => $media->getUrl(),
            'wysiwygMediaId' => $entry->id,
        ]);
    }

    public function destroy(WysiwygMedia $wysiwygMedia)
    {
        $wysiwygMedia->delete();

        return response()->json(['success' => true]);
    }
}
