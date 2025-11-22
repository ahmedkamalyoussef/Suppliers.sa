<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SupplierDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        $documents = $supplier->documents()->latest()->get();

        return response()->json([
            'data' => $documents->map(fn (SupplierDocument $document) => $this->transformDocument($document)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        // Store new document in public/uploads/documents
        $destDir = public_path('uploads/documents');
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $file = $request->file('document');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $filePath = 'uploads/documents/' . $filename;

        $document = SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'file_path' => $filePath,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully and pending verification.',
            'data' => $this->transformDocument($document),
        ], 201);
    }

    public function destroy(Request $request, SupplierDocument $document): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($document->supplier_id !== $supplier->id) {
            abort(404);
        }

        // With simplified flow, allow deleting any document

        $this->deleteFile($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    public function resubmit(Request $request, SupplierDocument $document): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($document->supplier_id !== $supplier->id) {
            abort(404);
        }

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        // Delete old document file
        $this->deleteFile($document->file_path);

        // Store new document in public/uploads/documents
        $destDir = public_path('uploads/documents');
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $file = $request->file('document');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $filePath = 'uploads/documents/' . $filename;

        $document->forceFill([
            'file_path' => $filePath,
        ])->save();

        return response()->json([
            'message' => 'Document resubmitted successfully.',
            'data' => $this->transformDocument($document->fresh()),
        ]);
    }

    private function resolveSupplier(Request $request): Supplier
    {
        $user = $request->user();

        if (!$user instanceof Supplier) {
            abort(403, 'Only suppliers can manage documents.');
        }

        return $user;
    }

    private function deleteFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        // Handle different path formats
        $filePath = $path;
        
        if (Str::startsWith($path, ['http://', 'https://'])) {
            // Extract path from URL
            $filePath = Str::after($path, '/uploads/');
            if ($filePath !== $path) {
                $filePath = 'uploads/' . $filePath;
            }
        } elseif (Str::startsWith($path, 'storage/')) {
            // Legacy storage path, convert to uploads path if needed
            $filePath = Str::after($path, 'storage/documents/');
            if ($filePath !== $path) {
                $filePath = 'uploads/documents/' . $filePath;
            }
        }

        // Delete from public/uploads/documents
        if ($filePath && Str::startsWith($filePath, 'uploads/documents/')) {
            $fullPath = public_path($filePath);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        } elseif ($filePath) {
            // Fallback: try direct public path
            $fullPath = public_path($filePath);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }
    }
}

