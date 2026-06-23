<?php

namespace App\Http\Controllers;

use App\Models\Export;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function download(Export $export): StreamedResponse
    {
        abort_unless($export->user_id === auth()->id(), 403);
        abort_unless($export->isCompleted(), 404);

        $media = $export->getFirstMedia('export');

        abort_unless($media !== null, 404);

        return response()->streamDownload(function () use ($media) {
            echo file_get_contents($media->getPath());
        }, $media->file_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
