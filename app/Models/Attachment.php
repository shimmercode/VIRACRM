<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REQ-06.05 / REQ-10.06 — file storage.
 *
 * Files live in the private disk, addressed by a generated path. The original
 * filename is kept for display only and is never used to build a filesystem
 * path, which is the usual path-traversal foothold in upload features.
 */
class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'disk', 'path', 'original_name', 'mime', 'size',
        'sha256', 'category', 'is_confidential', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['بایت', 'کیلوبایت', 'مگابایت'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return \App\Support\Money::toPersianDigits(number_format($bytes, $i === 0 ? 0 : 1)) . ' ' . $units[$i];
    }
}
