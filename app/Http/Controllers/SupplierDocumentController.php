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
            'documentType' => ['required', 'string', 'max:255'],
            'referenceNumber' => ['nullable', 'string', 'max:255'],
            'issueDate' => ['nullable', 'date'],
            'expiryDate' => ['nullable', 'date', 'after_or_equal:issueDate'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        // Check if there's an existing document of the same type for this supplier
        $existingDocument = $supplier->documents()
            ->where('document_type', $validated['documentType'])
            ->first();

        // Delete old document file if exists
        if ($existingDocument) {
            $this->deleteFile($existingDocument->file_path);
            $existingDocument->delete();
        }

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
            'document_type' => $validated['documentType'],
            'reference_number' => $validated['referenceNumber'] ?? null,
            'issue_date' => $validated['issueDate'] ?? null,
            'expiry_date' => $validated['expiryDate'] ?? null,
            'file_path' => $filePath,
            'status' => 'pending_verification',
            'notes' => $validated['notes'] ?? null,
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

        if ($document->status !== 'pending_verification') {
            return response()->json(['message' => 'Only pending verification documents can be deleted.'], 422);
        }

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
            'issueDate' => ['nullable', 'date'],
            'expiryDate' => ['nullable', 'date', 'after_or_equal:issueDate'],
            'notes' => ['nullable', 'string'],
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
            'status' => 'pending_verification',
            'issue_date' => $validated['issueDate'] ?? $document->issue_date,
            'expiry_date' => $validated['expiryDate'] ?? $document->expiry_date,
            'notes' => $validated['notes'] ?? $document->notes,
            'reviewed_by_admin_id' => null,
            'reviewed_at' => null,
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

