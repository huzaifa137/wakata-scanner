<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'zone',
        'ref_no',
        'subject',
        'exam_year',
        'source_file',
        'scan_type',
    ];

    public function entries()
    {
        return $this->hasMany(ScoreEntry::class);
    }
}
