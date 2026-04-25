<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierDocumentRequest;
use App\Http\Resources\Supplier\DocumentResource;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\DocumentService;
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
            'data' => $documents->map(fn (SupplierDocument $document) => (new DocumentResource($document))->toArray($request)),
        ]);
    }

    public function store(StoreSupplierDocumentRequest $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        // Delete any existing documents for this supplier
        $existingDocuments = $supplier->documents;
        
        // Store the new document
        $service = new DocumentService;
        $filePath = $service->storeDocumentFile($request->file('document'));

        // Delete old documents and their files
        foreach ($existingDocuments as $existingDoc) {
            $this->deleteFile($existingDoc->file_path);
            $existingDoc->delete();
        }

        $document = SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        // Update supplier status to pending when document is uploaded
        $supplier->update(['status' => 'pending']);

        return response()->json([
            'message' => 'Document uploaded successfully and pending verification. Any existing documents were removed.',
            'data' => (new DocumentResource($document))->toArray($request),
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

    public function resubmit(StoreSupplierDocumentRequest $request, SupplierDocument $document): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($document->supplier_id !== $supplier->id) {
            abort(404);
        }

        // Delete old document file
        $this->deleteFile($document->file_path);

        $service = new DocumentService;
        $filePath = $service->storeDocumentFile($request->file('document'));

        $document->forceFill([
            'file_path' => $filePath,
            'status' => 'pending',
        ])->save();

        // Update supplier status to pending when document is resubmitted
        $supplier->update(['status' => 'pending']);

        return response()->json([
            'message' => 'Document resubmitted successfully.',
            'data' => (new DocumentResource($document->fresh()))->toArray($request),
        ]);
    }

    private function resolveSupplier(Request $request): Supplier
    {
        $user = $request->user();

        if (! $user instanceof Supplier) {
            abort(403, 'Only suppliers can manage documents.');
        }

        return $user;
    }

    private function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        // Handle different path formats
        $filePath = $path;

        if (Str::startsWith($path, ['http://', 'https://'])) {
            // Extract path from URL
            $filePath = Str::after($path, '/uploads/');
            if ($filePath !== $path) {
                $filePath = 'uploads/'.$filePath;
            }
        } elseif (Str::startsWith($path, 'storage/')) {
            // Legacy storage path, convert to uploads path if needed
            $filePath = Str::after($path, 'storage/documents/');
            if ($filePath !== $path) {
                $filePath = 'uploads/documents/'.$filePath;
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
