<?php

namespace App\Services;

use App\Models\Retreat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RetreatMediaService
{
    public const DISK = 'local';

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
