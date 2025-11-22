<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentService
{
    public function storeDocumentFile(UploadedFile $file): string
    {
        $destDir = public_path('uploads/documents');
        if (! File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        return 'uploads/documents/'.$filename;
    }
}
