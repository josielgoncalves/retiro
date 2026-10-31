<?php

namespace App\Services;

use App\Models\Retreat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RetreatMediaService
{
    public const DISK = 'local';

    public function coverUrl(Retreat $retreat): ?string
    {
        if (! $retreat->cover_image_path) {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($retreat->cover_image_path)) {
            Log::warning('Capa de retiro cadastrada, mas ausente no storage.', [
                'retreat_id' => $retreat->id,
                'disk' => self::DISK,
            ]);

            return null;
        }

        return route('retreats.cover', [
            'retreat' => $retreat->id,
            'v' => substr(hash('sha256', $retreat->cover_image_path), 0, 12),
        ]);
    }

    public function replaceCover(Retreat $retreat, UploadedFile $image): string
    {
        $oldPath = $retreat->cover_image_path;
        $newPath = sprintf('retreats/%d/covers/%s.%s', $retreat->id, Str::uuid(), $image->extension());

        if (! Storage::disk(self::DISK)->putFileAs(dirname($newPath), $image, basename($newPath))) {
            throw new RuntimeException('Não foi possível armazenar a imagem de capa.');
        }

        if (DB::transactionLevel() > 0) {
            DB::afterRollBack(fn () => Storage::disk(self::DISK)->delete($newPath));
        }

        try {
            $retreat->update(['cover_image_path' => $newPath]);
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            DB::afterCommit(function () use ($oldPath) {
                if (! Retreat::where('cover_image_path', $oldPath)->exists()) {
                    Storage::disk(self::DISK)->delete($oldPath);
                }
            });
        }

        return $newPath;
    }

    public function removeCover(Retreat $retreat): void
    {
        $oldPath = $retreat->cover_image_path;
        $retreat->update(['cover_image_path' => null]);

        if ($oldPath) {
            DB::afterCommit(function () use ($oldPath) {
                if (! Retreat::where('cover_image_path', $oldPath)->exists()) {
                    Storage::disk(self::DISK)->delete($oldPath);
                }
            });
        }
    }
}
