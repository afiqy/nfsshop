<?php

namespace App\Services;

use App\Models\InvoicePaymentSubmission;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentProofStorage
{
    public function store(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('The payment proof could not be read.');
        }

        $path = 'payment-proofs/'.tenant('id').'/'.Str::uuid().'.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($contents))) {
            throw new RuntimeException('The payment proof could not be stored.');
        }

        return $path;
    }

    public function download(InvoicePaymentSubmission $submission): Response
    {
        $encrypted = Storage::disk('local')->get($submission->proof_path);
        if (! is_string($encrypted)) {
            throw new RuntimeException('The payment proof is unavailable.');
        }

        $extension = match ($submission->proof_mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => throw new RuntimeException('The payment proof has an unsupported file type.'),
        };

        return response(Crypt::decryptString($encrypted), 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="payment-proof.'.$extension.'"',
            'Content-Type' => $submission->proof_mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }
}
