<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentService
{
    public function storeDocumentFile(UploadedFile $file): string
    {
        try {
            $destDir = public_path('uploads/documents');
            \Log::info('Document upload: destination dir = ' . $destDir);

            if (! File::exists($destDir)) {
                \Log::info('Document upload: creating directory');
                File::makeDirectory($destDir, 0755, true);
            }

            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            \Log::info('Document upload: moving file to ' . $destDir . '/' . $filename);
            $file->move($destDir, $filename);
            \Log::info('Document upload: file moved successfully');

            return 'uploads/documents/'.$filename;
        } catch (\Exception $e) {
            \Log::error('Document upload failed in service: ' . $e->getMessage());
            throw $e;
        }
    }
}
